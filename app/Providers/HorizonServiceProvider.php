<?php

declare(strict_types=1);

namespace App\Providers;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Laravel\Horizon\Horizon;
use Laravel\Horizon\HorizonApplicationServiceProvider;

class HorizonServiceProvider extends HorizonApplicationServiceProvider
{
    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        parent::boot();

        // Horizon::routeSmsNotificationsTo('15556667777');
        // Horizon::routeMailNotificationsTo('example@example.com');
        // Horizon::routeSlackNotificationsTo('slack-webhook-url', '#channel');
    }

    /**
     * Configure Horizon authorization.
     *
     * Local environments stay open. Elsewhere, require a Filament admin session.
     */
    protected function authorization(): void
    {
        $this->gate();

        Horizon::auth(function (): bool {
            return app()->environment(['local', 'testing'])
                || Auth::guard('admin')->check();
        });
    }

    /**
     * Register the Horizon gate for non-local environments.
     */
    protected function gate(): void
    {
        Gate::define('viewHorizon', function ($user = null): bool {
            return Auth::guard('admin')->check();
        });
    }
}
