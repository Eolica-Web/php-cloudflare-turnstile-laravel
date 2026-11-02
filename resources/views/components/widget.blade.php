@if ($attributes->whereStartsWith('wire:model')->isNotEmpty())
    <div
        wire:ignore
        x-data="{
            token: null,
            widgetId: null,
            solved: false,
            init() {
                this.$watch('token', (token) => {
                    if (token || !this.solved) {
                        return;
                    }

                    this.solved = false;
                    window.turnstile.reset(this.widgetId);
                });

                const render = () => this.widgetId = window.turnstile.render(this.$el, {
                    ...@js($options),
                    'response-field': false,
                    callback: (token) => {
                        this.solved = true;
                        this.token = token;
                    },
                    'expired-callback': () => {
                        this.solved = false;
                        this.token = null;
                    },
                });

                if (window.turnstile) {
                    render();

                    return;
                }

                const url = @js(\Eolica\LaravelCloudflareTurnstile\View\Components\Script::URL);
                const script = document.querySelector(`script[src=&quot;${url}&quot;]`);

                if (!script) {
                    console.error('The Cloudflare Turnstile script is missing. Add &lt;x-turnstile::script /&gt; to your layout.');

                    return;
                }

                script.addEventListener('load', render);
            },
        }"
        x-modelable="token"
        {{ $attributes }}
    ></div>
@else
    <div {{ $attributes->class('cf-turnstile')->merge($dataAttributes()) }}></div>
@endif
