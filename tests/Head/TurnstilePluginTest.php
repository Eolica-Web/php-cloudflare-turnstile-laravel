<?php

declare(strict_types=1);

use Eolica\LaravelCloudflareTurnstile\Head\TurnstilePlugin;
use Seo\Head\Head;

covers(TurnstilePlugin::class);

it('adds the preconnect hint and the script to the head', function (): void {
    $head = Head::create()->plugin(TurnstilePlugin::make());

    expect($head->render(omitLineBreaks: true)['headTags'])
        ->toBe(
            '<link href="https://challenges.cloudflare.com" id="cf-turnstile-preconnect" rel="preconnect">'
            . '<script async defer id="cf-turnstile-api" src="https://challenges.cloudflare.com/turnstile/v0/api.js"></script>',
        );
});

it('adds the preconnect hint and the script only once', function (): void {
    $headTags = Head::create()->plugins([TurnstilePlugin::make(), TurnstilePlugin::make()])->render()['headTags'];

    expect(substr_count($headTags, needle: '<link'))->toBe(1)->and(substr_count($headTags, needle: '<script'))->toBe(1);
});
