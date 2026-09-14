@if ($paginator->hasPages())
    <nav role="navigation" aria-label="{{ __('Page navigation') }}" class="afmc-pagination__nav">
        {{-- An arrow with nowhere to go is a disabled button, not a span: a span cannot be
             focused, so the reason never reaches a screen reader, and it would sit outside the
             coarse-pointer rule that lifts controls to a 44px target. --}}
        @if ($paginator->onFirstPage())
            <button type="button" class="afmc-pagination__link" disabled aria-label="{{ __('Previous page') }}">
                <x-afmc.icon name="chevron_left" size="16px" />
            </button>
        @else
            <button
                type="button"
                class="afmc-pagination__link"
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
                <span class="afmc-pagination__gap" aria-hidden="true">{{ $element }}</span>
            @endif

            @if (is_array($element))
                @foreach ($element as $page => $url)
                    @if ($page == $paginator->currentPage())
                        <span class="afmc-pagination__link is-current" aria-current="page">{{ $page }}</span>
                    @else
                        <button
                            type="button"
                            class="afmc-pagination__link afmc-pagination__link--page"
                            wire:click="gotoPage({{ (int) $page }})"
                            wire:loading.attr="disabled"
                            aria-label="{{ __('Go to page :page', ['page' => $page]) }}"
                        >{{ $page }}</button>
                    @endif
                @endforeach
            @endif
        @endforeach

        {{-- A phone has room for the arrows or for the jump targets, not both. Below 560px the
             numbered cells give way and this takes their place, so the reader still knows where
             they are. Both renderings are in the markup, because which one applies is a
             viewport question and CSS is what answers it. --}}
        <span class="afmc-pagination__position" aria-hidden="true">
            {{ $paginator->currentPage() }} / {{ $paginator->lastPage() }}
        </span>

        @if ($paginator->hasMorePages())
            <button
                type="button"
                class="afmc-pagination__link"
                wire:click="nextPage"
                wire:loading.attr="disabled"
                rel="next"
                aria-label="{{ __('Next page') }}"
            >
                <x-afmc.icon name="chevron_right" size="16px" />
            </button>
        @else
            <button type="button" class="afmc-pagination__link" disabled aria-label="{{ __('Next page') }}">
                <x-afmc.icon name="chevron_right" size="16px" />
            </button>
        @endif
    </nav>
@endif
