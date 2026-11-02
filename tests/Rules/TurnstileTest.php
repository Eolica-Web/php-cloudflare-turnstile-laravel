<?php

declare(strict_types=1);

use Eolica\CloudflareTurnstile\Exception\HttpException;
use Eolica\CloudflareTurnstile\TurnstileClient;
use Eolica\CloudflareTurnstile\VerifyResponse;
use Eolica\LaravelCloudflareTurnstile\Events\TokenVerified;
use Eolica\LaravelCloudflareTurnstile\Rules\Turnstile;
use Eolica\LaravelCloudflareTurnstile\TokenVerifications;
use Illuminate\Contracts\Translation\Translator;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Http\Client\Request as HttpRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Exceptions;
use Illuminate\Support\Facades\Http;
use Illuminate\Translation\PotentiallyTranslatedString;

covers(Turnstile::class, TokenVerified::class);

beforeEach(function (): void {
    config()->set('turnstile.secret_key', '1x0000000000000000000000000000000AA');
});

/**
 * @param array<string, mixed> $data
 */
function turnstileValidator(array $data, Turnstile $rule = new Turnstile()): Validator
{
    return validator($data, ['cf-turnstile-response' => [$rule]]);
}

it('passes when Cloudflare accepts the token', function (): void {
    Http::fake([TurnstileClient::SITEVERIFY_URL => Http::response(['success' => true])]);

    expect(turnstileValidator(['cf-turnstile-response' => 'XXXX.DUMMY.TOKEN.XXXX'])->fails())->toBeFalse();
});

it('records the verified tokens per request', function (): void {
    Http::fake([TurnstileClient::SITEVERIFY_URL => Http::response(['success' => false])]);

    $_ = turnstileValidator(['cf-turnstile-response' => 'XXXX.DUMMY.TOKEN.XXXX'])->fails();

    expect(app(TokenVerifications::class)->has('XXXX.DUMMY.TOKEN.XXXX'))->toBeTrue();

    app()->forgetScopedInstances();

    expect(app(TokenVerifications::class)->has('XXXX.DUMMY.TOKEN.XXXX'))->toBeFalse();
});

it('fails with a translated message when Cloudflare rejects the token', function (): void {
    Http::fake([
        TurnstileClient::SITEVERIFY_URL => Http::response([
            'success' => false,
            'error-codes' => ['invalid-input-response'],
        ]),
    ]);

    $validator = turnstileValidator(['cf-turnstile-response' => 'XXXX.DUMMY.TOKEN.XXXX']);

    expect($validator->fails())
        ->toBeTrue()
        ->and($validator->errors()->first('cf-turnstile-response'))
        ->toBe('The verification failed. Please try again.');
});

it('uses the custom messages of the validator', function (string $key): void {
    Http::fake([TurnstileClient::SITEVERIFY_URL => Http::response(['success' => false])]);

    $validator = validator(
        ['cf-turnstile-response' => 'XXXX.DUMMY.TOKEN.XXXX'],
        ['cf-turnstile-response' => [new Turnstile()]],
        [$key => 'Please prove you are human.'],
    );

    expect($validator->errors()->first('cf-turnstile-response'))
        ->toBe('Please prove you are human.')
        ->and($validator->failed())
        ->toBe(['cf-turnstile-response' => ['turnstile' => []]]);
})->with(['cf-turnstile-response.turnstile', 'cf-turnstile-response', 'turnstile']);

it('checks the expected action', function (?string $expectedAction, bool $fails): void {
    Http::fake([TurnstileClient::SITEVERIFY_URL => Http::response(['success' => true, 'action' => 'login'])]);

    $validator = turnstileValidator([
        'cf-turnstile-response' => 'XXXX.DUMMY.TOKEN.XXXX',
    ], new Turnstile(action: $expectedAction));

    expect($validator->fails())->toBe($fails);
})->with([
    'no action' => [null, false],
    'matching action' => ['login', false],
    'different action' => ['signup', true],
]);

it('dispatches an event with the response of each verification', function (
    array $body,
    ?string $action,
    VerifyResponse $response,
): void {
    $events = [];
    Event::listen(static function (TokenVerified $event) use (&$events): void {
        $events[] = $event;
    });
    Http::fake([TurnstileClient::SITEVERIFY_URL => Http::response($body)]);

    $_ = turnstileValidator([
        'cf-turnstile-response' => 'XXXX.DUMMY.TOKEN.XXXX',
    ], new Turnstile(action: $action))->fails();

    expect($events)->toEqual([new TokenVerified($response, $action)]);
})->with([
    'accepted' => [
        ['success' => true, 'action' => 'login'],
        'login',
        new VerifyResponse(success: true, action: 'login'),
    ],
    'rejected by Cloudflare' => [
        ['success' => false, 'error-codes' => ['timeout-or-duplicate']],
        null,
        new VerifyResponse(success: false, errorCodes: ['timeout-or-duplicate']),
    ],
    'different action' => [
        ['success' => true, 'action' => 'login'],
        'signup',
        new VerifyResponse(success: false, errorCodes: ['action-mismatch'], action: 'login'),
    ],
]);

it('sends the IP address of the request unless disabled', function (bool $config, ?bool $rule, bool $sent): void {
    config()->set('turnstile.send_remote_ip', $config);
    app()->instance('request', Request::create('/', 'POST', server: ['REMOTE_ADDR' => '203.0.113.1']));
    Http::fake([TurnstileClient::SITEVERIFY_URL => Http::response(['success' => true])]);

    $_ = turnstileValidator([
        'cf-turnstile-response' => 'XXXX.DUMMY.TOKEN.XXXX',
    ], new Turnstile(sendRemoteIp: $rule))->fails();

    $body = 'secret=1x0000000000000000000000000000000AA&response=XXXX.DUMMY.TOKEN.XXXX';
    Http::assertSent(
        static fn(HttpRequest $request): bool => $request->body() === ($sent ? $body . '&remoteip=203.0.113.1' : $body),
    );
})->with([
    'enabled in the configuration' => [true, null, true],
    'disabled in the configuration' => [false, null, false],
    'enabled in the rule' => [false, true, true],
    'disabled in the rule' => [true, false, false],
]);

it('fails without calling Cloudflare when the token is missing', function (): void {
    Http::fake();

    Event::fake([TokenVerified::class]);

    expect(turnstileValidator([])->fails())->toBeTrue();
    Http::assertNothingSent();
    Event::assertNotDispatched(TokenVerified::class);
});

it('fails without calling Cloudflare when the token is empty or not a string', function (
    #[SensitiveParameter]
    mixed $token,
): void {
    Http::fake();

    expect(turnstileValidator(['cf-turnstile-response' => $token])->fails())->toBeTrue();
    Http::assertNothingSent();
})->with([
    'null' => [null],
    'empty' => [''],
    'array' => [['XXXX.DUMMY.TOKEN.XXXX']],
]);

it('does not verify the token when another field fails', function (array $rules): void {
    Event::fake([TokenVerified::class]);
    Http::fake();

    $validator = validator(['cf-turnstile-response' => 'XXXX.DUMMY.TOKEN.XXXX'], $rules);

    expect($validator->fails())
        ->toBeTrue()
        ->and($validator->errors()->keys())
        ->toBe(['email'])
        ->and(app(TokenVerifications::class)->has('XXXX.DUMMY.TOKEN.XXXX'))
        ->toBeFalse();
    Http::assertNothingSent();
    Event::assertNotDispatched(TokenVerified::class);
})->with([
    'before the token' => [['email' => ['required'], 'cf-turnstile-response' => [new Turnstile()]]],
    'after the token' => [['cf-turnstile-response' => [new Turnstile()], 'email' => ['required']]],
]);

it('does not fail without a token when another field fails', function (): void {
    $validator = validator([], ['cf-turnstile-response' => [new Turnstile()], 'email' => ['required']]);

    expect($validator->errors()->keys())->toBe(['email']);
});

it('verifies the token when the other fields pass', function (): void {
    Http::fake([TurnstileClient::SITEVERIFY_URL => Http::response(['success' => true])]);

    $validator = validator(['cf-turnstile-response' => 'XXXX.DUMMY.TOKEN.XXXX', 'email' => 'john@example.com'], [
        'cf-turnstile-response' => [new Turnstile()],
        'email' => ['required'],
    ]);

    expect($validator->fails())->toBeFalse();
    Http::assertSentCount(1);
});

it('verifies the token once when the validator runs more than once', function (bool $success): void {
    Http::fake([TurnstileClient::SITEVERIFY_URL => Http::response(['success' => $success])]);

    $validator = turnstileValidator(['cf-turnstile-response' => 'XXXX.DUMMY.TOKEN.XXXX']);

    expect($validator->fails())
        ->toBe(!$success)
        ->and($validator->fails())
        ->toBe(!$success)
        ->and($validator->errors()->get('cf-turnstile-response'))
        ->toHaveCount($success ? 0 : 1);
    Http::assertSentCount(1);
})->with([true, false]);

it('reuses the result of a token verified earlier in the request', function (): void {
    Http::fake([TurnstileClient::SITEVERIFY_URL => Http::response(['success' => true])]);

    expect(turnstileValidator(['cf-turnstile-response' => 'XXXX.DUMMY.TOKEN.XXXX'])->fails())
        ->toBeFalse()
        ->and(turnstileValidator(['cf-turnstile-response' => 'XXXX.DUMMY.TOKEN.XXXX'])->fails())
        ->toBeFalse();
    Http::assertSentCount(1);
});

it('only validates the fields of the current validator', function (): void {
    Http::fake([
        TurnstileClient::SITEVERIFY_URL => Http::sequence()->push(['success' => false])->push(['success' => true]),
    ]);
    $rule = new Turnstile();

    $first = validator(['first' => 'XXXX.FIRST.TOKEN.XXXX'], ['first' => [$rule]]);
    $second = validator(['second' => 'XXXX.SECOND.TOKEN.XXXX'], ['second' => [$rule]]);

    expect($first->errors()->keys())->toBe(['first'])->and($second->errors()->keys())->toBe([]);
});

it('stops verifying tokens once one of them fails', function (): void {
    Http::fake([TurnstileClient::SITEVERIFY_URL => Http::response(['success' => false])]);

    $validator = validator(['captchas' => [
        'XXXX.FIRST.TOKEN.XXXX',
        'XXXX.SECOND.TOKEN.XXXX',
    ]], ['captchas.*' => [new Turnstile()]]);

    expect($validator->errors()->keys())->toBe(['captchas.0']);
    Http::assertSentCount(1);
});

it('verifies the token when used outside a validator', function (): void {
    Http::fake([TurnstileClient::SITEVERIFY_URL => Http::response(['success' => false])]);
    $failed = false;

    new Turnstile()->validate('captcha', 'XXXX.DUMMY.TOKEN.XXXX', static function () use (
        &$failed,
    ): PotentiallyTranslatedString {
        $failed = true;

        return new PotentiallyTranslatedString('turnstile::validation.failed', app(Translator::class));
    });

    expect($failed)->toBeTrue();
    Http::assertSentCount(1);
});

it('fails and reports the exception when Cloudflare cannot be reached', function (): void {
    Exceptions::fake();
    Event::fake([TokenVerified::class]);
    Http::fake([TurnstileClient::SITEVERIFY_URL => Http::failedConnection()]);

    expect(turnstileValidator(['cf-turnstile-response' => 'XXXX.DUMMY.TOKEN.XXXX'])->fails())->toBeTrue();
    Exceptions::assertReported(HttpException::class);
    Event::assertNotDispatched(TokenVerified::class);
});
