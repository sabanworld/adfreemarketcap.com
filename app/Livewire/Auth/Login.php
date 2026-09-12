<?php

declare(strict_types=1);

namespace App\Livewire\Auth;

use App\Rules\ValidAltcha;
use App\Support\FormRateLimiter;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.app')]
#[Title('Sign in · adfreemarketcap')]
class Login extends Component
{
    public string $email = '';

    public string $password = '';

    public bool $remember = false;

    public string $altcha = '';

    public function login(): void
    {
        FormRateLimiter::ensureIsNotRateLimited('login');
        FormRateLimiter::hit('login');

        $credentials = $this->validate([
            'email' => ['required', 'string', 'email'],
            'password' => ['required', 'string'],
            'altcha' => ['required', 'string', new ValidAltcha],
        ]);

        unset($credentials['altcha']);

        if (! Auth::attempt($credentials, $this->remember)) {
            throw ValidationException::withMessages([
                'email' => __('These credentials do not match our records.'),
            ]);
        }

        FormRateLimiter::clear('login');
        session()->regenerate();

        $this->redirectIntended(default: route('watchlist'), navigate: true);
    }

    public function render()
    {
        return view('livewire.auth.login');
    }
}
