<?php

declare(strict_types=1);

use Eolica\LaravelCloudflareTurnstile\TokenVerifications;

covers(TokenVerifications::class);

it('remembers whether each token passed', function (): void {
    $verifications = new TokenVerifications();
    $verifications->add('XXXX.PASSED.TOKEN.XXXX', passed: true);
    $verifications->add('XXXX.FAILED.TOKEN.XXXX', passed: false);

    expect($verifications->has('XXXX.PASSED.TOKEN.XXXX'))
        ->toBeTrue()
        ->and($verifications->passed('XXXX.PASSED.TOKEN.XXXX'))
        ->toBeTrue()
        ->and($verifications->has('XXXX.FAILED.TOKEN.XXXX'))
        ->toBeTrue()
        ->and($verifications->passed('XXXX.FAILED.TOKEN.XXXX'))
        ->toBeFalse()
        ->and($verifications->has('XXXX.UNKNOWN.TOKEN.XXXX'))
        ->toBeFalse();
});

it('throws when asking whether an unverified token passed', function (): void {
    expect(fn(): bool => new TokenVerifications()->passed('XXXX.UNKNOWN.TOKEN.XXXX'))
        ->toThrow(LogicException::class, 'The token has not been verified.');
});
