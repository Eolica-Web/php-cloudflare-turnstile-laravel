<?php

declare(strict_types=1);

namespace Eolica\LaravelCloudflareTurnstile\Tests\Livewire\Fixtures;

use Livewire\Form;

final class ContactFormData extends Form
{
    public string $captcha = '';
}
