<div
    wire:ignore
    class="afmc-altcha"
    x-data="{
        init() {
            const widget = this.$refs.widget;
            if (! widget) {
                return;
            }

            widget.addEventListener('verified', (event) => {
                $wire.set('altcha', event.detail.payload);
            });

            widget.addEventListener('statechange', (event) => {
                if (event.detail.state !== 'verified') {
                    $wire.set('altcha', '');
                }
            });
        }
    }"
>
    <altcha-widget
        x-ref="widget"
        challenge="{{ route('altcha.challenge') }}"
        floating
    ></altcha-widget>
    @error('altcha')
        <span class="afmc-altcha__error">{{ $message }}</span>
    @enderror
</div>
