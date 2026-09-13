@props([
    'address' => null,
])

@php
    $address = is_string($address) ? trim($address) : trim((string) config('company.donation.btc_address', ''));
@endphp

@if ($address !== '')
    @php
        $qr = \App\Support\QrSvg::make('bitcoin:'.$address, 112);
        $copyLabel = __('Copy Bitcoin address');
        $copiedLabel = __('Copied');
    @endphp

    <div
        data-afmc-btc-donate
        data-btc-address="{{ $address }}"
        data-btc-qr-payload="bitcoin:{{ $address }}"
        class="afmc-btc-donate"
        x-data="{
            address: @js($address),
            copied: false,
            copy() {
                const done = () => {
                    this.copied = true
                    clearTimeout(this._t)
                    this._t = setTimeout(() => { this.copied = false }, 2000)
                }
                if (navigator.clipboard?.writeText) {
                    navigator.clipboard.writeText(this.address).then(done).catch(() => {})
                    return
                }
                const input = document.createElement('input')
                input.value = this.address
                document.body.appendChild(input)
                input.select()
                try { document.execCommand('copy'); done() } catch (e) {}
                input.remove()
            }
        }"
    >
        <div class="afmc-btc-donate__qr" aria-hidden="true">{!! $qr !!}</div>
        <div class="afmc-btc-donate__body">
            <span class="afmc-footer__col-title">{{ __('Donate BTC') }}</span>
            <p class="afmc-btc-donate__hint">{{ __('Help keep the site ad-free.') }}</p>
            <code class="afmc-btc-donate__address" title="{{ $address }}">{{ $address }}</code>
            <button
                type="button"
                class="afmc-btc-donate__copy"
                @click="copy()"
                :aria-label="copied ? @js($copiedLabel) : @js($copyLabel)"
                :title="copied ? @js($copiedLabel) : @js($copyLabel)"
            >
                <span class="afmc-btc-donate__copy-icon" aria-hidden="true">
                    <span x-show="!copied" x-cloak><x-afmc.icon name="content_copy" size="15px" /></span>
                    <span x-show="copied" x-cloak><x-afmc.icon name="check" size="15px" color="var(--text-up)" /></span>
                </span>
                <span class="afmc-btc-donate__copy-text">
                    <span x-show="!copied" x-cloak>{{ __('Copy') }}</span>
                    <span x-show="copied" x-cloak>{{ __('Copied') }}</span>
                </span>
            </button>
        </div>
    </div>
@endif
