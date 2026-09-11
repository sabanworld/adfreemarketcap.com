<?php

use App\Http\Controllers\RobotsController;
use App\Http\Controllers\SitemapController;
use App\Livewire\CoinShow;
use App\Livewire\Home;
use Illuminate\Support\Facades\Route;

Route::get('/', Home::class)->name('home');
Route::get('coins/{coin:slug}', CoinShow::class)->name('coins.show');

Route::get('sitemap.xml', SitemapController::class)->name('sitemap');
Route::get('robots.txt', RobotsController::class)->name('robots');
