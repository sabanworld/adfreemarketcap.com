<?php

declare(strict_types=1);

namespace App\Support;

use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;

final class QrSvg
{
    /**
     * Inline SVG for a QR payload (no XML declaration, safe to embed in HTML).
     */
    public static function make(string $payload, int $size = 128): string
    {
        $writer = new Writer(new ImageRenderer(
            new RendererStyle($size),
            new SvgImageBackEnd,
        ));

        $svg = $writer->writeString($payload);

        $withoutDeclaration = preg_replace('/<\?xml[^?]*\?>\s*/u', '', $svg);

        return is_string($withoutDeclaration) ? $withoutDeclaration : $svg;
    }
}
