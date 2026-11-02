<?php

declare(strict_types=1);

namespace Eolica\LaravelCloudflareTurnstile;

use Eolica\CloudflareTurnstile\TurnstileClient;
use Eolica\LaravelCloudflareTurnstile\Http\LaravelHttpClient;
use Eolica\LaravelCloudflareTurnstile\Livewire\ResetVerifiedTokens;
use GuzzleHttp\Psr7\HttpFactory;
use Illuminate\Config\Repository;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Http\Client\Factory;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\ServiceProvider;
use Livewire\LivewireManager;

final class TurnstileServiceProvider extends ServiceProvider
{
    private const string CONFIG_PATH = __DIR__ . '/../config/turnstile.php';

    private const string LANG_PATH = __DIR__ . '/../lang';

    private const string VIEWS_PATH = __DIR__ . '/../resources/views';

    public function register(): void
    {
        $this->mergeConfigFrom(self::CONFIG_PATH, 'turnstile');

        $this->app->scoped(TokenVerifications::class);

        $this->callAfterResolving(LivewireManager::class, static function (LivewireManager $livewire): void {
            $livewire->componentHook(ResetVerifiedTokens::class);
        });

        $this->app->singleton(TurnstileClient::class, static function (Application $app): TurnstileClient {
            $config = $app->make(Repository::class);
            $hostname = $config->get('turnstile.hostname');
            $factory = new HttpFactory();

            return new TurnstileClient(
                secretKey: $config->string('turnstile.secret_key'),
                httpClient: new LaravelHttpClient($app->make(Factory::class)),
                requestFactory: $factory,
                streamFactory: $factory,
                expectedHostname: is_string($hostname) && filled($hostname) ? $hostname : null,
            );
        });
    }

    public function boot(): void
    {
        $this->loadTranslationsFrom(self::LANG_PATH, 'turnstile');
        $this->loadViewsFrom(self::VIEWS_PATH, 'turnstile');

        Blade::componentNamespace('Eolica\\LaravelCloudflareTurnstile\\View\\Components', prefix: 'turnstile');

        $this->publishes([self::CONFIG_PATH => $this->app->configPath('turnstile.php')], 'turnstile-config');
        $this->publishes([self::LANG_PATH => $this->app->langPath('vendor/turnstile')], 'turnstile-translations');
    }
}
