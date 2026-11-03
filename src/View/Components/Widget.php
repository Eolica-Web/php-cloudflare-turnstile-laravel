<?php

declare(strict_types=1);

namespace Eolica\LaravelCloudflareTurnstile\View\Components;

use Illuminate\Config\Repository;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Contracts\View\View;
use Illuminate\View\Component;
use Override;

final class Widget extends Component
{
    /**
     * @var array<string, string>
     */
    public readonly array $options;

    public function __construct(
        Repository $config,
        Application $app,
        ?string $action = null,
        ?string $cdata = null,
        ?string $theme = null,
        ?string $size = null,
        ?string $language = null,
        ?string $appearance = null,
    ) {
        $this->options = array_filter(
            [
                'sitekey' => $config->string('turnstile.site_key'),
                'action' => $action,
                'cData' => $cdata,
                'theme' => $theme,
                'size' => $size,
                'language' => $language ?? str_replace('_', replace: '-', subject: $app->getLocale()),
                'appearance' => $appearance,
            ],
            is_string(...),
        );
    }

    /**
     * @return array<string, string>
     */
    public function dataAttributes(): array
    {
        return array_combine(
            array_map(static fn(string $option): string => 'data-' . strtolower($option), array_keys($this->options)),
            $this->options,
        );
    }

    #[Override]
    public function render(): View
    {
        return view('turnstile::components.widget');
    }
}
