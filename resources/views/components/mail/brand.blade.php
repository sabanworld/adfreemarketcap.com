@props([
    'height' => 20,
    'tone' => 'ink',
])

@php
    // The mark is drawn with table cells rather than fetched as an image, so opening this
    // email makes no request at all. A remote logo would tell us who opened it and when.
    $h = max(12, (int) $height);
    $barWidth = max(3, (int) round($h * 0.25));
    $gap = max(2, (int) round($h * 0.125));
    $markGap = (int) round($h * 0.45);
    $wordSize = (int) round($h * 0.82);
    $barInk = $tone === 'paper' ? '#FFFFFF' : '#0E0F0C';
    $wordInk = $tone === 'paper' ? '#FFFFFF' : '#0E0F0C';
    $bars = [0.60, 1.0, 0.38, 0.78];
@endphp

<table role="presentation" cellpadding="0" cellspacing="0" border="0" style="border-collapse:collapse">
    <tr>
        <td valign="bottom" style="padding:0">
            <table role="presentation" cellpadding="0" cellspacing="0" border="0" style="border-collapse:collapse">
                <tr>
                    @foreach ($bars as $index => $portion)
                        @php
                            $barHeight = (int) round($h * $portion);
                            $fill = $index === 1 ? '#FF7A00' : $barInk;
                        @endphp
                        <td valign="bottom" style="padding:0 {{ $index === count($bars) - 1 ? 0 : $gap }}px 0 0">
                            <table role="presentation" cellpadding="0" cellspacing="0" border="0" style="border-collapse:collapse">
                                <tr>
                                    <td width="{{ $barWidth }}" height="{{ $barHeight }}" bgcolor="{{ $fill }}" style="width:{{ $barWidth }}px;height:{{ $barHeight }}px;line-height:{{ $barHeight }}px;font-size:0;background:{{ $fill }};border-radius:1px">&nbsp;</td>
                                </tr>
                            </table>
                        </td>
                    @endforeach
                </tr>
            </table>
        </td>
        <td valign="bottom" style="padding:0 0 0 {{ $markGap }}px;font-family:Archivo,'Helvetica Neue',Helvetica,Arial,sans-serif;font-weight:900;font-size:{{ $wordSize }}px;line-height:{{ $h }}px;letter-spacing:-0.035em;color:{{ $wordInk }}">adfreemarketcap</td>
    </tr>
</table>
