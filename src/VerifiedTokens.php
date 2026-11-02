<?php

declare(strict_types=1);

namespace Eolica\LaravelCloudflareTurnstile;

use SensitiveParameter;

final class VerifiedTokens
{
    /**
     * @var array<string, true>
     */
    private array $tokens = [];

    public function add(#[SensitiveParameter] string $token): void
    {
        $this->tokens[$token] = true;
    }

    public function has(#[SensitiveParameter] string $token): bool
    {
        return $this->tokens[$token] ?? false;
    }
}
