<div class="w-full max-w-2xl mx-auto">
    <div class="mb-6">
        <h2 class="text-2xl font-bold">{{ __('Edit Team') }}</h2>
        <p class="text-base-content/70 mt-1">{{ __('Update your team information') }}</p>
    </div>

    <div class="card bg-base-100 border border-base-300">
        <div class="card-body">
            <form wire:submit="save" class="space-y-6">
                <div class="form-control">
                    <label class="label pb-2">
                        <span class="label-text">{{ __('Team Name') }}</span>
                    </label>
                    <input
                        type="text"
                        wire:model="name"
                        required
                        autofocus
                        class="input input-bordered w-full rounded-lg @error('name') input-error @enderror"
                        placeholder="{{ __('Enter team name') }}"
                    />
                    @error('name')
                        <div class="label">
                            <span class="label-text-alt text-error">{{ $message }}</span>
                        </div>
                    @enderror
                </div>

                <div class="form-control">
                    <label class="label pb-2">
                        <span class="label-text">{{ __('Description') }}</span>
                        <span class="label-text-alt text-base-content/70">{{ __('Optional') }}</span>
                    </label>
                    <textarea
                        wire:model="description"
                        rows="4"
                        class="textarea textarea-bordered w-full rounded-lg @error('description') textarea-error @enderror"
                        placeholder="{{ __('Describe your team and its purpose') }}"
                    ></textarea>
                    @error('description')
                        <div class="label">
                            <span class="label-text-alt text-error">{{ $message }}</span>
                        </div>
                    @enderror
                </div>

                <div class="flex gap-3 justify-end">
                    <a href="{{ route('teams.index') }}" wire:navigate class="btn btn-ghost rounded-lg">
                        {{ __('Cancel') }}
                    </a>
                    <button type="submit" class="btn btn-primary rounded-lg">
                        {{ __('Save Changes') }}
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
