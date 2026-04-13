<div class="flex flex-col gap-6">
    <x-auth-header :title="__('Log in to your account')" :description="__('Enter your email and password below to log in')" />

    <!-- Session Status -->
    <x-auth-session-status class="text-center" :status="session('status')" />

    <form method="POST" wire:submit="login" class="flex flex-col gap-6">
        <!-- Email Address -->
        <div class="form-control">
            <label class="label pb-2">
                <span class="label-text">{{ __('Email address') }}</span>
            </label>
            <input
                type="email"
                wire:model="email"
                placeholder="email@example.com"
                class="input input-bordered w-full rounded-lg @error('email') input-error @enderror"
                required
                autofocus
                autocomplete="email"
            />
            @error('email')
                <label class="label">
                    <span class="label-text-alt text-error">{{ $message }}</span>
                </label>
            @enderror
        </div>

        <!-- Password -->
        <div class="form-control">
            <div class="flex justify-between items-center pb-2">
                <label class="label p-0">
                    <span class="label-text">{{ __('Password') }}</span>
                </label>
                @if (Route::has('password.request'))
                    <a href="{{ route('password.request') }}" wire:navigate class="label-text-alt link link-hover">
                        {{ __('Forgot your password?') }}
                    </a>
                @endif
            </div>
            <input
                type="password"
                wire:model="password"
                placeholder="{{ __('Password') }}"
                class="input input-bordered w-full rounded-lg @error('password') input-error @enderror"
                required
                autocomplete="current-password"
            />
            @error('password')
                <label class="label">
                    <span class="label-text-alt text-error">{{ $message }}</span>
                </label>
            @enderror
        </div>

        <!-- Remember Me -->
        <div class="form-control">
            <label class="label cursor-pointer justify-start gap-2">
                <input type="checkbox" wire:model="remember" class="checkbox checkbox-sm" />
                <span class="label-text">{{ __('Remember me') }}</span>
            </label>
        </div>

        <div class="form-control">
            <button type="submit" class="btn btn-primary w-full" data-test="login-button">
                {{ __('Log in') }}
            </button>
        </div>
    </form>

    @if (Route::has('register') && \App\Models\User::registrationAllowed())
        <div class="text-center text-sm">
            <span class="text-base-content/70">{{ __('Don\'t have an account?') }}</span>
            <a href="{{ route('register') }}" wire:navigate class="link link-primary">{{ __('Sign up') }}</a>
        </div>
    @endif
</div>
