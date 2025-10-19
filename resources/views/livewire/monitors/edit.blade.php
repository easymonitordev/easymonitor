<div class="w-full max-w-2xl mx-auto">
    <div class="mb-6">
        <h2 class="text-2xl font-bold">{{ __('Edit Monitor') }}</h2>
        <p class="text-base-content/70 mt-1">{{ __('Update monitor settings') }}</p>
    </div>

    <div class="card bg-base-100 border border-base-300">
        <div class="card-body">
            <form wire:submit="save" class="space-y-6">
                <div class="form-control">
                    <label class="label pb-2">
                        <span class="label-text">{{ __('Monitor Name') }}</span>
                    </label>
                    <input
                        type="text"
                        wire:model="name"
                        required
                        autofocus
                        class="input input-bordered w-full rounded-lg @error('name') input-error @enderror"
                        placeholder="{{ __('My Website') }}"
                    />
                    @error('name')
                        <div class="label">
                            <span class="label-text-alt text-error">{{ $message }}</span>
                        </div>
                    @enderror
                </div>

                <div class="form-control">
                    <label class="label pb-2">
                        <span class="label-text">{{ __('URL') }}</span>
                    </label>
                    <input
                        type="url"
                        wire:model="url"
                        required
                        class="input input-bordered w-full rounded-lg @error('url') input-error @enderror"
                        placeholder="{{ __('https://example.com') }}"
                    />
                    @error('url')
                        <div class="label">
                            <span class="label-text-alt text-error">{{ $message }}</span>
                        </div>
                    @enderror
                </div>

                <div class="form-control">
                    <label class="label pb-2">
                        <span class="label-text">{{ __('Check Interval (seconds)') }}</span>
                    </label>
                    <input
                        type="number"
                        wire:model="checkInterval"
                        required
                        min="30"
                        max="3600"
                        class="input input-bordered w-full rounded-lg @error('checkInterval') input-error @enderror"
                        placeholder="60"
                    />
                    <div class="label">
                        <span class="label-text-alt text-base-content/70">{{ __('Minimum 30 seconds, maximum 3600 seconds (1 hour)') }}</span>
                    </div>
                    @error('checkInterval')
                        <div class="label">
                            <span class="label-text-alt text-error">{{ $message }}</span>
                        </div>
                    @enderror
                </div>

                <div class="form-control">
                    <label class="label cursor-pointer justify-start gap-4">
                        <input type="checkbox" wire:model="isActive" class="checkbox" />
                        <div>
                            <span class="label-text font-medium">{{ __('Active') }}</span>
                            <p class="text-sm text-base-content/70">{{ __('Enable monitoring for this website') }}</p>
                        </div>
                    </label>
                </div>

                <div class="flex gap-3 justify-end pt-4">
                    <a href="{{ route('monitors.index') }}" wire:navigate class="btn btn-ghost rounded-lg">
                        {{ __('Cancel') }}
                    </a>
                    <button type="submit" class="btn btn-primary rounded-lg">
                        {{ __('Update Monitor') }}
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
