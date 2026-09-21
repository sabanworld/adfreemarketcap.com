@props([
    'title' => null,
    'preheader' => null,
])

@php
    $company = config('company');
@endphp

<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="color-scheme" content="light dark">
    <meta name="supported-color-schemes" content="light dark">
    <title>{{ $title ?: config('app.name') }}</title>
    {{-- No @font-face and no remote image: Archivo, Public Sans, and JetBrains Mono are named
         for the readers who already have them, and everyone else gets the system stack. A
         font or logo fetched from a server would report back when the email was opened. --}}
    <style>
        @media (prefers-color-scheme: dark) {
            .afmc-page { background: #000000 !important; }
            .afmc-card { background: #0B0C0A !important; border-color: #2A2C26 !important; }
            /* One step above the card, because the light theme's near-black band and an OLED
               card are the same colour to the eye and the header stops reading as a band. */
            .afmc-band { background: #141512 !important; border-color: #2A2C26 !important; }
            .afmc-ink { color: #F0F0EA !important; }
            .afmc-ink-strong { color: #FFFFFF !important; }
            .afmc-ink-muted { color: #9EA296 !important; }
            .afmc-rule { border-color: #2A2C26 !important; }
            .afmc-panel { background: #141512 !important; border-color: #2A2C26 !important; }
        }
    </style>
</head>
<body class="afmc-page" style="margin:0;padding:0;width:100%;background:#F5F6F2;-webkit-font-smoothing:antialiased">
    @if (filled($preheader))
        <div style="display:none;max-height:0;overflow:hidden;opacity:0;color:transparent;height:0;width:0">{{ $preheader }}</div>
    @endif

    <table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%" class="afmc-page" style="border-collapse:collapse;background:#F5F6F2">
        <tr>
            <td align="center" style="padding:24px 12px">
                <table role="presentation" cellpadding="0" cellspacing="0" border="0" width="600" style="border-collapse:collapse;width:100%;max-width:600px">
                    <tr>
                        {{-- The band carries the same side rule as the cells under it, or the
                             card outline starts halfway down and steps in at the seam. --}}
                        <td bgcolor="#0E0F0C" class="afmc-band" style="padding:20px 24px;background:#0E0F0C;border:1px solid #C2C7BA;border-bottom:none;border-radius:12px 12px 0 0">
                            <x-mail.brand tone="paper" :height="20" />
                        </td>
                    </tr>
                    <tr>
                        <td class="afmc-card" style="padding:28px 24px 24px;background:#FFFFFF;border-left:1px solid #C2C7BA;border-right:1px solid #C2C7BA;font-family:'Public Sans',-apple-system,BlinkMacSystemFont,'Segoe UI',Helvetica,Arial,sans-serif;font-size:16px;line-height:1.55;color:#1F211C">
                            {{ $slot }}
                        </td>
                    </tr>
                    <tr>
                        <td class="afmc-card" style="padding:0 24px 24px;background:#FFFFFF;border-left:1px solid #C2C7BA;border-right:1px solid #C2C7BA;border-bottom:1px solid #C2C7BA;border-radius:0 0 12px 12px">
                            <table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%" style="border-collapse:collapse">
                                <tr>
                                    <td class="afmc-rule" style="padding-top:20px;border-top:1px solid #E2E5DD;font-family:'Public Sans',-apple-system,BlinkMacSystemFont,'Segoe UI',Helvetica,Arial,sans-serif;font-size:13px;line-height:1.6;color:#666A5C">
                                        <p class="afmc-ink-muted" style="margin:0 0 6px;color:#666A5C">{{ __('You get this because price emails are on for your watchlist. The switch on that page turns them off.') }}</p>
                                        <p style="margin:0 0 6px">
                                            <a href="{{ route('watchlist') }}" style="color:#AD4D00;text-decoration:underline">{{ __('Your watchlist') }}</a>
                                            <span class="afmc-ink-muted" style="color:#9AA08F"> · </span>
                                            <a href="{{ route('legal.show', 'privacy-policy') }}" style="color:#AD4D00;text-decoration:underline">{{ __('Privacy policy') }}</a>
                                        </p>
                                        <p class="afmc-ink-muted" style="margin:0;color:#666A5C">{{ $company['product_name'] }}, {{ __('operated by :legal, KvK :kvk.', ['legal' => $company['legal_name'], 'kvk' => $company['kvk']]) }}</p>
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
