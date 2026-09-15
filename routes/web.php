<?php

declare(strict_types=1);

use App\Http\Controllers\AltchaChallengeController;
use App\Http\Controllers\Auth\LogoutController;
use App\Http\Controllers\RobotsController;
use App\Http\Controllers\SitemapController;
use App\Livewire\Auth\Login;
use App\Livewire\Auth\Register;
use App\Livewire\CoinShow;
use App\Livewire\DexPairShow;
use App\Livewire\DexScan;
use App\Livewire\DexTokenShow;
use App\Livewire\Home;
use App\Livewire\LegalPage;
use App\Livewire\Watchlist;
use App\Livewire\WhyAdFree;
use Illuminate\Support\Facades\Route;

Route::get('/', Home::class)->name('home');
Route::get('coins/{coin:slug}', CoinShow::class)->name('coins.show');
Route::get('dexscan', DexScan::class)->name('dexscan');
Route::get('dexscan/pairs/{pair:slug}', DexPairShow::class)->name('dexscan.pair');
Route::get('dexscan/{network}/{address}', DexTokenShow::class)
    ->where('network', '[A-Za-z0-9_-]+')
    ->where('address', '[A-Za-z0-9]+')
    ->name('dexscan.token');
Route::get('why-ad-free', WhyAdFree::class)->name('why-ad-free');

Route::get('legal/{page}', LegalPage::class)
    ->whereIn('page', array_keys(LegalPage::PAGES))
    ->name('legal.show');

Route::get(config('altcha.route.path', 'altcha'), AltchaChallengeController::class)
    ->middleware('throttle:' . config('forms.altcha_challenge.max_attempts', 60) . ',1')
    ->name(config('altcha.route.name', 'altcha.challenge'));

Route::middleware('guest')->group(function (): void {
    Route::get('login', Login::class)->name('login');
    Route::get('register', Register::class)->name('register');
});

Route::post('logout', LogoutController::class)
    ->middleware([
        'auth',
        'throttle:' . config('forms.logout.max_attempts', 10) . ',1',
    ])
    ->name('logout');

Route::get('watchlist', Watchlist::class)
    ->middleware('auth')
    ->name('watchlist');

Route::get('sitemap.xml', SitemapController::class)->name('sitemap');
Route::get('robots.txt', RobotsController::class)->name('robots');
