<?php

declare(strict_types=1);

namespace Eolica\LaravelCloudflareTurnstile\Http;

use Illuminate\Http\Client\ConnectionException;
use Psr\Http\Client\NetworkExceptionInterface;
use Psr\Http\Message\RequestInterface;
use RuntimeException;

final class NetworkException extends RuntimeException implements NetworkExceptionInterface
{
    public function __construct(
        private readonly RequestInterface $request,
        ConnectionException $previous,
    ) {
        parent::__construct($previous->getMessage(), previous: $previous);
    }

    public function getRequest(): RequestInterface
    {
        return $this->request;
    }
}
