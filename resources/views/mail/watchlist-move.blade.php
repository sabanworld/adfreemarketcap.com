@php
    use App\Services\Watchlist\WatchlistMailFigures;

    $headline = count($alerts) === 1
        ? $alerts[0]->coin->name
        : trans_choice(':count coin on your watchlist moved|:count coins on your watchlist moved', count($alerts), ['count' => count($alerts)]);
@endphp

<x-mail.layout :title="$subjectLine" :preheader="$subjectLine">
    <p class="afmc-ink-muted" style="margin:0 0 4px;font-size:12px;font-weight:600;letter-spacing:0.08em;text-transform:uppercase;color:#666A5C">{{ __('Watchlist alert') }}</p>
    <h1 class="afmc-ink-strong" style="margin:0 0 12px;font-family:Archivo,'Helvetica Neue',Helvetica,Arial,sans-serif;font-size:24px;line-height:1.2;font-weight:800;letter-spacing:-0.02em;color:#0E0F0C">{{ $headline }}</h1>
    <p class="afmc-ink" style="margin:0 0 20px;color:#1F211C">{{ __('Hello :name, prices are in US dollars and come from the last sync, not a live feed.', ['name' => $user->name]) }}</p>

    @foreach ($alerts as $alert)
        <table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%" class="afmc-panel" style="border-collapse:collapse;margin:0 0 16px;background:#FFFFFF;border:1px solid #C2C7BA;border-radius:10px">
            <tr>
                <td style="padding:16px 18px">
                    <table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%" style="border-collapse:collapse">
                        <tr>
                            <td style="font-family:'Public Sans',-apple-system,BlinkMacSystemFont,'Segoe UI',Helvetica,Arial,sans-serif">
                                <a href="{{ route('coins.show', $alert->coin) }}" class="afmc-ink-strong" style="font-size:17px;font-weight:700;color:#0E0F0C;text-decoration:none">{{ $alert->coin->name }}</a>
                                <span class="afmc-ink-muted" style="font-size:13px;color:#666A5C"> {{ strtoupper((string) $alert->coin->symbol) }}</span>
                            </td>
                            <td align="right" class="afmc-ink-strong" style="font-family:'JetBrains Mono',ui-monospace,SFMono-Regular,Menlo,Consolas,monospace;font-size:17px;font-weight:600;color:#0E0F0C;white-space:nowrap">{{ WatchlistMailFigures::price($alert->coin->price) }}</td>
                        </tr>
                    </table>

                    <table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%" class="afmc-rule" style="border-collapse:collapse;margin-top:14px;border-top:1px solid #E2E5DD">
                        @foreach ($alert->lines as $line)
                            <tr>
                                <td style="padding:10px 0 0;font-family:'Public Sans',-apple-system,BlinkMacSystemFont,'Segoe UI',Helvetica,Arial,sans-serif;font-size:14px;color:#1F211C" class="afmc-ink">
                                    <x-mail.change :value="$line->percent" />
                                    <span style="padding-left:6px">{{ __('over :window, past the :band% mark', ['window' => $line->windowLabel, 'band' => $line->band]) }}</span>
                                </td>
                            </tr>
                        @endforeach
                    </table>
                </td>
            </tr>
        </table>
    @endforeach

    <p style="margin:20px 0 20px">
        <x-mail.button :href="route('watchlist')">{{ __('Open your watchlist') }}</x-mail.button>
    </p>

    <p class="afmc-ink-muted" style="margin:0;font-size:13px;line-height:1.6;color:#666A5C">{{ __('Marks start at 5% and step every 5%. One email per coin an hour, naming the highest mark it passed.') }}</p>
</x-mail.layout>
