@php
    $company = config('company');
@endphp

<p>{{ __('Last updated: :date', ['date' => $company['policies_updated_at']]) }}</p>

<p>{{ __('We want :product to work for people who use a keyboard, a screen reader, magnification, or a small screen. We aim at WCAG 2.2 level AA and treat the accessibility requirements of Directive (EU) 2019/882 as our standard, in the spirit of the European Accessibility Act rather than as a claim of full conformance.', [
    'product' => $company['product_name'],
]) }}</p>

<h2>{{ __('What we do') }}</h2>
<ul>
    <li>{{ __('Pages are built from headings, lists, and tables that describe their own structure, so a screen reader can move through them.') }}</li>
    <li>{{ __('Every control can be reached and operated with a keyboard, and the focused element stays visible.') }}</li>
    <li>{{ __('Icons are decorative and hidden from screen readers, and the buttons around them carry text labels.') }}</li>
    <li>{{ __('Text and interface colours are checked for contrast in both the light and the dark theme.') }}</li>
    <li>{{ __('Figures use tabular numbers so columns line up, and colour is never the only way we show a price direction.') }}</li>
</ul>

<h2>{{ __('Known limitations') }}</h2>
<ul>
    <li>{{ __('Below 700 pixels wide, ranked coin tables become a list of rows that open in place, and the sort control moves out of the column headers into a select above the list. The figures are the same; the columns are stacked instead of side by side. Wider than that, a table pans sideways with the asset name held still.') }}</li>
    <li>{{ __('Charts and sparklines are visual summaries. The figures behind them are always available as text in the tables on the same page.') }}</li>
    <li>{{ __('We have not commissioned an external audit yet, so this statement rests on our own testing.') }}</li>
</ul>

<h2>{{ __('Tell us about a barrier') }}</h2>
<p>{{ __('Write to :email with the page you were on, what you were trying to do, and the browser or assistive technology you use. We aim to reply within five business days and to give you the information you needed in another form while we fix the cause.', [
    'email' => $company['contact_email'],
]) }}</p>
<p>{{ __('If our answer does not help you, the Complaints page explains how to escalate.') }}
    <a href="{{ route('legal.show', 'complaints') }}" wire:navigate>{{ __('Read the Complaints page') }}</a>
</p>
