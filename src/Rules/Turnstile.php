<?php

declare(strict_types=1);

namespace Eolica\LaravelCloudflareTurnstile\Rules;

use Closure;
use Eolica\CloudflareTurnstile\Exception\TurnstileException;
use Eolica\CloudflareTurnstile\TurnstileClient;
use Eolica\LaravelCloudflareTurnstile\Events\TokenVerified;
use Eolica\LaravelCloudflareTurnstile\TokenVerifications;
use Illuminate\Config\Repository;
use Illuminate\Container\Container;
use Illuminate\Contracts\Debug\ExceptionHandler;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Contracts\Validation\ValidatorAwareRule;
use Illuminate\Http\Request;
use Illuminate\Validation\Validator;
use SensitiveParameter;

final class Turnstile implements ValidationRule, ValidatorAwareRule
{
    private const string MESSAGE = 'turnstile::validation.failed';

    public bool $implicit = true;

    private ?Validator $validator = null;

    /**
     * @var array<string, mixed>
     */
    private array $values = [];

    public function __construct(
        private readonly ?string $action = null,
        private readonly ?bool $sendRemoteIp = null,
    ) {}

    public function setValidator(Validator $validator): static
    {
        if ($this->validator !== $validator) {
            $this->validator = $validator;
            $this->values = [];

            $validator->after($this->validateAfterOtherFields(...));
        }

        return $this;
    }

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if ($this->validator === null) {
            if (!$this->passes($value)) {
                $fail(self::MESSAGE)->translate();
            }

            return;
        }

        $this->values[$attribute] = $value;
    }

    private function validateAfterOtherFields(Validator $validator): void
    {
        foreach ($this->values as $attribute => $value) {
            if ($validator->messages()->isNotEmpty()) {
                return;
            }

            if (!$this->passes($value)) {
                $validator->fallbackMessages['turnstile'] = $validator->getTranslator()->get(self::MESSAGE);
                $validator->addFailure($attribute, 'turnstile');
            }
        }
    }

    private function passes(mixed $value): bool
    {
        return is_string($value) && $this->verify($value);
    }

    private function verify(#[SensitiveParameter] string $token): bool
    {
        $container = Container::getInstance();

        $verifications = $container->make(TokenVerifications::class);

        if (!$verifications->has($token)) {
            $verifications->add($token, $this->action, $this->send($container, $token));
        }

        return $verifications->passed($token, $this->action);
    }

    private function send(Container $container, #[SensitiveParameter] string $token): bool
    {
        try {
            $response = $container
                ->make(TurnstileClient::class)
                ->withExpectedAction($this->action)
                ->verify($token, remoteIp: $this->remoteIp($container));
        } catch (TurnstileException $e) {
            $container->make(ExceptionHandler::class)->report($e);

            return false;
        }

        $container->make(Dispatcher::class)->dispatch(new TokenVerified($response, $this->action));

        return $response->success;
    }

    private function remoteIp(Container $container): ?string
    {
        $sendRemoteIp = $this->sendRemoteIp ?? $container->make(Repository::class)->boolean('turnstile.send_remote_ip');

        if (!$sendRemoteIp) {
            return null;
        }

        return $container->make(Request::class)->ip();
    }
}
