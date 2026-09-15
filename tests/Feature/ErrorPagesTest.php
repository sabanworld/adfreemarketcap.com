<?php

declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Support\Facades\Route;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class ErrorPagesTest extends TestCase
{
    /**
     * @return list<array{0: int, 1: string}>
     */
    public static function statusProvider(): array
    {
        return [
            [401, 'Sign in required'],
            [403, 'Not allowed'],
            [419, 'Page expired'],
            [429, 'Too many requests'],
            [500, 'Something broke'],
            [503, 'Temporarily down'],
        ];
    }

    public function test_unknown_url_renders_themed_404(): void
    {
        $response = $this->get('/this-page-does-not-exist-afmc-error-pages');

        $response->assertNotFound();
        $response->assertSee('data-afmc-error="404"', false);
        $response->assertSee('Page not found', false);
        $response->assertSee('adfreemarketcap', false);
        $response->assertSee('Back to markets', false);
        $response->assertSee('Open DexScan', false);
        $response->assertSee('<meta name="robots" content="noindex,nofollow">', false);
        $response->assertDontSee('Not Found', false);
    }

    #[DataProvider('statusProvider')]
    public function test_http_status_renders_themed_error_page(int $status, string $heading): void
    {
        Route::get('/__afmc-error-probe/{code}', function (int $code) {
            abort($code);
        });

        $response = $this->get('/__afmc-error-probe/' . $status);

        $response->assertStatus($status);
        $response->assertSee('data-afmc-error="' . $status . '"', false);
        $response->assertSee($heading, false);
        $response->assertSee('afmc-error__code', false);
        $response->assertSee('<meta name="robots" content="noindex,nofollow">', false);
    }
}
