<section class="mt-10 space-y-6">
    <div class="relative mb-5">
        <h2 class="text-xl font-bold">{{ __('Delete account') }}</h2>
        <p class="text-base-content/70 mt-1">{{ __('Delete your account and all of its resources') }}</p>
    </div>

    <button
        class="btn btn-error rounded-lg"
        onclick="confirm_user_deletion_modal.showModal()"
    >
        {{ __('Delete account') }}
    </button>

    <dialog id="confirm_user_deletion_modal" class="modal" @if($errors->isNotEmpty()) open @endif>
        <div class="modal-box max-w-lg">
            <form method="POST" wire:submit="deleteUser" class="space-y-6">
                <div>
                    <h3 class="text-xl font-bold">{{ __('Are you sure you want to delete your account?') }}</h3>

                    <p class="text-base-content/70 mt-2">
                        {{ __('Once your account is deleted, all of its resources and data will be permanently deleted. Please enter your password to confirm you would like to permanently delete your account.') }}
                    </p>
                </div>

                <div class="form-control">
                    <label class="label pb-2">
                        <span class="label-text">{{ __('Password') }}</span>
                    </label>
                    <input
                        type="password"
                        wire:model="password"
                        class="input input-bordered w-full rounded-lg @error('password') input-error @enderror"
                    />
                    @error('password')
                        <div class="label">
                            <span class="label-text-alt text-error">{{ $message }}</span>
                        </div>
                    @enderror
                </div>

                <div class="flex justify-end space-x-2 rtl:space-x-reverse">
                    <button type="button" class="btn btn-ghost rounded-lg" onclick="confirm_user_deletion_modal.close()">
                        {{ __('Cancel') }}
                    </button>

                    <button type="submit" class="btn btn-error rounded-lg">{{ __('Delete account') }}</button>
                </div>
            </form>
        </div>
        <form method="dialog" class="modal-backdrop">
            <button>close</button>
        </form>
    </dialog>
</section>
