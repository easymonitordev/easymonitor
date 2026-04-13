<div class="w-full">
    <div class="mb-6">
        <div class="flex items-center gap-3">
            <a href="{{ route('status-pages.index') }}" wire:navigate class="btn btn-ghost btn-sm btn-circle">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" />
                </svg>
            </a>
            <div>
                <h2 class="text-2xl font-bold">{{ __('New Status Page') }}</h2>
                <p class="text-base-content/70 mt-0.5 text-sm">{{ __('A page that shows the live status of your services') }}</p>
            </div>
        </div>
    </div>

    <form wire:submit="save" class="max-w-2xl mx-auto">
        <div class="card bg-base-100 border border-base-300 mb-4">
            <div class="card-body gap-5">
                <h3 class="text-sm font-semibold uppercase tracking-wider text-base-content/50">{{ __('Basics') }}</h3>

                <div class="form-control">
                    <label class="label pb-1">
                        <span class="label-text font-medium">{{ __('Name') }}</span>
                    </label>
                    <input type="text" wire:model.live.debounce.300ms="name" required autofocus
                        class="input input-bordered w-full rounded-lg @error('name') input-error @enderror"
                        placeholder="{{ __('My Service Status') }}" />
                    @error('name')
                        <div class="label pb-0"><span class="label-text-alt text-error">{{ $message }}</span></div>
                    @enderror
                </div>

                <div class="form-control">
                    <label class="label pb-1">
                        <span class="label-text font-medium">{{ __('URL Slug') }}</span>
                    </label>
                    <div class="join w-full">
                        <span class="join-item btn btn-disabled no-animation bg-base-200 border-base-300 text-base-content/60 px-3">
                            /status/
                        </span>
                        <input type="text" wire:model="slug" required
                            class="input input-bordered join-item w-full @error('slug') input-error @enderror"
                            placeholder="my-service" />
                    </div>
                    <div class="label pb-0">
                        <span class="label-text-alt text-base-content/50">{{ __('Lowercase letters, numbers, and dashes only.') }}</span>
                    </div>
                    @error('slug')
                        <div class="label pb-0"><span class="label-text-alt text-error">{{ $message }}</span></div>
                    @enderror
                </div>

                <div class="form-control">
                    <label class="label pb-1">
                        <span class="label-text font-medium">{{ __('Description') }}</span>
                        <span class="label-text-alt text-base-content/50">{{ __('Optional') }}</span>
                    </label>
                    <textarea wire:model="description" rows="2"
                        class="textarea textarea-bordered w-full rounded-lg @error('description') textarea-error @enderror"
                        placeholder="{{ __('Live status of our infrastructure and APIs') }}"></textarea>
                </div>
            </div>
        </div>

        <div class="card bg-base-100 border border-base-300 mb-4">
            <div class="card-body gap-5">
                <h3 class="text-sm font-semibold uppercase tracking-wider text-base-content/50">{{ __('Visibility') }}</h3>

                <div class="form-control gap-2">
                    <label class="label cursor-pointer justify-start gap-3 py-2">
                        <input type="radio" wire:model="visibility" value="public" class="radio radio-primary" />
                        <div class="flex-1">
                            <div class="font-medium">{{ __('Public') }}</div>
                            <div class="text-xs text-base-content/50">{{ __('Anyone with the URL can view') }}</div>
                        </div>
                    </label>
                    <label class="label cursor-pointer justify-start gap-3 py-2">
                        <input type="radio" wire:model="visibility" value="unlisted" class="radio radio-primary" />
                        <div class="flex-1">
                            <div class="font-medium">{{ __('Unlisted') }}</div>
                            <div class="text-xs text-base-content/50">{{ __('Requires a secret link to access') }}</div>
                        </div>
                    </label>
                    <label class="label cursor-pointer justify-start gap-3 py-2">
                        <input type="radio" wire:model="visibility" value="private" class="radio radio-primary" />
                        <div class="flex-1">
                            <div class="font-medium">{{ __('Private') }}</div>
                            <div class="text-xs text-base-content/50">{{ __('Only you and your team members can view') }}</div>
                        </div>
                    </label>
                </div>
            </div>
        </div>

        @if ($teams->count() > 0)
            <div class="card bg-base-100 border border-base-300 mb-4">
                <div class="card-body gap-5">
                    <h3 class="text-sm font-semibold uppercase tracking-wider text-base-content/50">{{ __('Ownership') }}</h3>

                    <div class="form-control">
                        <label class="label pb-1">
                            <span class="label-text font-medium">{{ __('Team') }}</span>
                            <span class="label-text-alt text-base-content/50">{{ __('Optional') }}</span>
                        </label>
                        <select wire:model="teamId" class="select select-bordered w-full rounded-lg">
                            <option value="">{{ __('Personal — only you can manage') }}</option>
                            @foreach ($teams as $team)
                                <option value="{{ $team->id }}">{{ $team->name }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>
            </div>
        @endif

        <div class="flex gap-3 justify-end pt-2">
            <a href="{{ route('status-pages.index') }}" wire:navigate class="btn btn-ghost">{{ __('Cancel') }}</a>
            <button type="submit" class="btn btn-primary">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                </svg>
                {{ __('Create Status Page') }}
            </button>
        </div>
    </form>
</div>
