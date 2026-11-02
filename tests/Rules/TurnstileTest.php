<?php

declare(strict_types=1);

use Eolica\CloudflareTurnstile\Exception\HttpException;
use Eolica\CloudflareTurnstile\TurnstileClient;
use Eolica\LaravelCloudflareTurnstile\Rules\Turnstile;
use Eolica\LaravelCloudflareTurnstile\VerifiedTokens;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Http\Client\Request as HttpRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Exceptions;
use Illuminate\Support\Facades\Http;

covers(Turnstile::class);

beforeEach(function (): void {
    config()->set('turnstile.secret_key', '1x0000000000000000000000000000000AA');
});

/**
 * @param array<string, mixed> $data
 */
function turnstileValidator(array $data, Turnstile $rule = new Turnstile()): Validator
{
    return validator($data, ['cf-turnstile-response' => [$rule]]);
}

it('passes when Cloudflare accepts the token', function (): void {
    Http::fake([TurnstileClient::SITEVERIFY_URL => Http::response(['success' => true])]);

    expect(turnstileValidator(['cf-turnstile-response' => 'XXXX.DUMMY.TOKEN.XXXX'])->fails())->toBeFalse();
});

it('records the verified tokens per request', function (): void {
    Http::fake([TurnstileClient::SITEVERIFY_URL => Http::response(['success' => false])]);

    $_ = turnstileValidator(['cf-turnstile-response' => 'XXXX.DUMMY.TOKEN.XXXX'])->fails();

    expect(app(VerifiedTokens::class)->has('XXXX.DUMMY.TOKEN.XXXX'))->toBeTrue();

    app()->forgetScopedInstances();

    expect(app(VerifiedTokens::class)->has('XXXX.DUMMY.TOKEN.XXXX'))->toBeFalse();
});

it('fails with a translated message when Cloudflare rejects the token', function (): void {
    Http::fake([
        TurnstileClient::SITEVERIFY_URL => Http::response([
            'success' => false,
            'error-codes' => ['invalid-input-response'],
        ]),
    ]);

    $validator = turnstileValidator(['cf-turnstile-response' => 'XXXX.DUMMY.TOKEN.XXXX']);

    expect($validator->fails())
        ->toBeTrue()
        ->and($validator->errors()->first('cf-turnstile-response'))
        ->toBe('The verification failed. Please try again.');
});

it('checks the expected action', function (?string $expectedAction, bool $fails): void {
    Http::fake([TurnstileClient::SITEVERIFY_URL => Http::response(['success' => true, 'action' => 'login'])]);

    $validator = turnstileValidator([
        'cf-turnstile-response' => 'XXXX.DUMMY.TOKEN.XXXX',
    ], new Turnstile(action: $expectedAction));

    expect($validator->fails())->toBe($fails);
})->with([
    'no action' => [null, false],
    'matching action' => ['login', false],
    'different action' => ['signup', true],
]);

it('sends the IP address of the request unless disabled', function (bool $config, ?bool $rule, bool $sent): void {
    config()->set('turnstile.send_remote_ip', $config);
    app()->instance('request', Request::create('/', 'POST', server: ['REMOTE_ADDR' => '203.0.113.1']));
    Http::fake([TurnstileClient::SITEVERIFY_URL => Http::response(['success' => true])]);

    $_ = turnstileValidator([
        'cf-turnstile-response' => 'XXXX.DUMMY.TOKEN.XXXX',
    ], new Turnstile(sendRemoteIp: $rule))->fails();

    $body = 'secret=1x0000000000000000000000000000000AA&response=XXXX.DUMMY.TOKEN.XXXX';
    Http::assertSent(
        static fn(HttpRequest $request): bool => $request->body() === ($sent ? $body . '&remoteip=203.0.113.1' : $body),
    );
})->with([
    'enabled in the configuration' => [true, null, true],
    'disabled in the configuration' => [false, null, false],
    'enabled in the rule' => [false, true, true],
    'disabled in the rule' => [true, false, false],
]);

it('fails without calling Cloudflare when the token is missing', function (): void {
    Http::fake();

    expect(turnstileValidator([])->fails())->toBeTrue();
    Http::assertNothingSent();
});

it('fails without calling Cloudflare when the token is empty or not a string', function (
    #[SensitiveParameter]
    mixed $token,
): void {
    Http::fake();

    expect(turnstileValidator(['cf-turnstile-response' => $token])->fails())->toBeTrue();
    Http::assertNothingSent();
})->with([
    'null' => [null],
    'empty' => [''],
    'array' => [['XXXX.DUMMY.TOKEN.XXXX']],
]);

it('fails and reports the exception when Cloudflare cannot be reached', function (): void {
    Exceptions::fake();
    Http::fake([TurnstileClient::SITEVERIFY_URL => Http::failedConnection()]);

    expect(turnstileValidator(['cf-turnstile-response' => 'XXXX.DUMMY.TOKEN.XXXX'])->fails())->toBeTrue();
    Exceptions::assertReported(HttpException::class);
});
