<?php

declare(strict_types=1);

namespace Eolica\LaravelCloudflareTurnstile;

use LogicException;
use SensitiveParameter;

final class TokenVerifications
{
    /**
     * @var array<string, bool>
     */
    private array $results = [];

    public function add(#[SensitiveParameter] string $token, bool $passed): void
    {
        $this->results[$token] = $passed;
    }

    public function has(#[SensitiveParameter] string $token): bool
    {
        return array_key_exists($token, $this->results);
    }

    /**
     * @throws LogicException
     */
    public function passed(#[SensitiveParameter] string $token): bool
    {
        if (!$this->has($token)) {
            throw new LogicException('The token has not been verified.');
        }

        return $this->results[$token];
    }
}
