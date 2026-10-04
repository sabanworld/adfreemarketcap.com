<?php

declare(strict_types=1);

use App\Http\Controllers\Api\SiblingPriceFeedController;
use App\Http\Middleware\AuthenticateSiblingApi;
use Illuminate\Support\Facades\Route;

Route::get(config('sibling_api.route', 'api/sibling/prices'), SiblingPriceFeedController::class)
    ->middleware([
        AuthenticateSiblingApi::class,
        'throttle:'.config('sibling_api.throttle', '60,1'),
    ])
    ->name('api.sibling.prices');
