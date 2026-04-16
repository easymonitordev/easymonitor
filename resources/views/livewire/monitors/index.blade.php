<div class="w-full" wire:poll.30s>
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
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z" />
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
        <div class="grid gap-3">
            @foreach ($monitors as $monitor)
                <div class="card bg-base-100 border border-base-300 hover:border-base-content/20 transition-colors" wire:key="monitor-{{ $monitor->id }}">
                    <div class="card-body p-4">
                        <div class="flex items-center justify-between">
                            <div class="flex items-center gap-4 flex-1 min-w-0">
                                <!-- Status indicator -->
                                @if ($monitor->status === 'up')
                                    <div class="w-3 h-3 rounded-full bg-success shrink-0" title="{{ __('Up') }}"></div>
                                @elseif ($monitor->status === 'down')
                                    <div class="w-3 h-3 rounded-full bg-error animate-pulse shrink-0" title="{{ __('Down') }}"></div>
                                @else
                                    <div class="w-3 h-3 rounded-full bg-base-content/30 shrink-0" title="{{ __('Pending') }}"></div>
                                @endif

                                <!-- Name & URL -->
                                <a href="{{ route('monitors.show', $monitor) }}" wire:navigate class="flex-1 min-w-0 group">
                                    <div class="flex items-center gap-2">
                                        <span class="font-semibold group-hover:underline truncate">{{ $monitor->name }}</span>
                                        @if ($monitor->project)
                                            <span class="badge badge-ghost badge-sm gap-1">
                                                @if ($monitor->project->color)
                                                    <div class="w-1.5 h-1.5 rounded-full" style="background: {{ $monitor->project->color }}"></div>
                                                @endif
                                                {{ $monitor->project->name }}
                                            </span>
                                        @endif
                                        @if (!$monitor->is_active)
                                            <div class="badge badge-warning badge-sm">{{ __('Paused') }}</div>
                                        @endif
                                    </div>
                                    <div class="text-sm text-base-content/50 truncate">{{ $monitor->url }}</div>
                                </a>

                                <!-- Metrics -->
                                <div class="hidden sm:flex items-center gap-6 text-sm text-base-content/60 shrink-0">
                                    @if ($monitor->latestCheckResult?->response_time_ms)
                                        <div class="text-right">
                                            <div class="font-medium text-base-content {{ $monitor->latestCheckResult->response_time_ms > 1000 ? 'text-warning' : '' }}">
                                                {{ \App\Support\Format::ms($monitor->latestCheckResult->response_time_ms) }}
                                            </div>
                                            <div class="text-xs text-base-content/40">{{ __('response') }}</div>
                                        </div>
                                    @endif
                                    <div class="text-right">
                                        <div class="font-medium text-base-content">{{ $monitor->check_interval }}s</div>
                                        <div class="text-xs text-base-content/40">{{ __('interval') }}</div>
                                    </div>
                                    @if ($monitor->last_checked_at)
                                        <div class="text-right w-24">
                                            <div class="font-medium text-base-content">{{ $monitor->last_checked_at->diffForHumans(short: true) }}</div>
                                            <div class="text-xs text-base-content/40">{{ __('last check') }}</div>
                                        </div>
                                    @endif
                                </div>
                            </div>

                            @if ($monitor->last_error)
                                <div class="tooltip tooltip-left shrink-0 mx-2" data-tip="{{ $monitor->last_error }}">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-error" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.964-.833-2.732 0L4.082 16.5c-.77.833.192 2.5 1.732 2.5z" />
                                    </svg>
                                </div>
                            @endif

                            <div class="dropdown dropdown-end">
                                <div tabindex="0" role="button" class="btn btn-ghost btn-sm">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 5v.01M12 12v.01M12 19v.01M12 6a1 1 0 110-2 1 1 0 010 2zm0 7a1 1 0 110-2 1 1 0 010 2zm0 7a1 1 0 110-2 1 1 0 010 2z" />
                                    </svg>
                                </div>
                                <ul tabindex="0" class="dropdown-content menu bg-base-100 rounded-box z-[1] w-52 p-2 shadow border border-base-300">
                                    <li>
                                        <a href="{{ route('monitors.show', $monitor) }}" wire:navigate>
                                            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                                            </svg>
                                            {{ __('View Details') }}
                                        </a>
                                    </li>
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
