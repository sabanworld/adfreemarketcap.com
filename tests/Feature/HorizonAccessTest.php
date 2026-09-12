<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\User;
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
        $this->get('/horizon')->assertOk();
    }

    public function test_horizon_allows_authenticated_admins_outside_local(): void
    {
        $previous = app()->environment();
        app()->detectEnvironment(fn () => 'production');

        try {
            $this->assertFalse(Horizon::check(request()));
            $this->get('/horizon')->assertForbidden();

            $admin = Admin::factory()->create();
            $this->actingAs($admin, 'admin');

            $this->assertTrue(Horizon::check(request()));
            $this->get('/horizon')->assertOk();
        } finally {
            app()->detectEnvironment(fn () => $previous);
        }
    }

    public function test_public_users_cannot_open_horizon_outside_local(): void
    {
        $previous = app()->environment();
        app()->detectEnvironment(fn () => 'production');

        try {
            $this->actingAs(User::factory()->create())
                ->get('/horizon')
                ->assertForbidden();
        } finally {
            app()->detectEnvironment(fn () => $previous);
        }
    }

    public function test_admin_panel_links_to_horizon(): void
    {
        $this->actingAs(Admin::factory()->create(), 'admin');

        $this->get('/admin')
            ->assertOk()
            ->assertSee(__('admin.navigation.horizon'), false)
            ->assertSee('/' . trim((string) config('horizon.path', 'horizon'), '/'), false);
    }
}
