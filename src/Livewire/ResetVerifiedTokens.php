<?php

declare(strict_types=1);

namespace Eolica\LaravelCloudflareTurnstile\Livewire;

use Eolica\LaravelCloudflareTurnstile\TokenVerifications;
use Illuminate\Container\Container;
use Livewire\Component;
use Livewire\ComponentHook;
use Livewire\Features\SupportFormObjects\Form;

final class ResetVerifiedTokens extends ComponentHook
{
    public function dehydrate(): void
    {
        self::reset($this->component, Container::getInstance()->make(TokenVerifications::class));
    }

    private static function reset(Component|Form $target, TokenVerifications $verifications): void
    {
        foreach ($target->all() as $property => $value) {
            if ($value instanceof Form) {
                self::reset($value, $verifications);
            } elseif (is_string($value) && $verifications->has($value)) {
                $target->reset($property);
            }
        }
    }
}
