@props([
    'href',
])

{{-- 20px line box plus 12px of padding each side keeps the tap target at 44px. --}}
<a href="{{ $href }}" style="display:inline-block;padding:12px 20px;background:#FF7A00;border:1px solid #E06A00;border-radius:6px;color:#0E0F0C;font-family:'Public Sans',-apple-system,BlinkMacSystemFont,'Segoe UI',Helvetica,Arial,sans-serif;font-size:15px;font-weight:600;line-height:20px;text-decoration:none">{{ $slot }}</a>
