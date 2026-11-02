<?php

declare(strict_types=1);

namespace Eolica\LaravelCloudflareTurnstile\Head;

use Eolica\LaravelCloudflareTurnstile\View\Components\Script;
use Seo\Head\Head;
use Seo\Head\HeadPlugin;

final readonly class TurnstilePlugin implements HeadPlugin
{
    public const string SCRIPT_ID = 'cf-turnstile-api';

    private function __construct() {}

    public static function make(): self
    {
        return new self();
    }

    public function initialize(Head $head): void
    {
        $head->script(async: true, defer: true, id: self::SCRIPT_ID, src: Script::URL);
    }
}
