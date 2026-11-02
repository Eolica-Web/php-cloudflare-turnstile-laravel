<?php

declare(strict_types=1);

use Eolica\CloudflareTurnstile\TurnstileClient;
use Eolica\LaravelCloudflareTurnstile\Livewire\ResetVerifiedTokens;
use Eolica\LaravelCloudflareTurnstile\Tests\Livewire\Fixtures\ContactForm;
use Eolica\LaravelCloudflareTurnstile\TurnstileServiceProvider;
use Eolica\LaravelCloudflareTurnstile\VerifiedTokens;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;
use Livewire\LivewireServiceProvider;

covers(ResetVerifiedTokens::class, TurnstileServiceProvider::class, VerifiedTokens::class);

beforeEach(function (): void {
    config()->set('turnstile.secret_key', '1x0000000000000000000000000000000AA');
    config()->set('app.key', 'base64:' . base64_encode(random_bytes(32)));
    app()->register(LivewireServiceProvider::class);
});

function fakeSiteverify(bool $success = true): void
{
    Http::fake([TurnstileClient::SITEVERIFY_URL => Http::response(['success' => $success])]);
}

it('resets the token after it is verified', function (): void {
    fakeSiteverify();

    $component = Livewire::test(ContactForm::class)
        ->set('email', 'john@example.com')
        ->set('captcha', 'XXXX.DUMMY.TOKEN.XXXX')
        ->call('submit');

    $component->assertHasNoErrors();
    $component->assertSet('captcha', null);
});

it('resets the token when the validation of another field fails', function (): void {
    fakeSiteverify();

    $component = Livewire::test(ContactForm::class)
        ->set('email', 'invalid')
        ->set('captcha', 'XXXX.DUMMY.TOKEN.XXXX')
        ->call('submit');

    $component->assertHasErrors('email');
    $component->assertSet('captcha', null);
});

it('resets the token when Cloudflare rejects it', function (): void {
    fakeSiteverify(success: false);

    $component = Livewire::test(ContactForm::class)
        ->set('email', 'john@example.com')
        ->set('captcha', 'XXXX.DUMMY.TOKEN.XXXX')
        ->call('submit');

    $component->assertHasErrors('captcha');
    $component->assertSet('captcha', null);
});

it('keeps the token when it is not verified', function (): void {
    Livewire::test(ContactForm::class)
        ->set('email', 'john@example.com')
        ->set('captcha', 'XXXX.DUMMY.TOKEN.XXXX')
        ->call('$refresh')
        ->assertSet('captcha', 'XXXX.DUMMY.TOKEN.XXXX')
        ->assertSet('email', 'john@example.com');
});

it('resets each token to its default value independently', function (): void {
    fakeSiteverify();

    Livewire::test(ContactForm::class)
        ->set('captcha', 'XXXX.CONTACT.TOKEN.XXXX')
        ->set('newsletterCaptcha', 'XXXX.NEWSLETTER.TOKEN.XXXX')
        ->call('subscribe')
        ->assertSet('newsletterCaptcha', '')
        ->assertSet('captcha', 'XXXX.CONTACT.TOKEN.XXXX');
});

it('resets the token of a form object', function (): void {
    fakeSiteverify();

    Livewire::test(ContactForm::class)
        ->set('form.captcha', 'XXXX.DUMMY.TOKEN.XXXX')
        ->call('submitForm')
        ->assertSet('form.captcha', '');
});

it('keeps the other properties', function (): void {
    fakeSiteverify();

    Livewire::test(ContactForm::class)
        ->set('email', 'invalid')
        ->set('captcha', 'XXXX.DUMMY.TOKEN.XXXX')
        ->call('submit')
        ->assertSet('email', 'invalid')
        ->assertSet('attempts', 1);
});
