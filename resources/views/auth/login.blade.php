<x-guest-layout>
    <x-auth-session-status class="pm-flash-success" :status="session('status')" />

    <form method="POST" action="{{ route('login') }}" class="pm-form">
        @csrf

        <div class="pm-form-group">
            <label for="email" class="pm-label">{{ __('Email') }}</label>
            <input id="email" type="email" name="email"
                   value="{{ old('email') }}"
                   required autofocus autocomplete="username"
                   class="pm-input" />
            @error('email')
                <div class="pm-form-error">{{ $message }}</div>
            @enderror
        </div>

        <div class="pm-form-group">
            <label for="password" class="pm-label">{{ __('Password') }}</label>
            <input id="password" type="password" name="password"
                   required autocomplete="current-password"
                   class="pm-input" />
            @error('password')
                <div class="pm-form-error">{{ $message }}</div>
            @enderror
        </div>

        <div class="pm-checkbox-group">
            <input id="remember_me" type="checkbox" name="remember" class="pm-checkbox" />
            <label for="remember_me" class="pm-checkbox-label">{{ __('Ricordami') }}</label>
        </div>

        <div class="pm-auth-actions">
            @if (! config('demo.enabled') && Route::has('password.request'))
                <a href="{{ route('password.request') }}"
                   style="font-size:12px;color:var(--pm-text-muted);text-decoration:none;"
                   onmouseover="this.style.color='var(--pm-accent)'"
                   onmouseout="this.style.color='var(--pm-text-muted)'">
                    {{ __('Password dimenticata?') }}
                </a>
            @endif
            <button type="submit" class="pm-btn pm-btn-primary pm-auth-submit">
                {{ __('Accedi') }}
            </button>
        </div>
    </form>

    @if (config('demo.enabled'))
        <dl class="pm-demo-credentials" aria-label="{{ __('Accesso dimostrativo') }}">
            <div>
                <dt>{{ __('Email') }}</dt>
                <dd>demo@sodanoconsulting.it</dd>
            </div>
            <div>
                <dt>{{ __('Password') }}</dt>
                <dd>password</dd>
            </div>
        </dl>
    @endif
</x-guest-layout>
