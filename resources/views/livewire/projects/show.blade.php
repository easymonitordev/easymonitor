<div class="w-full" wire:poll.30s>
    <!-- Header -->
    <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between mb-6 gap-4">
        <div>
            <div class="flex items-center gap-3">
                <a href="{{ route('projects.index') }}" wire:navigate class="btn btn-ghost btn-sm btn-circle">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" />
                    </svg>
                </a>
                @if ($project->color)
                    <div class="w-3 h-3 rounded-full shrink-0" style="background: {{ $project->color }}"></div>
                @endif
                <h2 class="text-2xl font-bold">{{ $project->name }}</h2>
                @if ($status === 'operational')
                    <div class="badge badge-success gap-2"><div class="w-2 h-2 rounded-full bg-success-content"></div>{{ __('Operational') }}</div>
                @elseif ($status === 'degraded')
                    <div class="badge badge-warning gap-2"><div class="w-2 h-2 rounded-full bg-warning-content"></div>{{ __('Degraded') }}</div>
                @elseif ($status === 'outage')
                    <div class="badge badge-error gap-2"><div class="w-2 h-2 rounded-full bg-error-content"></div>{{ __('Outage') }}</div>
                @else
                    <div class="badge badge-ghost gap-2"><div class="w-2 h-2 rounded-full bg-base-content/50"></div>{{ __('No data') }}</div>
                @endif
            </div>
            @if ($project->description)
                <p class="text-sm text-base-content/70 mt-1 ml-12">{{ $project->description }}</p>
            @endif
        </div>
        <a href="{{ route('projects.edit', $project) }}" wire:navigate class="btn btn-ghost btn-sm">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
            </svg>
            {{ __('Edit') }}
        </a>
    </div>

    <!-- Stats -->
    <div class="grid gap-4 md:grid-cols-4 mb-6">
        <div class="stats bg-base-100 border border-base-300 rounded-xl">
            <div class="stat">
                <div class="stat-title">{{ __('Total Monitors') }}</div>
                <div class="stat-value text-2xl">{{ $total }}</div>
            </div>
        </div>
        <div class="stats bg-base-100 border border-base-300 rounded-xl">
            <div class="stat">
                <div class="stat-title">{{ __('Up') }}</div>
                <div class="stat-value text-2xl text-success">{{ $up }}</div>
            </div>
        </div>
        <div class="stats bg-base-100 border border-base-300 rounded-xl">
            <div class="stat">
                <div class="stat-title">{{ __('Down') }}</div>
                <div class="stat-value text-2xl {{ $down > 0 ? 'text-error' : '' }}">{{ $down }}</div>
            </div>
        </div>
        <div class="stats bg-base-100 border border-base-300 rounded-xl">
            <div class="stat">
                <div class="stat-title">{{ __('Pending') }}</div>
                <div class="stat-value text-2xl text-base-content/60">{{ $pending }}</div>
            </div>
        </div>
    </div>

    <!-- Monitors -->
    <div class="card bg-base-100 border border-base-300">
        <div class="card-body">
            <div class="flex items-center justify-between mb-4">
                <h3 class="card-title text-lg">{{ __('Monitors') }}</h3>
                <a href="{{ route('monitors.create') }}" wire:navigate class="btn btn-primary btn-sm">
                    {{ __('Add Monitor') }}
                </a>
            </div>

            @if ($monitors->isEmpty())
                <div class="text-center py-10">
                    <p class="text-sm text-base-content/50">{{ __('No monitors in this project yet. Create one and assign it to this project.') }}</p>
                </div>
            @else
                <div class="space-y-1">
                    @foreach ($monitors as $monitor)
                        <a href="{{ route('monitors.show', $monitor) }}" wire:navigate wire:key="monitor-{{ $monitor->id }}"
                           class="flex items-center gap-3 px-3 py-2.5 rounded-lg hover:bg-base-200 transition-colors group">
                            @if ($monitor->status === 'up')
                                <div class="w-2.5 h-2.5 rounded-full bg-success shrink-0"></div>
                            @elseif ($monitor->status === 'down')
                                <div class="w-2.5 h-2.5 rounded-full bg-error animate-pulse shrink-0"></div>
                            @else
                                <div class="w-2.5 h-2.5 rounded-full bg-base-content/30 shrink-0"></div>
                            @endif

                            <div class="flex-1 min-w-0">
                                <div class="font-medium truncate group-hover:underline">{{ $monitor->name }}</div>
                                <div class="text-xs text-base-content/50 truncate">{{ $monitor->url }}</div>
                            </div>

                            <div class="text-sm text-base-content/60 shrink-0">
                                @if ($monitor->latestCheckResult?->response_time_ms)
                                    {{ $monitor->latestCheckResult->response_time_ms }}ms
                                @endif
                            </div>

                            <div class="text-xs text-base-content/40 w-16 text-right shrink-0">
                                {{ $monitor->last_checked_at?->diffForHumans(short: true) ?? __('Never') }}
                            </div>

                            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 text-base-content/20 group-hover:text-base-content/50 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
                            </svg>
                        </a>
                    @endforeach
                </div>
            @endif
        </div>
    </div>
</div>
