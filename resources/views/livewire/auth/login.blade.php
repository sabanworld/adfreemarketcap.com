<div class="afmc-page" data-afmc-page style="max-width:28rem">
    <h1 style="font:var(--type-h1);margin:0 0 var(--space-2)">{{ __('Sign in') }}</h1>
    <p style="margin:0 0 var(--space-5);font:var(--type-body-sm);color:var(--text-muted)">
        {{ __('Sign in to save a watchlist across devices.') }}
    </p>

    <form wire:submit="login" class="afmc-card" style="padding:var(--space-5);display:grid;gap:var(--space-4)">
        <label style="display:grid;gap:var(--space-1)">
            <span style="font:var(--type-label);color:var(--text-muted)">{{ __('Email') }}</span>
            <input type="email" wire:model="email" autocomplete="username" required class="afmc-input" />
            @error('email') <span style="font:var(--type-body-sm);color:var(--text-down)">{{ $message }}</span> @enderror
        </label>

        <label style="display:grid;gap:var(--space-1)">
            <span style="font:var(--type-label);color:var(--text-muted)">{{ __('Password') }}</span>
            <input type="password" wire:model="password" autocomplete="current-password" required class="afmc-input" />
            @error('password') <span style="font:var(--type-body-sm);color:var(--text-down)">{{ $message }}</span> @enderror
        </label>

        <label class="afmc-check">
            <input type="checkbox" wire:model="remember" />
            {{ __('Remember me') }}
        </label>

        <x-afmc.altcha />

        <button type="submit" class="afmc-btn afmc-btn--primary afmc-btn--md">{{ __('Sign in') }}</button>
    </form>

    <p style="margin:var(--space-4) 0 0;font:var(--type-body-sm);color:var(--text-muted)">
        {{ __('No account yet?') }}
        <a href="{{ route('register') }}" wire:navigate>{{ __('Create one') }}</a>
    </p>
</div>
