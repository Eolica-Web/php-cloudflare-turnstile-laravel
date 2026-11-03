# Cloudflare Turnstile for Laravel

Laravel integration of [eolica/cloudflare-turnstile](https://github.com/Eolica-Web/php-cloudflare-turnstile-core).

## Requirements

- PHP 8.5 or higher
- Laravel 13
- Livewire 3 or 4, optional, for the [Livewire integration](#livewire)

## Installation

Install via [Composer](https://getcomposer.org):

```bash
composer require eolica/laravel-cloudflare-turnstile
```

The service provider is registered automatically.

This package always requires the same version of [eolica/cloudflare-turnstile](https://github.com/Eolica-Web/php-cloudflare-turnstile-core), so update both together with `--with-dependencies`:

```bash
composer update eolica/laravel-cloudflare-turnstile --with-dependencies

# Or, to a version outside your current constraint:
composer require eolica/laravel-cloudflare-turnstile:^0.3 --with-dependencies
```

## Configuration

Add your site key and secret key to the `.env` file:

```dotenv
TURNSTILE_SITE_KEY=your-site-key
TURNSTILE_SECRET_KEY=your-secret-key
```

Optionally, set the hostname the tokens must be issued for:

```dotenv
TURNSTILE_HOSTNAME=example.com
```

To customize the configuration, publish the configuration file:

```bash
php artisan vendor:publish --tag=turnstile-config
```

## Rendering the widget

Include Cloudflare's script once in your layout, for example in the `<head>`:

```blade
<head>
    <x-turnstile::script />
</head>
```

It also adds a `preconnect` hint so the browser connects to Cloudflare early. Any attribute, such as a `nonce`, is added to the `<script>` tag. Include it in the layout itself, not inside a Livewire component: the scripts Livewire adds to the page when it updates a component don't run.

Then add the widget inside your form:

```blade
<form method="POST" action="/contact">
    @csrf

    <x-turnstile::widget />

    <button type="submit">Send</button>
</form>
```

The widget adds the token to the form in the `cf-turnstile-response` field.

Configure the widget with any of these attributes:

```blade
<x-turnstile::widget
    action="contact"
    cdata="campaign-42"
    theme="dark"
    size="flexible"
    language="es"
    appearance="interaction-only"
/>
```

See [Cloudflare's widget configuration](https://developers.cloudflare.com/turnstile/get-started/client-side-rendering/widget-configurations/) for the values each one accepts. Any other attribute, such as `class` or `data-callback`, is added to the widget's container.

The widget is shown in your application's locale unless you set a `language`, and in English if Cloudflare doesn't [support that language](https://developers.cloudflare.com/turnstile/reference/supported-languages/). Use `language="auto"` to show it in the visitor's browser language instead.

### Livewire

Bind the widget to a property of your component with `wire:model`:

```blade
<form wire:submit="send">
    <x-turnstile::widget wire:model="captcha" />

    <button type="submit">Send</button>
</form>
```

```php
use Eolica\LaravelCloudflareTurnstile\Rules\Turnstile;
use Livewire\Component;

class Contact extends Component
{
    public string $email = '';

    public ?string $captcha = null;

    public function send(): void
    {
        $this->validate([
            'email' => ['required', 'email'],
            'captcha' => [new Turnstile()],
        ]);
    }
}
```

The widget works with `wire:navigate` and in components rendered after the page loads. It also works with form objects. If the page doesn't include Cloudflare's script, the widget logs an error in the browser's console.

A token can only be verified once, so the widget is reset after Cloudflare verifies its token, whether it passes or fails. The property returns to its initial value and the visitor gets a new token. You don't need to do anything for this.

If another field fails, the token isn't verified, so the visitor keeps it and can submit the form again without solving a new challenge.

### seophp/head

If your application uses [seophp/head-laravel](https://github.com/seo-php/head-laravel), add Cloudflare's script and the `preconnect` hint to the `<head>` it renders with the `TurnstilePlugin`, instead of the `script` component:

```php
use Eolica\LaravelCloudflareTurnstile\Head\TurnstilePlugin;

protected function configure(Head $head): void
{
    $head->plugins([
        TurnstilePlugin::make(),
    ]);
}
```

## Validation

Validate the token the widget adds to your form with the `Turnstile` rule:

```php
use Eolica\LaravelCloudflareTurnstile\Rules\Turnstile;

$request->validate([
    'cf-turnstile-response' => [new Turnstile()],
]);
```

The token is only checked once every other field passes, wherever the rule is placed. When another field fails, the rule doesn't send the token to Cloudflare and doesn't add an error, as the validation fails anyway. This avoids a request and keeps the token unused.

The rule fails when the token is missing, so it doesn't need the `required` rule.

It also sends the visitor's IP address to Cloudflare, taken from `$request->ip()`. If your application runs behind a proxy, such as Cloudflare itself or a load balancer, [configure the trusted proxies](https://laravel.com/docs/requests#configuring-trusted-proxies) so the IP address is the visitor's and not the proxy's. To stop sending it:

```dotenv
TURNSTILE_SEND_REMOTE_IP=false
```

Or override the configuration for a single rule:

```php
new Turnstile(sendRemoteIp: false)
```

Pass the action configured in the widget to check that the token was issued for it:

```php
new Turnstile(action: 'login')
```

If Cloudflare can't be reached, the validation fails and the exception is reported to your exception handler.

To customize the error message or translate it, publish the translations:

```bash
php artisan vendor:publish --tag=turnstile-translations
```

Or set a custom message for a single form with the `turnstile` key, as with any other rule:

```php
$request->validate(
    ['cf-turnstile-response' => [new Turnstile()]],
    ['cf-turnstile-response.turnstile' => 'Please confirm you are human.'],
);
```

### Events

The rule dispatches a `TokenVerified` event with Cloudflare's response after each verification, whether it passes or fails. The visitor only sees a generic error, so listen to it to find out why a token was rejected:

```php
use Eolica\LaravelCloudflareTurnstile\Events\TokenVerified;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Log;

Event::listen(function (TokenVerified $event): void {
    if (! $event->response->success) {
        Log::debug('Turnstile rejected a token.', [
            'action' => $event->action,
            'errors' => $event->response->errorCodes,
        ]);
    }
});
```

`$event->response` is the [`VerifyResponse`](https://github.com/Eolica-Web/php-cloudflare-turnstile-core#the-response) returned by the client and `$event->action` the action passed to the rule. The event is not dispatched when the field is missing or isn't a string, when another field fails, or when Cloudflare can't be reached and the exception is reported instead.

## Using the client directly

`Eolica\CloudflareTurnstile\TurnstileClient` is registered in the container, configured with your secret key and expected hostname. Inject it wherever you need it:

```php
use Eolica\CloudflareTurnstile\TurnstileClient;

public function store(Request $request, TurnstileClient $turnstile)
{
    $response = $turnstile
        ->withExpectedAction('login')
        ->verify($request->input('cf-turnstile-response', ''), remoteIp: $request->ip());

    if (!$response->success) {
        // Reject the request
    }
}
```

See the [core package documentation](https://github.com/Eolica-Web/php-cloudflare-turnstile-core) for everything `TurnstileClient` offers.

### HTTP client

Requests to Cloudflare are sent through Laravel's HTTP client, so they fire its events, show up in tools like Telescope or Nightwatch, and use its global configuration. For example, to set a timeout for every outgoing request:

```php
use Illuminate\Support\Facades\Http;

Http::globalOptions(['timeout' => 5]);
```

## Testing

Fake Cloudflare's responses with `Http::fake()`:

```php
use Eolica\CloudflareTurnstile\TurnstileClient;
use Illuminate\Support\Facades\Http;

Http::fake([
    TurnstileClient::SITEVERIFY_URL => Http::response(['success' => true, 'action' => 'contact']),
]);
```

The rule compares the response with the expected values, so include the `action` you pass to the rule, and the `hostname` if you set `TURNSTILE_HOSTNAME`. Otherwise the rule fails.

`Http::preventStrayRequests()` also prevents real requests to Cloudflare.

To try the widget locally, use Cloudflare's [testing keys](https://developers.cloudflare.com/turnstile/troubleshooting/testing/), such as the site key `1x00000000000000000000AA` and the secret key `1x0000000000000000000000000000000AA`, which always pass. The hostname and the action are not checked with the test secret keys, so a rule with an `action` passes too.

## License

The MIT License (MIT). See the [license file](LICENSE) for more information.
