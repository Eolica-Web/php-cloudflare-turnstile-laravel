<?php

declare(strict_types=1);

use Eolica\LaravelCloudflareTurnstile\Head\TurnstilePlugin;
use Seo\Head\Head;

covers(TurnstilePlugin::class);

it('adds the script to the head', function (): void {
    $head = Head::create()->plugin(TurnstilePlugin::make());

    expect($head->render(omitLineBreaks: true)['headTags'])
        ->toBe(
            '<script async defer id="cf-turnstile-api" src="https://challenges.cloudflare.com/turnstile/v0/api.js"></script>',
        );
});

it('adds the script only once', function (): void {
    $head = Head::create()->plugins([TurnstilePlugin::make(), TurnstilePlugin::make()]);

    expect(substr_count($head->render()['headTags'], needle: '<script'))->toBe(1);
});
