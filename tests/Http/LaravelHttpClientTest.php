<?php

declare(strict_types=1);

use Eolica\LaravelCloudflareTurnstile\Http\LaravelHttpClient;
use Eolica\LaravelCloudflareTurnstile\Http\NetworkException;
use GuzzleHttp\Psr7\Request as PsrRequest;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Factory;
use Illuminate\Http\Client\Request;
use Illuminate\Http\Client\StrayRequestException;
use Illuminate\Support\Facades\Http;

covers(LaravelHttpClient::class, NetworkException::class);

function psrRequest(): PsrRequest
{
    return new PsrRequest(
        'POST',
        'https://example.com/endpoint',
        ['Content-Type' => 'application/x-www-form-urlencoded', 'X-Custom' => 'value'],
        'key=value',
    );
}

it('sends the request through the Laravel HTTP client', function (): void {
    Http::fake(['https://example.com/endpoint' => Http::response('{"ok":true}', 201, ['X-Response' => 'yes'])]);

    $response = new LaravelHttpClient(app(Factory::class))->sendRequest(psrRequest());

    expect($response->getStatusCode())
        ->toBe(201)
        ->and($response->getHeaderLine('X-Response'))
        ->toBe('yes')
        ->and((string) $response->getBody())
        ->toBe('{"ok":true}');
    Http::assertSent(
        static fn(Request $request): bool => (
            $request->method() === 'POST'
            && $request->url() === 'https://example.com/endpoint'
            && $request->header('Content-Type') === ['application/x-www-form-urlencoded']
            && $request->header('X-Custom') === ['value']
            && $request->body() === 'key=value'
        ),
    );
});

it('returns non successful responses instead of throwing', function (): void {
    Http::fake(['https://example.com/endpoint' => Http::response('{"error":true}', 400)]);

    $response = new LaravelHttpClient(app(Factory::class))->sendRequest(psrRequest());

    expect($response->getStatusCode())->toBe(400);
});

it('throws a PSR-18 network exception when the connection fails', function (): void {
    Http::fake(['https://example.com/endpoint' => Http::failedConnection('Connection refused')]);
    $client = new LaravelHttpClient(app(Factory::class));
    $request = psrRequest();

    $exception = thrownBy(NetworkException::class, fn(): mixed => $client->sendRequest($request));

    expect($exception)
        ->toBeInstanceOf(NetworkException::class)
        ->and($exception?->getMessage())
        ->toBe('Connection refused')
        ->and($exception?->getRequest())
        ->toBe($request)
        ->and($exception?->getPrevious())
        ->toBeInstanceOf(ConnectionException::class);
});

it('lets stray request exceptions through', function (): void {
    Http::preventStrayRequests();
    $client = new LaravelHttpClient(app(Factory::class));

    expect(fn(): mixed => $client->sendRequest(psrRequest()))->toThrow(StrayRequestException::class);
});
