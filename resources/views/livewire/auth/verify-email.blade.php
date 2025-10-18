<div class="mt-4 flex flex-col gap-6">
    <p class="text-center text-base-content">
        {{ __('Please verify your email address by clicking on the link we just emailed to you.') }}
    </p>

    @if (session('status') == 'verification-link-sent')
        <p class="text-center font-medium text-success">
            {{ __('A new verification link has been sent to the email address you provided during registration.') }}
        </p>
    @endif

    <div class="flex flex-col items-center justify-between space-y-3">
        <button wire:click="sendVerification" class="btn btn-primary w-full rounded-lg">
            {{ __('Resend verification email') }}
        </button>

        <a class="link link-primary text-sm cursor-pointer" wire:click="logout">
            {{ __('Log out') }}
        </a>
    </div>
</div>
