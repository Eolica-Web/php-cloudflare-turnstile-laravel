<?php

declare(strict_types=1);

namespace Eolica\LaravelCloudflareTurnstile\Http;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Factory;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

final readonly class LaravelHttpClient implements ClientInterface
{
    public function __construct(
        private Factory $http,
    ) {}

    /**
     * @throws NetworkException
     */
    public function sendRequest(RequestInterface $request): ResponseInterface
    {
        try {
            return $this->http
                ->createPendingRequest()
                ->withHeaders($request->getHeaders())
                ->withBody((string) $request->getBody(), $request->getHeaderLine('Content-Type'))
                ->send($request->getMethod(), (string) $request->getUri())
                ->toPsrResponse();
        } catch (ConnectionException $e) {
            throw new NetworkException($request, $e);
        }
    }
}
