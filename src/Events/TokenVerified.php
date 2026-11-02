<?php

declare(strict_types=1);

namespace Eolica\LaravelCloudflareTurnstile\Events;

use Eolica\CloudflareTurnstile\VerifyResponse;

final readonly class TokenVerified
{
    public function __construct(
        public VerifyResponse $response,
        public ?string $action,
    ) {}
}
