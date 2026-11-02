<?php

declare(strict_types=1);

namespace Eolica\LaravelCloudflareTurnstile\Tests\Browser\Fixtures;

use Eolica\LaravelCloudflareTurnstile\Rules\Turnstile;
use Livewire\Component;

final class ContactPage extends Component
{
    public string $email = '';

    public ?string $captcha = null;

    public bool $showWidget = true;

    public int $sent = 0;

    public function send(): void
    {
        $this->validate(['email' => ['required', 'email'], 'captcha' => [new Turnstile()]]);

        $this->sent++;
    }

    public function render(): string
    {
        return <<<'BLADE'
            <div>
                <form wire:submit="send">
                    <input type="text" name="email" wire:model="email">
                    @error('email') <p>{{ $message }}</p> @enderror

                    @if ($showWidget)
                        <x-turnstile::widget wire:model="captcha" />
                    @endif
                    @error('captcha') <p>{{ $message }}</p> @enderror

                    <button type="submit">Send</button>
                </form>

                <button type="button" wire:click="$set('showWidget', true)">Show widget</button>

                <p>Sent: {{ $sent }}</p>
            </div>
            BLADE;
    }
}
