 <div class="flex flex-col gap-6">
    <x-auth-header :title="__('Forgot password')" :description="__('Enter your email to receive a password reset link')" />

    <!-- Session Status -->
    <x-auth-session-status class="text-center" :status="session('status')" />

    <form method="POST" wire:submit="sendPasswordResetLink" class="flex flex-col gap-6">
        <!-- Email Address -->
        <div class="form-control">
            <label class="label pb-2">
                <span class="label-text">{{ __('Email Address') }}</span>
            </label>
            <input
                type="email"
                wire:model="email"
                required
                autofocus
                placeholder="email@example.com"
                class="input input-bordered w-full rounded-lg @error('email') input-error @enderror"
            />
            @error('email')
                <div class="label">
                    <span class="label-text-alt text-error">{{ $message }}</span>
                </div>
            @enderror
        </div>

        <button type="submit" class="btn btn-primary w-full rounded-lg">{{ __('Email password reset link') }}</button>
    </form>

    <div class="space-x-1 rtl:space-x-reverse text-center text-sm text-base-content/70">
        <span>{{ __('Or, return to') }}</span>
        <a href="{{ route('login') }}" wire:navigate class="link link-primary">{{ __('log in') }}</a>
    </div>
</div>
