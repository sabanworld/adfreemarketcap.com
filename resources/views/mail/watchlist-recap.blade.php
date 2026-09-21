@php
    use App\Services\Watchlist\WatchlistMailFigures;

    $columnWidth = intdiv(100, max(1, count($windows))) . '%';
@endphp

<x-mail.layout :title="$subjectLine" :preheader="trans_choice(':count coin on your watchlist.|:count coins on your watchlist.', $coins->count(), ['count' => $coins->count()])">
    <p class="afmc-ink-muted" style="margin:0 0 4px;font-size:12px;font-weight:600;letter-spacing:0.08em;text-transform:uppercase;color:#666A5C">{{ $heading }}</p>
    <h1 class="afmc-ink-strong" style="margin:0 0 12px;font-family:Archivo,'Helvetica Neue',Helvetica,Arial,sans-serif;font-size:24px;line-height:1.2;font-weight:800;letter-spacing:-0.02em;color:#0E0F0C">{{ $sentOn }}</h1>
    <p class="afmc-ink" style="margin:0 0 20px;color:#1F211C">{{ __('Hello :name, here is every coin on your watchlist, moved or not. Prices are in US dollars, as of :time.', ['name' => $user->name, 'time' => $sentAt]) }}</p>

    @foreach ($coins as $coin)
        <table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%" class="afmc-panel" style="border-collapse:collapse;margin:0 0 12px;background:#FFFFFF;border:1px solid #C2C7BA;border-radius:10px">
            <tr>
                <td style="padding:14px 18px">
                    <table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%" style="border-collapse:collapse">
                        <tr>
                            <td style="font-family:'Public Sans',-apple-system,BlinkMacSystemFont,'Segoe UI',Helvetica,Arial,sans-serif">
                                <a href="{{ route('coins.show', $coin) }}" class="afmc-ink-strong" style="font-size:16px;font-weight:700;color:#0E0F0C;text-decoration:none">{{ $coin->name }}</a>
                                <span class="afmc-ink-muted" style="font-size:13px;color:#666A5C"> {{ strtoupper((string) $coin->symbol) }}</span>
                            </td>
                            <td align="right" class="afmc-ink-strong" style="font-family:'JetBrains Mono',ui-monospace,SFMono-Regular,Menlo,Consolas,monospace;font-size:16px;font-weight:600;color:#0E0F0C;white-space:nowrap">{{ WatchlistMailFigures::price($coin->price) }}</td>
                        </tr>
                    </table>

                    <table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%" class="afmc-rule" style="border-collapse:collapse;margin-top:12px;border-top:1px solid #E2E5DD">
                        <tr>
                            @foreach ($windows as $key => $label)
                                <td width="{{ $columnWidth }}" style="padding:10px 8px 0 0;font-family:'Public Sans',-apple-system,BlinkMacSystemFont,'Segoe UI',Helvetica,Arial,sans-serif">
                                    <div class="afmc-ink-muted" style="font-size:11px;letter-spacing:0.06em;text-transform:uppercase;color:#666A5C;padding-bottom:4px">{{ $label }}</div>
                                    <x-mail.change :value="$coin->{'percent_change_' . $key}" />
                                </td>
                            @endforeach
                        </tr>
                    </table>
                </td>
            </tr>
        </table>
    @endforeach

    <p style="margin:20px 0 20px">
        <x-mail.button :href="route('watchlist')">{{ __('Open your watchlist') }}</x-mail.button>
    </p>

    <p class="afmc-ink-muted" style="margin:0;font-size:13px;line-height:1.6;color:#666A5C">{{ $closing }}</p>
</x-mail.layout>
