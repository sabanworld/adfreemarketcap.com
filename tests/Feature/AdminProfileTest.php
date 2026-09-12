<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\User;
use Filament\Auth\Pages\EditProfile;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;
use Tests\TestCase;

class AdminProfileTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Filament::setCurrentPanel('admin');
    }

    public function test_guests_are_sent_to_the_admin_login(): void
    {
        $this->get('/admin/profile')->assertRedirect('/admin/login');
    }

    public function test_admin_can_open_the_profile_page(): void
    {
        $this->actingAs($this->admin(), 'admin');

        $this->get('/admin/profile')
            ->assertOk()
            ->assertSee('Profile');
    }

    public function test_admin_can_change_name_and_email_with_the_current_password(): void
    {
        $admin = $this->admin();
        $this->actingAs($admin, 'admin');

        Livewire::test(EditProfile::class)
            ->fillForm([
                'name' => 'Ada Lovelace',
                'email' => 'ada@adfreemarketcap.com',
                'currentPassword' => 'current-password',
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        $admin->refresh();

        $this->assertSame('Ada Lovelace', $admin->name);
        $this->assertSame('ada@adfreemarketcap.com', $admin->email);
    }

    public function test_changing_the_email_needs_the_current_password(): void
    {
        $admin = $this->admin();
        $this->actingAs($admin, 'admin');

        Livewire::test(EditProfile::class)
            ->fillForm([
                'email' => 'someone-else@adfreemarketcap.com',
                'currentPassword' => 'not-the-password',
            ])
            ->call('save')
            ->assertHasFormErrors(['currentPassword']);

        $this->assertSame('admin@adfreemarketcap.com', $admin->refresh()->email);
    }

    public function test_admin_can_change_the_password_and_sign_in_with_it(): void
    {
        $admin = $this->admin();
        $this->actingAs($admin, 'admin');

        Livewire::test(EditProfile::class)
            ->fillForm([
                'password' => 'a-longer-new-password',
                'passwordConfirmation' => 'a-longer-new-password',
                'currentPassword' => 'current-password',
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        $admin->refresh();

        $this->assertTrue(Hash::check('a-longer-new-password', $admin->password));
        $this->assertFalse(Hash::check('current-password', $admin->password));

        // The new password is the only one that works from a fresh session.
        Auth::guard('admin')->logout();
        $this->assertFalse(Auth::guard('admin')->attempt([
            'email' => $admin->email,
            'password' => 'current-password',
        ]));
        $this->assertTrue(Auth::guard('admin')->attempt([
            'email' => $admin->email,
            'password' => 'a-longer-new-password',
        ]));
    }

    public function test_a_new_password_must_be_confirmed_and_long_enough(): void
    {
        $admin = $this->admin();
        $this->actingAs($admin, 'admin');

        Livewire::test(EditProfile::class)
            ->fillForm([
                'password' => 'a-longer-new-password',
                'passwordConfirmation' => 'something-else',
                'currentPassword' => 'current-password',
            ])
            ->call('save')
            ->assertHasFormErrors(['password']);

        Livewire::test(EditProfile::class)
            ->fillForm([
                'password' => 'short',
                'passwordConfirmation' => 'short',
                'currentPassword' => 'current-password',
            ])
            ->call('save')
            ->assertHasFormErrors(['password']);

        $this->assertTrue(Hash::check('current-password', $admin->refresh()->password));
    }

    public function test_email_stays_unique_across_admins(): void
    {
        Admin::factory()->create(['email' => 'taken@adfreemarketcap.com']);
        $admin = $this->admin();
        $this->actingAs($admin, 'admin');

        Livewire::test(EditProfile::class)
            ->fillForm([
                'email' => 'taken@adfreemarketcap.com',
                'currentPassword' => 'current-password',
            ])
            ->call('save')
            ->assertHasFormErrors(['email']);
    }

    public function test_public_users_cannot_reach_the_admin_profile(): void
    {
        $this->actingAs(User::factory()->create())
            ->get('/admin/profile')
            ->assertRedirect('/admin/login');
    }

    private function admin(): Admin
    {
        return Admin::factory()->create([
            'name' => 'Admin',
            'email' => 'admin@adfreemarketcap.com',
            'password' => Hash::make('current-password'),
        ]);
    }
}
