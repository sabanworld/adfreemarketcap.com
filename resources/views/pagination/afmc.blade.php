@if ($paginator->hasPages())
    <nav role="navigation" aria-label="{{ __('Page navigation') }}">
        @if ($paginator->onFirstPage())
            <span aria-disabled="true" style="opacity:.45">
                <span class="afmc-icon" style="font-size:16px">chevron_left</span>
            </span>
        @else
            <button
                type="button"
                wire:click="previousPage"
                wire:loading.attr="disabled"
                rel="prev"
                aria-label="{{ __('Previous page') }}"
            >
                <span class="afmc-icon" style="font-size:16px">chevron_left</span>
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
                <span class="afmc-icon" style="font-size:16px">chevron_right</span>
            </button>
        @else
            <span aria-disabled="true" style="opacity:.45">
                <span class="afmc-icon" style="font-size:16px">chevron_right</span>
            </span>
        @endif
    </nav>
@endif
