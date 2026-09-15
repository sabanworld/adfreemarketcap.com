@if (config('analytics.enabled'))
    @php
        $settings = [
            'domain' => config('analytics.domain'),
            'endpoint' => config('analytics.endpoint'),
            'collectDnt' => (bool) config('analytics.collect_dnt'),
            'capture' => [
                'outboundLinks' => (bool) config('analytics.capture.outbound_links'),
                'fileDownloads' => (bool) config('analytics.capture.file_downloads'),
                'formSubmissions' => (bool) config('analytics.capture.form_submissions'),
            ],
        ];
    @endphp
    {{-- Cookieless visitor counter. The tracker is bundled from the
         @plausible-analytics/tracker package, so no file is fetched from
         Plausible and these settings reach it without inline script.
         Disclosed in the privacy and cookie policies. --}}
    <script type="application/json" id="afmc-analytics">@json($settings)</script>
    @vite('resources/js/analytics.js')
@endif
