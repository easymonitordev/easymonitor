<section class="w-full">
    @include('partials.settings-heading')

    <x-settings.layout :heading="__('Update password')" :subheading="__('Ensure your account is using a long, random password to stay secure')">
        <form method="POST" wire:submit="updatePassword" class="mt-6 space-y-6">
            <div class="form-control">
                <label class="label pb-2">
                    <span class="label-text">{{ __('Current password') }}</span>
                </label>
                <input
                    type="password"
                    wire:model="current_password"
                    required
                    autocomplete="current-password"
                    class="input input-bordered w-full rounded-lg @error('current_password') input-error @enderror"
                />
                @error('current_password')
                    <div class="label">
                        <span class="label-text-alt text-error">{{ $message }}</span>
                    </div>
                @enderror
            </div>

            <div class="form-control">
                <label class="label pb-2">
                    <span class="label-text">{{ __('New password') }}</span>
                </label>
                <input
                    type="password"
                    wire:model="password"
                    required
                    autocomplete="new-password"
                    class="input input-bordered w-full rounded-lg @error('password') input-error @enderror"
                />
                @error('password')
                    <div class="label">
                        <span class="label-text-alt text-error">{{ $message }}</span>
                    </div>
                @enderror
            </div>

            <div class="form-control">
                <label class="label pb-2">
                    <span class="label-text">{{ __('Confirm Password') }}</span>
                </label>
                <input
                    type="password"
                    wire:model="password_confirmation"
                    required
                    autocomplete="new-password"
                    class="input input-bordered w-full rounded-lg @error('password_confirmation') input-error @enderror"
                />
                @error('password_confirmation')
                    <div class="label">
                        <span class="label-text-alt text-error">{{ $message }}</span>
                    </div>
                @enderror
            </div>

            <div class="flex items-center gap-4">
                <div class="flex items-center justify-end">
                    <button type="submit" class="btn btn-primary rounded-lg">{{ __('Save') }}</button>
                </div>

                <x-action-message class="me-3" on="password-updated">
                    {{ __('Saved.') }}
                </x-action-message>
            </div>
        </form>
    </x-settings.layout>
</section>
