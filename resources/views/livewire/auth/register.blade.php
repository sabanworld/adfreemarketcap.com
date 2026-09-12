<div class="afmc-page" data-afmc-page style="max-width:28rem">
    <h1 style="font:var(--type-h1);margin:0 0 var(--space-2)">{{ __('Create account') }}</h1>
    <p style="margin:0 0 var(--space-5);font:var(--type-body-sm);color:var(--text-muted)">
        {{ __('Watchlists require an account. We never sell your email.') }}
    </p>

    <form wire:submit="register" class="afmc-card" style="padding:var(--space-5);display:grid;gap:var(--space-4)">
        <label style="display:grid;gap:var(--space-1)">
            <span style="font:var(--type-label);color:var(--text-muted)">{{ __('Name') }}</span>
            <input type="text" wire:model="name" autocomplete="name" required class="afmc-input" />
            @error('name') <span style="font:var(--type-body-sm);color:var(--text-down)">{{ $message }}</span> @enderror
        </label>

        <label style="display:grid;gap:var(--space-1)">
            <span style="font:var(--type-label);color:var(--text-muted)">{{ __('Email') }}</span>
            <input type="email" wire:model="email" autocomplete="username" required class="afmc-input" />
            @error('email') <span style="font:var(--type-body-sm);color:var(--text-down)">{{ $message }}</span> @enderror
        </label>

        <label style="display:grid;gap:var(--space-1)">
            <span style="font:var(--type-label);color:var(--text-muted)">{{ __('Password') }}</span>
            <input type="password" wire:model="password" autocomplete="new-password" required class="afmc-input" />
            @error('password') <span style="font:var(--type-body-sm);color:var(--text-down)">{{ $message }}</span> @enderror
        </label>

        <label style="display:grid;gap:var(--space-1)">
            <span style="font:var(--type-label);color:var(--text-muted)">{{ __('Confirm password') }}</span>
            <input type="password" wire:model="password_confirmation" autocomplete="new-password" required class="afmc-input" />
        </label>

        <x-afmc.altcha />

        <p style="margin:0;font:var(--type-body-sm);color:var(--text-muted)">
            {!! __('Creating an account means you accept our :terms, and that we handle your data as described in our :privacy.', [
                'terms' => '<a href="'.route('legal.show', 'terms').'" wire:navigate>'.e(__('Terms & conditions')).'</a>',
                'privacy' => '<a href="'.route('legal.show', 'privacy-policy').'" wire:navigate>'.e(__('Privacy policy')).'</a>',
            ]) !!}
        </p>

        <button type="submit" class="afmc-btn afmc-btn--primary afmc-btn--md">{{ __('Create account') }}</button>
    </form>

    <p style="margin:var(--space-4) 0 0;font:var(--type-body-sm);color:var(--text-muted)">
        {{ __('Already have an account?') }}
        <a href="{{ route('login') }}" wire:navigate>{{ __('Sign in') }}</a>
    </p>
</div>
