@if (config('analytics.enabled'))
    {{-- Cookieless visitor counter. Disclosed in the privacy and cookie policies. --}}
    <script async src="{{ config('analytics.script_url') }}"@if (config('analytics.collect_dnt')) data-collect-dnt="true"@endif></script>
    <noscript><img src="{{ config('analytics.noscript_url') }}" alt="" referrerpolicy="no-referrer-when-downgrade"></noscript>
@endif
