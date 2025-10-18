<div class="flex flex-col gap-6">
    <x-auth-header :title="__('Reset password')" :description="__('Please enter your new password below')" />

    <!-- Session Status -->
    <x-auth-session-status class="text-center" :status="session('status')" />

    <form method="POST" wire:submit="resetPassword" class="flex flex-col gap-6">
        <!-- Email Address -->
        <div class="form-control">
            <label class="label pb-2">
                <span class="label-text">{{ __('Email') }}</span>
            </label>
            <input
                type="email"
                wire:model="email"
                required
                autocomplete="email"
                class="input input-bordered w-full rounded-lg @error('email') input-error @enderror"
            />
            @error('email')
                <div class="label">
                    <span class="label-text-alt text-error">{{ $message }}</span>
                </div>
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
                required
                autocomplete="new-password"
                placeholder="{{ __('Password') }}"
                class="input input-bordered w-full rounded-lg @error('password') input-error @enderror"
            />
            @error('password')
                <div class="label">
                    <span class="label-text-alt text-error">{{ $message }}</span>
                </div>
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
                required
                autocomplete="new-password"
                placeholder="{{ __('Confirm password') }}"
                class="input input-bordered w-full rounded-lg @error('password_confirmation') input-error @enderror"
            />
            @error('password_confirmation')
                <div class="label">
                    <span class="label-text-alt text-error">{{ $message }}</span>
                </div>
            @enderror
        </div>

        <div class="flex items-center justify-end">
            <button type="submit" class="btn btn-primary w-full rounded-lg">
                {{ __('Reset password') }}
            </button>
        </div>
    </form>
</div>
