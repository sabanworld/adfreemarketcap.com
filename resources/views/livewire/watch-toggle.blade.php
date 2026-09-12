<button
    type="button"
    class="afmc-icon-btn"
    wire:click="toggle"
    wire:loading.attr="disabled"
    aria-pressed="{{ $watched ? 'true' : 'false' }}"
    aria-label="{{ $watched ? __('Remove from watchlist') : __('Add to watchlist') }}"
    title="{{ auth()->check() ? ($watched ? __('Remove from watchlist') : __('Add to watchlist')) : __('Sign in to use watchlist') }}"
>
    <x-afmc.icon
        name="star"
        :filled="$watched"
        size="18px"
        :color="$watched ? 'var(--amber-500)' : 'var(--text-faint)'"
    />
</button>
