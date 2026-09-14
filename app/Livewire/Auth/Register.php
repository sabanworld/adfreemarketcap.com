<?php

declare(strict_types=1);

namespace App\Livewire\Auth;

use App\Models\User;
use App\Rules\ValidAltcha;
use App\Support\FormRateLimiter;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rules\Password;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.app')]
#[Title('Create account · adfreemarketcap')]
class Register extends Component
{
    public string $name = '';

    public string $email = '';

    public string $password = '';

    public string $password_confirmation = '';

    public string $altcha = '';

    public function register(): void
    {
        FormRateLimiter::ensureIsNotRateLimited('register');
        FormRateLimiter::hit('register');

        $validated = $this->validate([
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', 'confirmed', Password::defaults()],
            'altcha' => ['required', 'string', new ValidAltcha],
        ]);

        unset($validated['altcha']);

        $user = User::query()->create($validated);

        FormRateLimiter::clear('register');
        Auth::login($user);
        session()->regenerate();

        // The layout replays this after the redirect. It reaches Google only if
        // the visitor accepted advert measurement and the label is configured.
        session()->flash('afmc-conversion', 'registration');

        $this->redirect(route('watchlist'), navigate: true);
    }

    public function render()
    {
        return view('livewire.auth.register');
    }
}
