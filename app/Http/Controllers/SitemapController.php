<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Services\Seo\SeoService;
use Illuminate\Http\Response;

final class SitemapController extends Controller
{
    public function __invoke(SeoService $seo): Response
    {
        return response()
            ->view('seo.sitemap', [
                'entries' => $seo->sitemapEntries(),
            ], 200, [
                'Content-Type' => 'application/xml; charset=UTF-8',
            ]);
    }
}
