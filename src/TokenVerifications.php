<?php

declare(strict_types=1);

namespace Eolica\LaravelCloudflareTurnstile;

use LogicException;
use SensitiveParameter;

final class TokenVerifications
{
    /**
     * @var array<string, array{action: ?string, passed: bool}>
     */
    private array $results = [];

    public function add(#[SensitiveParameter] string $token, ?string $action, bool $passed): void
    {
        $this->results[$token] = ['action' => $action, 'passed' => $passed];
    }

    public function has(#[SensitiveParameter] string $token): bool
    {
        return array_key_exists($token, $this->results);
    }

    /**
     * @throws LogicException
     */
    public function passed(#[SensitiveParameter] string $token, ?string $action): bool
    {
        if (!$this->has($token)) {
            throw new LogicException('The token has not been verified.');
        }

        return $this->results[$token]['passed'] && $this->results[$token]['action'] === $action;
    }
}
