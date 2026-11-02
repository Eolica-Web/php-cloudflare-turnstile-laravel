<?php

declare(strict_types=1);

use Eolica\LaravelCloudflareTurnstile\TokenVerifications;

covers(TokenVerifications::class);

it('remembers whether each token passed', function (): void {
    $verifications = new TokenVerifications();
    $verifications->add('XXXX.PASSED.TOKEN.XXXX', action: null, passed: true);
    $verifications->add('XXXX.FAILED.TOKEN.XXXX', action: null, passed: false);

    expect($verifications->has('XXXX.PASSED.TOKEN.XXXX'))
        ->toBeTrue()
        ->and($verifications->passed('XXXX.PASSED.TOKEN.XXXX', action: null))
        ->toBeTrue()
        ->and($verifications->has('XXXX.FAILED.TOKEN.XXXX'))
        ->toBeTrue()
        ->and($verifications->passed('XXXX.FAILED.TOKEN.XXXX', action: null))
        ->toBeFalse()
        ->and($verifications->has('XXXX.UNKNOWN.TOKEN.XXXX'))
        ->toBeFalse();
});

it('only passes for the action the token was verified with', function (?string $action, bool $passed): void {
    $verifications = new TokenVerifications();
    $verifications->add('XXXX.DUMMY.TOKEN.XXXX', action: 'login', passed: true);

    expect($verifications->passed('XXXX.DUMMY.TOKEN.XXXX', $action))->toBe($passed);
})->with([
    'same action' => ['login', true],
    'different action' => ['signup', false],
    'no action' => [null, false],
]);

it('throws when asking whether an unverified token passed', function (): void {
    expect(fn(): bool => new TokenVerifications()->passed('XXXX.UNKNOWN.TOKEN.XXXX', action: null))
        ->toThrow(LogicException::class, 'The token has not been verified.');
});
