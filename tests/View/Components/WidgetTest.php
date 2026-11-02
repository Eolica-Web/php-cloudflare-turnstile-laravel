<?php

declare(strict_types=1);

use Eolica\LaravelCloudflareTurnstile\View\Components\Script;
use Eolica\LaravelCloudflareTurnstile\View\Components\Widget;
use Illuminate\Support\Facades\Blade;

covers(Widget::class, Script::class);

beforeEach(function (): void {
    config()->set('turnstile.site_key', '1x00000000000000000000AA');
});

function renderBlade(string $template): string
{
    return trim((string) preg_replace('/\s+/', replacement: ' ', subject: Blade::render($template)));
}

it('renders the standard Cloudflare markup without wire:model', function (): void {
    expect(renderBlade('<x-turnstile::widget />'))
        ->toBe('<div data-sitekey="1x00000000000000000000AA" class="cf-turnstile"></div>');
});

it('renders the widget options as data attributes', function (): void {
    $html = renderBlade(
        '<x-turnstile::widget action="login" cdata="session" theme="dark" size="flexible" language="es" appearance="interaction-only" />',
    );

    expect($html)->toContain(
        '<div data-sitekey="1x00000000000000000000AA" data-action="login" data-cdata="session" data-theme="dark"'
        . ' data-size="flexible" data-language="es" data-appearance="interaction-only" class="cf-turnstile"></div>',
    );
});

it('passes any other attribute to the widget container', function (): void {
    expect(renderBlade('<x-turnstile::widget class="mt-4" data-callback="onSuccess" />'))
        ->toContain(
            '<div data-sitekey="1x00000000000000000000AA" class="cf-turnstile mt-4" data-callback="onSuccess"></div>',
        );
});

it('renders the widget from JavaScript and binds the token with wire:model', function (): void {
    $html = renderBlade('<x-turnstile::widget wire:model="captcha" action="login" />');

    expect($html)->not->toContain('cf-turnstile');
    expect($html)->not->toContain('<script');
    expect($html)
        ->toContain('wire:ignore')
        ->toContain('x-modelable="token"')
        ->toContain('wire:model="captcha"')
        ->toContain(
            "window.turnstile.render(this.\$el, { ...JSON.parse('{\\u0022sitekey\\u0022:\\u00221x00000000000000000000AA\\u0022,\\u0022action\\u0022:\\u0022login\\u0022}'), 'response-field': false,",
        );
});

it('waits for the script in Livewire mode', function (): void {
    expect(renderBlade('<x-turnstile::widget wire:model="captcha" />'))
        ->toContain("const url = 'https:\\/\\/challenges.cloudflare.com\\/turnstile\\/v0\\/api.js';")
        ->toContain('const script = document.querySelector(`script[src=&quot;${url}&quot;]`);')
        ->toContain(
            "if (!script) { console.error('The Cloudflare Turnstile script is missing. Add &lt;x-turnstile::script /&gt; to your layout.'); return; }",
        )
        ->toContain("script.addEventListener('load', render);");
});

it('resets the widget when the bound token is emptied', function (): void {
    expect(renderBlade('<x-turnstile::widget wire:model="captcha" />'))
        ->toContain(
            "this.\$watch('token', (token) => { if (token || !this.solved) { return; } this.solved = false; window.turnstile.reset(this.widgetId); });",
        )
        ->toContain('const render = () => this.widgetId = window.turnstile.render(')
        ->toContain('callback: (token) => { this.solved = true; this.token = token; },')
        ->toContain("'expired-callback': () => { this.solved = false; this.token = null; },");
});

it('removes the widget when its element is removed', function (): void {
    expect(renderBlade('<x-turnstile::widget wire:model="captcha" />'))
        ->toContain('destroy() { if (this.widgetId !== null) { window.turnstile.remove(this.widgetId); } },');
});

it('fails to render without a site key', function (): void {
    config()->set('turnstile.site_key', null);

    expect(fn(): Widget => app(Widget::class))->toThrow(InvalidArgumentException::class, 'turnstile.site_key');
});

it('renders the script', function (): void {
    expect(renderBlade('<x-turnstile::script />'))
        ->toBe(
            '<script src="https://challenges.cloudflare.com/turnstile/v0/api.js" async defer data-navigate-once ></script>',
        );
});

it('renders the script with any extra attribute', function (): void {
    expect(renderBlade('<x-turnstile::script nonce="abc" />'))
        ->toBe(
            '<script src="https://challenges.cloudflare.com/turnstile/v0/api.js" async defer data-navigate-once nonce="abc"></script>',
        );
});
