<?php

declare(strict_types=1);

namespace Eolica\LaravelCloudflareTurnstile\View\Components;

use Illuminate\Contracts\View\View;
use Illuminate\View\Component;
use Override;

final class Script extends Component
{
    public const string ORIGIN = 'https://challenges.cloudflare.com';

    public const string URL = self::ORIGIN . '/turnstile/v0/api.js';

    #[Override]
    public function render(): View
    {
        return view('turnstile::components.script');
    }
}
