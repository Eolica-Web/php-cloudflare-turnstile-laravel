<?php

declare(strict_types=1);

use Eolica\LaravelCloudflareTurnstile\Rules\Turnstile;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Route;

beforeEach(function (): void {
    config()->set('app.key', 'base64:' . base64_encode(random_bytes(32)));
    config()->set('turnstile.site_key', '1x00000000000000000000AA');
    config()->set('turnstile.secret_key', '1x0000000000000000000000000000000AA');
});

it('submits the token of the widget in a form', function (): void {
    Route::middleware('web')->get('/contact', static fn(): string => Blade::render(<<<'BLADE'
        <html>
            <head>
                <x-turnstile::script />
            </head>
            <body>
                <form method="POST" action="/contact">
                    @csrf
                    <x-turnstile::widget />
                    <button type="submit">Send</button>
                </form>
            </body>
        </html>
        BLADE));

    Route::middleware('web')->post('/contact', static function (Request $request): string {
        $request->validate(['cf-turnstile-response' => [new Turnstile()]]);

        return 'Verified';
    });

    visit('/contact')
        ->assertNoJavaScriptErrors()
        ->waitForEvent('networkidle')
        ->assertValue('[name="cf-turnstile-response"]', 'XXXX.DUMMY.TOKEN.XXXX')
        ->press('Send')
        ->assertSee('Verified');
});
