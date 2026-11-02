<?php

declare(strict_types=1);

use Eolica\CloudflareTurnstile\Exception\HttpException;
use Eolica\CloudflareTurnstile\TurnstileClient;
use Eolica\CloudflareTurnstile\VerifyResponse;
use Eolica\LaravelCloudflareTurnstile\Http\NetworkException;
use Eolica\LaravelCloudflareTurnstile\TurnstileServiceProvider;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\ServiceProvider;

covers(TurnstileServiceProvider::class);

it('merges the default configuration', function (): void {
    expect(config('turnstile'))->toBe([
        'site_key' => null,
        'secret_key' => null,
        'hostname' => null,
        'send_remote_ip' => true,
    ]);
});

it('registers TurnstileClient as a singleton', function (): void {
    config()->set('turnstile.secret_key', '1x0000000000000000000000000000000AA');

    expect(app(TurnstileClient::class))->toBe(app(TurnstileClient::class));
});

it('sends the configured secret key to Cloudflare through the Laravel HTTP client', function (): void {
    config()->set('turnstile.secret_key', '1x0000000000000000000000000000000AA');
    Http::fake([TurnstileClient::SITEVERIFY_URL => Http::response(['success' => true])]);

    $response = app(TurnstileClient::class)->verify('XXXX.DUMMY.TOKEN.XXXX');

    expect($response->success)->toBeTrue();
    Http::assertSent(
        static fn(Request $request): bool => (
            $request->body() === 'secret=1x0000000000000000000000000000000AA&response=XXXX.DUMMY.TOKEN.XXXX'
        ),
    );
});

it('uses fakes registered after TurnstileClient has been resolved', function (): void {
    config()->set('turnstile.secret_key', '1x0000000000000000000000000000000AA');
    $turnstile = app(TurnstileClient::class);

    Http::fake([
        TurnstileClient::SITEVERIFY_URL => Http::response([
            'success' => false,
            'error-codes' => ['timeout-or-duplicate'],
        ]),
    ]);

    expect($turnstile->verify('XXXX.DUMMY.TOKEN.XXXX')->errorCodes)->toBe(['timeout-or-duplicate']);
});

it('turns connection failures into TurnstileClient HTTP exceptions', function (): void {
    config()->set('turnstile.secret_key', '1x0000000000000000000000000000000AA');
    Http::fake([TurnstileClient::SITEVERIFY_URL => Http::failedConnection()]);
    $turnstile = app(TurnstileClient::class);

    $exception = thrownBy(HttpException::class, fn(): VerifyResponse => $turnstile->verify('XXXX.DUMMY.TOKEN.XXXX'));

    expect($exception?->getPrevious())->toBeInstanceOf(NetworkException::class);
});

it('expects the configured hostname', function (?string $hostname, bool $success): void {
    config()->set('turnstile.secret_key', '1x0000000000000000000000000000000AA');
    config()->set('turnstile.hostname', $hostname);
    Http::fake([TurnstileClient::SITEVERIFY_URL => Http::response(['success' => true, 'hostname' => 'other.example'])]);

    expect(app(TurnstileClient::class)->verify('XXXX.DUMMY.TOKEN.XXXX')->success)->toBe($success);
})->with([
    'not configured' => [null, true],
    'empty' => ['', true],
    'matching' => ['other.example', true],
    'not matching' => ['example.com', false],
]);

it('fails to resolve TurnstileClient without a secret key', function (): void {
    expect(fn(): TurnstileClient => app(TurnstileClient::class))
        ->toThrow(InvalidArgumentException::class, 'turnstile.secret_key');
});

it('publishes its resources', function (string $tag, string $source, string $destination): void {
    $paths = ServiceProvider::pathsToPublish(TurnstileServiceProvider::class, $tag);

    expect(array_map(realpath(...), array_keys($paths)))
        ->toBe([realpath(__DIR__ . '/../' . $source)])
        ->and(array_values($paths))
        ->toBe([$destination]);
})->with([
    'configuration' => ['turnstile-config', 'config/turnstile.php', fn(): string => app()->configPath('turnstile.php')],
    'translations' => ['turnstile-translations', 'lang', fn(): string => app()->langPath('vendor/turnstile')],
]);
