<?php

declare(strict_types=1);

namespace Eolica\LaravelCloudflareTurnstile\Tests\Livewire\Fixtures;

use Eolica\LaravelCloudflareTurnstile\Rules\Turnstile;
use Livewire\Component;

final class ContactForm extends Component
{
    public string $email = '';

    public int $attempts = 0;

    public ?string $captcha = null;

    public string $newsletterCaptcha = '';

    public ContactFormData $form;

    public function submit(): void
    {
        $this->attempts++;

        $this->validate(['email' => ['required', 'email'], 'captcha' => [new Turnstile()]]);
    }

    public function subscribe(): void
    {
        $this->validate(['newsletterCaptcha' => [new Turnstile()]]);
    }

    public function submitForm(): void
    {
        $this->form->validate(['captcha' => [new Turnstile()]]);
    }

    public function render(): string
    {
        return '<div></div>';
    }
}
