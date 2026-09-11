<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Admin;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Horizon\Horizon;
use Tests\TestCase;

class HorizonAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_horizon_dashboard_is_reachable_in_local(): void
    {
        $this->assertTrue(app()->environment('local', 'testing'));

        $this->assertTrue(Horizon::check(request()));
    }

    public function test_horizon_allows_authenticated_admins_outside_local(): void
    {
        $previous = app()->environment();
        app()->detectEnvironment(fn () => 'production');

        try {
            $this->assertFalse(Horizon::check(request()));

            $admin = Admin::factory()->create();
            $this->actingAs($admin, 'admin');

            $this->assertTrue(Horizon::check(request()));
        } finally {
            app()->detectEnvironment(fn () => $previous);
        }
    }
}
