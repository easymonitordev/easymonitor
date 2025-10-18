<div class="flex flex-col gap-6">
    <x-auth-header :title="__('Create an account')" :description="__('Enter your details below to create your account')" />

    <!-- Session Status -->
    <x-auth-session-status class="text-center" :status="session('status')" />

    <form method="POST" wire:submit="register" class="flex flex-col gap-6">
        <!-- Name -->
        <div class="form-control">
            <label class="label pb-2">
                <span class="label-text">{{ __('Name') }}</span>
            </label>
            <input
                type="text"
                wire:model="name"
                placeholder="{{ __('Full name') }}"
                class="input input-bordered w-full rounded-lg @error('name') input-error @enderror"
                required
                autofocus
                autocomplete="name"
            />
            @error('name')
                <label class="label">
                    <span class="label-text-alt text-error">{{ $message }}</span>
                </label>
            @enderror
        </div>

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
            <label class="label pb-2">
                <span class="label-text">{{ __('Password') }}</span>
            </label>
            <input
                type="password"
                wire:model="password"
                placeholder="{{ __('Password') }}"
                class="input input-bordered w-full rounded-lg @error('password') input-error @enderror"
                required
                autocomplete="new-password"
            />
            @error('password')
                <label class="label">
                    <span class="label-text-alt text-error">{{ $message }}</span>
                </label>
            @enderror
        </div>

        <!-- Confirm Password -->
        <div class="form-control">
            <label class="label pb-2">
                <span class="label-text">{{ __('Confirm password') }}</span>
            </label>
            <input
                type="password"
                wire:model="password_confirmation"
                placeholder="{{ __('Confirm password') }}"
                class="input input-bordered w-full rounded-lg @error('password_confirmation') input-error @enderror"
                required
                autocomplete="new-password"
            />
            @error('password_confirmation')
                <label class="label">
                    <span class="label-text-alt text-error">{{ $message }}</span>
                </label>
            @enderror
        </div>

        <div class="form-control">
            <button type="submit" class="btn btn-primary w-full">
                {{ __('Create account') }}
            </button>
        </div>
    </form>

    <div class="text-center text-sm">
        <span class="text-base-content/70">{{ __('Already have an account?') }}</span>
        <a href="{{ route('login') }}" wire:navigate class="link link-primary">{{ __('Log in') }}</a>
    </div>
</div>
