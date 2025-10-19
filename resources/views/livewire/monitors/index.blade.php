<div class="w-full">
    <div class="mb-6">
        <h2 class="text-2xl font-bold">{{ __('Monitors') }}</h2>
        <p class="text-base-content/70 mt-1">{{ __('Monitor your websites and get notified when they go down') }}</p>
    </div>

    <x-alert-success />
    <x-alert-error />

    <div class="flex flex-col sm:flex-row gap-4 justify-between mb-6">
        <div class="flex gap-2">
            <div role="tablist" class="tabs tabs-boxed">
                <a role="tab" class="tab @if($filter === 'my') tab-active @endif" wire:click="$set('filter', 'my')">
                    {{ __('My Monitors') }}
                </a>
                @if ($teams->count() > 0)
                    <a role="tab" class="tab @if($filter === 'team') tab-active @endif" wire:click="$set('filter', 'team')">
                        {{ __('Team Monitors') }}
                    </a>
                @endif
            </div>

            @if ($filter === 'team' && $teams->count() > 0)
                <select wire:model.live="selectedTeamId" class="select select-bordered select-sm">
                    <option value="">{{ __('Select Team') }}</option>
                    @foreach ($teams as $team)
                        <option value="{{ $team->id }}">{{ $team->name }}</option>
                    @endforeach
                </select>
            @endif
        </div>

        <div class="flex items-end">
            <a href="{{ route('monitors.create') }}" wire:navigate class="btn btn-primary rounded-lg">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                </svg>
                {{ __('Add Monitor') }}
            </a>
        </div>
    </div>

    @if ($monitors->count() === 0)
        <div class="card bg-base-100 border border-base-300">
            <div class="card-body text-center py-12">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-16 w-16 mx-auto text-base-content/30" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                </svg>
                <h3 class="text-lg font-semibold mt-4">{{ __('No monitors yet') }}</h3>
                <p class="text-base-content/70 mt-2">{{ __('Get started by adding your first website monitor') }}</p>
                <div class="mt-6">
                    <a href="{{ route('monitors.create') }}" wire:navigate class="btn btn-primary">
                        {{ __('Add Monitor') }}
                    </a>
                </div>
            </div>
        </div>
    @else
        <div class="grid gap-4">
            @foreach ($monitors as $monitor)
                <div class="card bg-base-100 border border-base-300">
                    <div class="card-body">
                        <div class="flex items-start justify-between">
                            <div class="flex-1">
                                <div class="flex items-center gap-3">
                                    <h4 class="card-title">{{ $monitor->name }}</h4>
                                    @if ($monitor->status === 'up')
                                        <div class="badge badge-success gap-2">
                                            <div class="w-2 h-2 rounded-full bg-success-content"></div>
                                            {{ __('Up') }}
                                        </div>
                                    @elseif ($monitor->status === 'down')
                                        <div class="badge badge-error gap-2">
                                            <div class="w-2 h-2 rounded-full bg-error-content"></div>
                                            {{ __('Down') }}
                                        </div>
                                    @else
                                        <div class="badge badge-ghost gap-2">
                                            <div class="w-2 h-2 rounded-full bg-base-content/50"></div>
                                            {{ __('Pending') }}
                                        </div>
                                    @endif

                                    @if (!$monitor->is_active)
                                        <div class="badge badge-warning">{{ __('Inactive') }}</div>
                                    @endif
                                </div>

                                <p class="text-sm text-base-content/70 mt-2">{{ $monitor->url }}</p>

                                <div class="flex gap-4 mt-3 text-sm text-base-content/60">
                                    <span>{{ __('Check every') }} {{ $monitor->check_interval }}s</span>
                                    @if ($monitor->last_checked_at)
                                        <span>{{ __('Last checked') }} {{ $monitor->last_checked_at->diffForHumans() }}</span>
                                    @endif
                                </div>

                                @if ($monitor->last_error)
                                    <div class="alert alert-error mt-3">
                                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 shrink-0 stroke-current" fill="none" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 14l2-2m0 0l2-2m-2 2l-2-2m2 2l2 2m7-2a9 9 0 11-18 0 9 9 0 0118 0z" />
                                        </svg>
                                        <span class="text-sm">{{ $monitor->last_error }}</span>
                                    </div>
                                @endif
                            </div>

                            <div class="dropdown dropdown-end">
                                <div tabindex="0" role="button" class="btn btn-ghost btn-sm">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 5v.01M12 12v.01M12 19v.01M12 6a1 1 0 110-2 1 1 0 010 2zm0 7a1 1 0 110-2 1 1 0 010 2zm0 7a1 1 0 110-2 1 1 0 010 2z" />
                                    </svg>
                                </div>
                                <ul tabindex="0" class="dropdown-content menu bg-base-100 rounded-box z-[1] w-52 p-2 shadow border border-base-300">
                                    <li>
                                        <a href="{{ route('monitors.edit', $monitor) }}" wire:navigate>
                                            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                                            </svg>
                                            {{ __('Edit') }}
                                        </a>
                                    </li>
                                    <li>
                                        <button wire:click="toggleActive({{ $monitor->id }})" wire:confirm="{{ __('Are you sure you want to toggle this monitor?') }}">
                                            @if ($monitor->is_active)
                                                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 9v6m4-6v6m7-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                                                </svg>
                                                {{ __('Pause') }}
                                            @else
                                                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14.752 11.168l-3.197-2.132A1 1 0 0010 9.87v4.263a1 1 0 001.555.832l3.197-2.132a1 1 0 000-1.664z" />
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                                                </svg>
                                                {{ __('Resume') }}
                                            @endif
                                        </button>
                                    </li>
                                    <li>
                                        <button wire:click="delete({{ $monitor->id }})" wire:confirm="{{ __('Are you sure you want to delete this monitor?') }}" class="text-error">
                                            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                            </svg>
                                            {{ __('Delete') }}
                                        </button>
                                    </li>
                                </ul>
                            </div>
                        </div>
                    </div>
                </div>
            @endforeach
        </div>
    @endif
</div>
