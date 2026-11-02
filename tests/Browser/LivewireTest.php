<?php

declare(strict_types=1);

use Eolica\LaravelCloudflareTurnstile\Head\TurnstilePlugin;
use Eolica\LaravelCloudflareTurnstile\Tests\Browser\Fixtures\ContactPage;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Route;
use Livewire\Livewire;
use Livewire\LivewireServiceProvider;
use Seo\Head\Head;

beforeEach(function (): void {
    config()->set('app.key', 'base64:' . base64_encode(random_bytes(32)));
    config()->set('turnstile.site_key', '1x00000000000000000000AA');
    config()->set('turnstile.secret_key', '1x0000000000000000000000000000000AA');
    app()->register(LivewireServiceProvider::class);
    Livewire::component('contact-page', ContactPage::class);

    Route::middleware('web')->get('/contact', static fn(): string => Blade::render(<<<'BLADE'
        <html>
            <head>
                <x-turnstile::script />
            </head>
            <body>
                <livewire:contact-page :show-widget="request()->boolean('widget', true)" />
            </body>
        </html>
        BLADE));

    Route::middleware('web')->get('/without-script', static fn(): string => Blade::render(<<<'BLADE'
        <html>
            <body>
                <livewire:contact-page :show-widget="false" />
            </body>
        </html>
        BLADE));

    Route::middleware('web')->get('/home', static fn(): string => Blade::render(<<<'BLADE'
        <html>
            <body>
                <a href="/contact" wire:navigate>Contact</a>
                @livewireScripts
            </body>
        </html>
        BLADE));

    $headTags = static fn(): string => Head::create()->plugin(TurnstilePlugin::make())->render()['headTags'];

    Route::middleware('web')->get('/head/home', static fn(): string => Blade::render(
        <<<'BLADE'
            <html>
                <head>
                    {!! $headTags !!}
                </head>
                <body>
                    <a href="/head/contact" wire:navigate>Contact</a>
                    @livewireScripts
                </body>
            </html>
            BLADE,
        ['headTags' => $headTags()],
    ));

    Route::middleware('web')->get('/head/contact', static fn(): string => Blade::render(
        <<<'BLADE'
            <html>
                <head>
                    {!! $headTags !!}
                </head>
                <body>
                    <a href="/head/home" wire:navigate>Home</a>
                    <livewire:contact-page />
                </body>
            </html>
            BLADE,
        ['headTags' => $headTags()],
    ));
});

function waitForToken(): string
{
    return <<<'JS'
        () => new Promise((resolve) => {
            const interval = setInterval(() => {
                if (Livewire.all()[0]?.$wire.captcha) {
                    clearInterval(interval);
                    resolve(true);
                }
            }, 50);
        })
        JS;
}

it('binds the token to the property', function (): void {
    visit('/contact')
        ->assertNoJavaScriptErrors()
        ->assertScript(waitForToken(), true)
        ->assertScript('Livewire.first().captcha', 'XXXX.DUMMY.TOKEN.XXXX');
});

it('keeps the token after a validation error in another field', function (): void {
    $page = visit('/contact')->assertScript(waitForToken(), true);

    $page->script(<<<'JS'
        () => {
            window.resets = 0;
            const reset = window.turnstile.reset;
            window.turnstile.reset = (...args) => {
                window.resets++;

                return reset(...args);
            };
        }
        JS);

    $page
        ->fill('email', 'invalid')
        ->press('Send')
        ->assertSee('The email field must be a valid email address.')
        ->assertScript('window.resets', 0)
        ->assertScript('Livewire.first().captcha', 'XXXX.DUMMY.TOKEN.XXXX')
        ->fill('email', 'john@example.com')
        ->press('Send')
        ->assertSee('Sent: 1')
        ->assertScript('window.resets', 1)
        ->assertDontSee('The verification failed.')
        ->assertNoJavaScriptErrors();
});

it('gets a new token after each successful submission', function (): void {
    visit('/contact')
        ->assertScript(waitForToken(), true)
        ->fill('email', 'john@example.com')
        ->press('Send')
        ->assertSee('Sent: 1')
        ->assertScript(waitForToken(), true)
        ->press('Send')
        ->assertSee('Sent: 2')
        ->assertDontSee('The verification failed.')
        ->assertNoJavaScriptErrors();
});

it('renders the widget after the page loads', function (): void {
    visit('/contact?widget=0')
        ->press('Show widget')
        ->assertScript(waitForToken(), true)
        ->fill('email', 'john@example.com')
        ->press('Send')
        ->assertSee('Sent: 1')
        ->assertNoJavaScriptErrors();
});

it('removes the widget when it is no longer rendered', function (): void {
    $page = visit('/contact')->assertScript(waitForToken(), true);

    $page->script(<<<'JS'
        () => {
            window.removes = 0;
            const remove = window.turnstile.remove;
            window.turnstile.remove = (...args) => {
                window.removes++;

                return remove(...args);
            };
        }
        JS);

    $page
        ->press('Hide widget')
        ->assertMissing('[x-modelable="token"]')
        ->assertScript('window.removes', 1)
        ->press('Show widget')
        ->assertScript(waitForToken(), true)
        ->fill('email', 'john@example.com')
        ->press('Send')
        ->assertSee('Sent: 1')
        ->assertNoJavaScriptErrors();
});

it('renders the widget after navigating with wire:navigate', function (): void {
    visit('/home')
        ->click('Contact')
        ->assertPathIs('/contact')
        ->assertScript(waitForToken(), true)
        ->fill('email', 'john@example.com')
        ->press('Send')
        ->assertSee('Sent: 1')
        ->assertNoJavaScriptErrors();
});

it('loads the script added by the head plugin once when navigating with wire:navigate', function (): void {
    visit('/head/home')
        ->click('Contact')
        ->assertPathIs('/head/contact')
        ->assertScript(waitForToken(), true)
        ->click('Home')
        ->assertPathIs('/head/home')
        ->click('Contact')
        ->assertPathIs('/head/contact')
        ->assertScript(waitForToken(), true)
        ->assertScript('document.querySelectorAll(\'script[src*="challenges.cloudflare.com"]\').length', 1)
        ->fill('email', 'john@example.com')
        ->press('Send')
        ->assertSee('Sent: 1')
        ->assertNoJavaScriptErrors();
});

it('logs an error when the script is missing', function (): void {
    $page = visit('/without-script');

    $page->script(<<<'JS'
        () => {
            window.errors = [];
            console.error = (message) => window.errors.push(message);
        }
        JS);

    $page
        ->press('Show widget')
        ->assertPresent('[x-modelable="token"]')
        ->assertScript('window.errors', [
            'The Cloudflare Turnstile script is missing. Add <x-turnstile::script /> to your layout.',
        ]);
});
