<div class="w-full">
    <div class="mb-6">
        <div class="flex items-center gap-3">
            <a href="{{ route('status-pages.manage', ['statusPage' => $statusPage, 'tab' => 'incidents']) }}" wire:navigate class="btn btn-ghost btn-sm btn-circle">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" />
                </svg>
            </a>
            <div>
                <div class="flex items-center gap-2">
                    <h2 class="text-2xl font-bold">{{ $incident->title }}</h2>
                    @if ($incident->type === 'maintenance')
                        <span class="badge badge-info badge-sm">{{ __('Maintenance') }}</span>
                    @else
                        <span class="badge {{ $incident->resolved_at ? 'badge-ghost' : 'badge-error' }} badge-sm">{{ __('Incident') }}</span>
                    @endif
                </div>
                <p class="text-sm text-base-content/50 mt-0.5">{{ $statusPage->name }} · {{ $incident->created_at->format('M d, Y H:i') }}</p>
            </div>
        </div>
    </div>

    <x-alert-success />
    <x-alert-error />

    <div class="grid lg:grid-cols-3 gap-4 max-w-6xl">
        <!-- Edit form -->
        <div class="lg:col-span-2">
            <form wire:submit="save">
                <div class="card bg-base-100 border border-base-300 mb-4">
                    <div class="card-body gap-5">
                        <h3 class="text-sm font-semibold uppercase tracking-wider text-base-content/50">{{ __('Edit') }}</h3>

                        <div class="form-control">
                            <label class="label pb-1"><span class="label-text font-medium">{{ __('Title') }}</span></label>
                            <input type="text" wire:model="title" class="input input-bordered w-full rounded-lg @error('title') input-error @enderror" />
                            @error('title')<div class="label pb-0"><span class="label-text-alt text-error">{{ $message }}</span></div>@enderror
                        </div>

                        <div class="form-control">
                            <label class="label pb-1"><span class="label-text font-medium">{{ __('Description') }}</span></label>
                            <textarea wire:model="body" rows="5" class="textarea textarea-bordered w-full rounded-lg"></textarea>
                        </div>

                        <div class="grid grid-cols-2 gap-3">
                            <div class="form-control">
                                <label class="label pb-1"><span class="label-text font-medium">{{ __('Status') }}</span></label>
                                <select wire:model="status" class="select select-bordered w-full rounded-lg">
                                    @if ($incident->type === 'incident')
                                        <option value="investigating">{{ __('Investigating') }}</option>
                                        <option value="identified">{{ __('Identified') }}</option>
                                        <option value="monitoring">{{ __('Monitoring') }}</option>
                                        <option value="resolved">{{ __('Resolved') }}</option>
                                    @else
                                        <option value="scheduled">{{ __('Scheduled') }}</option>
                                        <option value="in_progress">{{ __('In Progress') }}</option>
                                        <option value="completed">{{ __('Completed') }}</option>
                                    @endif
                                </select>
                            </div>

                            @if ($incident->type === 'incident')
                                <div class="form-control">
                                    <label class="label pb-1"><span class="label-text font-medium">{{ __('Severity') }}</span></label>
                                    <select wire:model="severity" class="select select-bordered w-full rounded-lg">
                                        <option value="minor">{{ __('Minor') }}</option>
                                        <option value="major">{{ __('Major') }}</option>
                                        <option value="critical">{{ __('Critical') }}</option>
                                    </select>
                                </div>
                            @endif
                        </div>
                    </div>
                </div>

                <div class="flex justify-between items-center pt-2">
                    <button type="button" wire:click="delete" wire:confirm="{{ __('Delete this incident?') }}" class="btn btn-ghost text-error">
                        {{ __('Delete') }}
                    </button>
                    <button type="submit" class="btn btn-primary">{{ __('Save') }}</button>
                </div>
            </form>
        </div>

        <!-- Updates timeline -->
        <div>
            <div class="card bg-base-100 border border-base-300 mb-4">
                <div class="card-body">
                    <h3 class="card-title text-lg mb-3">{{ __('Post Update') }}</h3>
                    <form wire:submit="postUpdate" class="space-y-3">
                        <div class="form-control">
                            <select wire:model="newUpdateStatus" class="select select-bordered select-sm w-full rounded-lg">
                                @if ($incident->type === 'incident')
                                    <option value="investigating">{{ __('Investigating') }}</option>
                                    <option value="identified">{{ __('Identified') }}</option>
                                    <option value="monitoring">{{ __('Monitoring') }}</option>
                                    <option value="resolved">{{ __('Resolved') }}</option>
                                @else
                                    <option value="scheduled">{{ __('Scheduled') }}</option>
                                    <option value="in_progress">{{ __('In Progress') }}</option>
                                    <option value="completed">{{ __('Completed') }}</option>
                                @endif
                            </select>
                        </div>
                        <div class="form-control">
                            <textarea wire:model="newUpdateBody" rows="3" class="textarea textarea-bordered textarea-sm w-full rounded-lg"
                                placeholder="{{ __('Update message...') }}"></textarea>
                        </div>
                        <button type="submit" class="btn btn-primary btn-sm w-full">{{ __('Post Update') }}</button>
                    </form>
                </div>
            </div>

            <div class="card bg-base-100 border border-base-300">
                <div class="card-body">
                    <h3 class="card-title text-lg mb-3">{{ __('Timeline') }}</h3>
                    @if ($updates->isEmpty())
                        <p class="text-sm text-base-content/50">{{ __('No updates yet.') }}</p>
                    @else
                        <div class="space-y-3">
                            @foreach ($updates as $update)
                                <div class="border-l-2 border-base-300 pl-3 pb-2" wire:key="upd-{{ $update->id }}">
                                    <div class="flex items-center gap-2 text-xs text-base-content/50 mb-1">
                                        <span class="badge badge-ghost badge-xs capitalize">{{ str_replace('_', ' ', $update->status_at_update) }}</span>
                                        <span>{{ $update->created_at->diffForHumans() }}</span>
                                    </div>
                                    <p class="text-sm">{{ $update->body }}</p>
                                </div>
                            @endforeach
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>
</div>
