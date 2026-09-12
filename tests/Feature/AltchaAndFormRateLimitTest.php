<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Livewire\Auth\Login;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class AltchaAndFormRateLimitTest extends TestCase
{
    use RefreshDatabase;

    public function test_altcha_challenge_endpoint_returns_signed_payload(): void
    {
        $response = $this->getJson(route('altcha.challenge'));

        $response->assertOk();
        $response->assertJsonStructure([
            'parameters' => [
                'algorithm',
                'nonce',
                'salt',
                'cost',
                'keyLength',
                'keyPrefix',
            ],
            'signature',
        ]);
    }

    public function test_login_rejects_missing_altcha(): void
    {
        User::factory()->create([
            'email' => 'ada@example.com',
            'password' => 'password123',
        ]);

        Livewire::test(Login::class)
            ->set('email', 'ada@example.com')
            ->set('password', 'password123')
            ->call('login')
            ->assertHasErrors(['altcha']);
    }

    public function test_login_is_rate_limited(): void
    {
        config(['forms.login.max_attempts' => 2]);

        User::factory()->create([
            'email' => 'ada@example.com',
            'password' => 'password123',
        ]);

        $bypass = (string) config('altcha.testing_bypass');

        Livewire::test(Login::class)
            ->set('email', 'wrong@example.com')
            ->set('password', 'bad')
            ->set('altcha', $bypass)
            ->call('login')
            ->assertHasErrors(['email']);

        Livewire::test(Login::class)
            ->set('email', 'wrong@example.com')
            ->set('password', 'bad')
            ->set('altcha', $bypass)
            ->call('login')
            ->assertHasErrors(['email']);

        $limited = Livewire::test(Login::class)
            ->set('email', 'wrong@example.com')
            ->set('password', 'bad')
            ->set('altcha', $bypass)
            ->call('login');

        $limited->assertHasErrors(['email']);
        $this->assertStringContainsString('Too many attempts', (string) $limited->errors()->first('email'));
    }
}
