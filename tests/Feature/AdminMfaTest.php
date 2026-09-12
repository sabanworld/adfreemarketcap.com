<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Admin;
use Filament\Auth\MultiFactor\App\AppAuthentication;
use Filament\Auth\Pages\EditProfile;
use Filament\Auth\Pages\Login;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;
use PragmaRX\Google2FA\Google2FA;
use Tests\TestCase;

class AdminMfaTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Filament::setCurrentPanel('admin');
        config(['admin.mfa_required' => false]);
    }

    public function test_profile_offers_authenticator_app_setup(): void
    {
        $this->actingAs($this->admin(), 'admin');

        $this->get('/admin/profile')
            ->assertOk()
            ->assertSee(__('filament-panels::auth/multi-factor/app/provider.management_schema.actions.label'))
            ->assertSee(__('filament-panels::auth/multi-factor/app/actions/set-up.label'));
    }

    public function test_panel_stays_open_without_mfa_when_not_required(): void
    {
        $this->actingAs($this->admin(), 'admin');

        $this->get('/admin')->assertOk();
        $this->get('/admin/profile')->assertOk();
    }

    public function test_production_style_enforcement_sends_admins_without_mfa_to_set_up(): void
    {
        config(['admin.mfa_required' => true]);

        $this->actingAs($this->admin(), 'admin');

        $this->get('/admin')
            ->assertRedirect(route('filament.admin.auth.multi-factor-authentication.set-up-required'));

        $this->get('/admin/profile')
            ->assertRedirect(route('filament.admin.auth.multi-factor-authentication.set-up-required'));

        $this->get(route('filament.admin.auth.multi-factor-authentication.set-up-required'))
            ->assertOk()
            ->assertSee(__('filament-panels::auth/multi-factor/pages/set-up-required-multi-factor-authentication.heading'));
    }

    public function test_admins_with_mfa_enabled_can_use_the_panel_when_required(): void
    {
        config(['admin.mfa_required' => true]);

        $admin = $this->adminWithAppAuthentication();
        $this->actingAs($admin, 'admin');

        $this->get('/admin')->assertOk();
        $this->get('/admin/profile')
            ->assertOk()
            ->assertSee(__('filament-panels::auth/multi-factor/app/provider.management_schema.actions.messages.enabled'));
    }

    public function test_login_challenges_for_an_authenticator_code_when_mfa_is_enabled(): void
    {
        $admin = $this->adminWithAppAuthentication();
        $secret = $admin->getAppAuthenticationSecret();
        $this->assertNotNull($secret);

        $login = Livewire::test(Login::class)
            ->fillForm([
                'email' => $admin->email,
                'password' => 'current-password',
            ])
            ->call('authenticate');

        $login->assertHasNoFormErrors()
            ->assertSet('userUndertakingMultiFactorAuthentication', fn (mixed $value): bool => filled($value));

        $code = app(Google2FA::class)->getCurrentOtp($secret);

        $login->fillForm([
            'app' => [
                'code' => $code,
            ],
        ], 'multiFactorChallengeForm')
            ->call('authenticate')
            ->assertHasNoFormErrors()
            ->assertRedirect('/admin');

        $this->assertAuthenticatedAs($admin, 'admin');
    }

    public function test_login_rejects_an_invalid_authenticator_code(): void
    {
        $admin = $this->adminWithAppAuthentication();

        Livewire::test(Login::class)
            ->fillForm([
                'email' => $admin->email,
                'password' => 'current-password',
            ])
            ->call('authenticate')
            ->fillForm([
                'app' => [
                    'code' => '000000',
                ],
            ], 'multiFactorChallengeForm')
            ->call('authenticate')
            ->assertHasErrors(['data.multiFactor.app.code']);

        $this->assertGuest('admin');
    }

    public function test_app_authentication_provider_is_wired_on_the_panel(): void
    {
        $panel = Filament::getCurrentOrDefaultPanel();

        $this->assertTrue($panel->hasMultiFactorAuthentication());
        $this->assertArrayHasKey('app', $panel->getMultiFactorAuthenticationProviders());
        $this->assertInstanceOf(AppAuthentication::class, $panel->getMultiFactorAuthenticationProviders()['app']);
        $this->assertTrue($panel->getMultiFactorAuthenticationProviders()['app']->isRecoverable());
    }

    public function test_profile_still_saves_when_mfa_is_configured(): void
    {
        $admin = $this->adminWithAppAuthentication();
        $this->actingAs($admin, 'admin');

        Livewire::test(EditProfile::class)
            ->fillForm([
                'name' => 'MFA Admin',
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertSame('MFA Admin', $admin->refresh()->name);
        $this->assertNotNull($admin->getAppAuthenticationSecret());
    }

    private function admin(): Admin
    {
        return Admin::factory()->create([
            'name' => 'Admin',
            'email' => 'admin@adfreemarketcap.com',
            'password' => Hash::make('current-password'),
        ]);
    }

    private function adminWithAppAuthentication(): Admin
    {
        $admin = $this->admin();
        $secret = app(Google2FA::class)->generateSecretKey();
        $admin->saveAppAuthenticationSecret($secret);
        $admin->saveAppAuthenticationRecoveryCodes([
            Hash::make('recovery-code-one'),
            Hash::make('recovery-code-two'),
        ]);

        return $admin->refresh();
    }
}
