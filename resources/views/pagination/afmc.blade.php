@if ($paginator->hasPages())
    <nav role="navigation" aria-label="{{ __('Page navigation') }}">
        @if ($paginator->onFirstPage())
            <span aria-disabled="true" style="opacity:.45">
                <x-afmc.icon name="chevron_left" size="16px" />
            </span>
        @else
            <button
                type="button"
                wire:click="previousPage"
                wire:loading.attr="disabled"
                rel="prev"
                aria-label="{{ __('Previous page') }}"
            >
                <x-afmc.icon name="chevron_left" size="16px" />
            </button>
        @endif

        @foreach ($elements as $element)
            @if (is_string($element))
                <span aria-disabled="true">{{ $element }}</span>
            @endif

            @if (is_array($element))
                @foreach ($element as $page => $url)
                    @if ($page == $paginator->currentPage())
                        <span aria-current="page">{{ $page }}</span>
                    @else
                        <button type="button" wire:click="gotoPage({{ (int) $page }})">{{ $page }}</button>
                    @endif
                @endforeach
            @endif
        @endforeach

        @if ($paginator->hasMorePages())
            <button
                type="button"
                wire:click="nextPage"
                wire:loading.attr="disabled"
                rel="next"
                aria-label="{{ __('Next page') }}"
            >
                <x-afmc.icon name="chevron_right" size="16px" />
            </button>
        @else
            <span aria-disabled="true" style="opacity:.45">
                <x-afmc.icon name="chevron_right" size="16px" />
            </span>
        @endif
    </nav>
@endif
