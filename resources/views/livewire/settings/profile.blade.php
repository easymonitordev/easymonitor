<section class="w-full">
    @include('partials.settings-heading')

    <x-settings.layout :heading="__('Profile')" :subheading="__('Update your name and email address')">
        <form wire:submit="updateProfileInformation" class="my-6 w-full space-y-6">
            <div class="form-control">
                <label class="label pb-2">
                    <span class="label-text">{{ __('Name') }}</span>
                </label>
                <input
                    type="text"
                    wire:model="name"
                    required
                    autofocus
                    autocomplete="name"
                    class="input input-bordered w-full rounded-lg @error('name') input-error @enderror"
                />
                @error('name')
                    <div class="label">
                        <span class="label-text-alt text-error">{{ $message }}</span>
                    </div>
                @enderror
            </div>

            <div>
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

                @if (auth()->user() instanceof \Illuminate\Contracts\Auth\MustVerifyEmail &&! auth()->user()->hasVerifiedEmail())
                    <div>
                        <p class="mt-4 text-sm text-base-content">
                            {{ __('Your email address is unverified.') }}

                            <a class="link link-primary cursor-pointer" wire:click.prevent="resendVerificationNotification">
                                {{ __('Click here to re-send the verification email.') }}
                            </a>
                        </p>

                        @if (session('status') === 'verification-link-sent')
                            <p class="mt-2 text-sm font-medium text-success">
                                {{ __('A new verification link has been sent to your email address.') }}
                            </p>
                        @endif
                    </div>
                @endif
            </div>

            <div class="flex items-center gap-4">
                <div class="flex items-center justify-end">
                    <button type="submit" class="btn btn-primary rounded-lg">{{ __('Save') }}</button>
                </div>

                <x-action-message class="me-3" on="profile-updated">
                    {{ __('Saved.') }}
                </x-action-message>
            </div>
        </form>

        <livewire:settings.delete-user-form />
    </x-settings.layout>
</section>
