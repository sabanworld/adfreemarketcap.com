<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Livewire\Auth\Login;
use App\Livewire\Auth\Register;
use App\Livewire\Home;
use App\Livewire\Watchlist;
use App\Models\Coin;
use App\Models\User;
use App\Services\Watchlist\WatchlistService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class WatchlistAuthTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_are_redirected_from_watchlist_to_login(): void
    {
        $this->get(route('watchlist'))->assertRedirect(route('login'));
    }

    public function test_user_can_register_and_reach_watchlist(): void
    {
        Livewire::test(Register::class)
            ->set('name', 'Ada')
            ->set('email', 'ada@example.com')
            ->set('password', 'password123')
            ->set('password_confirmation', 'password123')
            ->set('altcha', (string) config('altcha.testing_bypass'))
            ->call('register')
            ->assertRedirect(route('watchlist'));

        $this->assertAuthenticated();
        $this->assertDatabaseHas('users', ['email' => 'ada@example.com']);
    }

    public function test_user_can_login(): void
    {
        $user = User::factory()->create([
            'email' => 'ada@example.com',
            'password' => 'password123',
        ]);

        Livewire::test(Login::class)
            ->set('email', $user->email)
            ->set('password', 'password123')
            ->set('altcha', (string) config('altcha.testing_bypass'))
            ->call('login')
            ->assertRedirect(route('watchlist'));

        $this->assertAuthenticatedAs($user);
    }

    public function test_authenticated_user_can_toggle_watchlist_item(): void
    {
        $user = User::factory()->create();
        $coin = Coin::query()->create([
            'slug' => 'bitcoin',
            'symbol' => 'BTC',
            'name' => 'Bitcoin',
            'rank' => 1,
            'price' => 50000,
        ]);

        $this->actingAs($user);

        Livewire::test(Home::class)
            ->call('toggleWatch', $coin->id)
            ->assertSet('watchedIds', [$coin->id]);

        $this->assertTrue(app(WatchlistService::class)->isWatched($user, $coin));

        Livewire::test(Watchlist::class)
            ->assertSee('Bitcoin')
            ->assertOk();
    }

    public function test_guest_toggle_redirects_to_login(): void
    {
        $coin = Coin::query()->create([
            'slug' => 'bitcoin',
            'symbol' => 'BTC',
            'name' => 'Bitcoin',
            'rank' => 1,
            'price' => 50000,
        ]);

        Livewire::test(Home::class)
            ->call('toggleWatch', $coin->id)
            ->assertRedirect(route('login'));
    }
}
