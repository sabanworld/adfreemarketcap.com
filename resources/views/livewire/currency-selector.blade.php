@php
    use App\Services\Currency\DTOs\CurrencyUnit;

    $hint = $active->code === 'usd'
        ? __('Market data is stored in US dollars.')
        : ($ratesSyncedAt
            ? __('Converted from USD at rates synced :time.', ['time' => $ratesSyncedAt->diffForHumans()])
            : __('Converted from USD at the latest synced rate.'));

    $groups = [];

    if ($fiatUnits->isNotEmpty()) {
        $groups[] = ['label' => __('Fiat'), 'units' => $fiatUnits];
    }

    if ($cryptoUnits->isNotEmpty()) {
        $groups[] = ['label' => __('Crypto'), 'units' => $cryptoUnits];
    }

    $all = $fiatUnits->concat($cryptoUnits);

    // A short row of the codes most people want, so the common case is one tap.
    $quick = collect(['usd', 'eur', 'gbp', 'btc'])
        ->map(fn (string $code): ?CurrencyUnit => $all->firstWhere('code', $code))
        ->filter()
        ->values();

    $isRow = $variant === 'row';
    $hasChoice = count($groups) > 0 && $all->count() > 1;
@endphp

<div
    class="afmc-currency {{ $isRow ? 'afmc-currency--row' : '' }}"
    x-data="{
        open: false,
        query: '',
        narrow: window.matchMedia('(max-width: 700px)').matches,
        init() {
            const mq = window.matchMedia('(max-width: 700px)');
            const sync = () => { this.narrow = mq.matches };
            mq.addEventListener ? mq.addEventListener('change', sync) : mq.addListener(sync);
            window.addEventListener('resize', sync);
            window.addEventListener('orientationchange', sync);
        },
        get filtering() {
            return this.query.trim() !== '';
        },
        get empty() {
            return this.filtering
                && this.$refs.list
                && this.$refs.list.querySelectorAll('[data-currency]:not([hidden])').length === 0;
        },
        matches(haystack) {
            return ! this.filtering || haystack.includes(this.query.trim().toLowerCase());
        },
        toggle() {
            this.open = ! this.open;
            this.query = '';

            if (this.open && ! this.narrow) {
                this.$nextTick(() => this.$refs.filter?.focus());
            }
        },
        pick(code) {
            this.open = false;
            $wire.set('currency', code);
        },
    }"
    @keydown.escape.window="if (open) { open = false }"
>
    @if ($hasChoice)
        @if ($isRow)
            <button
                type="button"
                class="afmc-currency__row-trigger"
                @click="toggle()"
                :aria-expanded="open"
                aria-haspopup="dialog"
            >
                <x-afmc.icon name="payments" size="20px" color="var(--text-muted)" />
                <span class="afmc-currency__row-label">{{ __('Currency') }}</span>
                <span class="afmc-currency__row-value">{{ $active->displayCode() }}</span>
                <x-afmc.icon name="chevron_right" size="18px" color="var(--text-faint)" />
            </button>
        @else
            <button
                type="button"
                data-afmc-curchip
                class="afmc-currency__trigger"
                @click="toggle()"
                :aria-expanded="open"
                aria-haspopup="dialog"
                aria-label="{{ __('Display currency') }}: {{ $active->displayCode() }}"
                title="{{ $hint }}"
            >
                {{ $active->displayCode() }}
                <x-afmc.icon name="expand_more" size="16px" color="var(--text-faint)" />
            </button>
        @endif

        {{--
            Teleported to the body: an overlay that has to cover the viewport must not be
            rendered inside a sticky bar, where an ancestor filter or transform would make
            itself the containing block for position:fixed and pin the sheet to that bar.
        --}}
        <template x-teleport="body">
            <div x-show="open && narrow" x-cloak x-effect="document.body.style.overflow = (open && narrow) ? 'hidden' : ''">
                <div class="afmc-currency__scrim" @click="open = false" x-transition.opacity></div>

                <div class="afmc-currency__sheet" role="dialog" aria-modal="true" aria-label="{{ __('Currency') }}">
                    <div class="afmc-currency__sheet-head" style="padding:var(--space-3) var(--space-4) 0">
                        <span class="afmc-currency__sheet-title">{{ __('Currency') }}</span>
                        <button type="button" class="afmc-icon-btn afmc-icon-btn--lg" @click="open = false" aria-label="{{ __('Close') }}">
                            <x-afmc.icon name="close" size="22px" />
                        </button>
                    </div>

                    <x-afmc.currency-list :groups="$groups" :quick="$quick" :active="$active" check-size="20px" />
                </div>
            </div>
        </template>

        <div class="afmc-currency__popover" x-show="open && ! narrow" x-cloak @click.outside="open = false">
            <x-afmc.currency-list :groups="$groups" :quick="$quick" :active="$active" />
        </div>
    @else
        <span class="afmc-currency__label">{{ $active->displayCode() }}</span>
    @endif
</div>
