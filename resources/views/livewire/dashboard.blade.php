<div class="w-full" wire:poll.30s>
    <div class="mb-6">
        <h2 class="text-2xl font-bold">{{ __('Dashboard') }}</h2>
        <p class="text-base-content/70 mt-1">{{ __('Overview of your monitoring status') }}</p>
    </div>

    <!-- Overall Status Banner -->
    @if ($totalMonitors > 0)
        @php($bannerClasses = $monitorsDown > 0
            ? 'bg-error/10 border-error/30'
            : ($monitorsDegraded > 0 ? 'bg-warning/10 border-warning/30' : 'bg-success/10 border-success/30'))
        <div class="mb-6 rounded-xl p-4 border {{ $bannerClasses }}">
            <div class="flex items-center gap-3 flex-wrap">
                @if ($monitorsDown > 0)
                    <div class="w-3 h-3 rounded-full bg-error animate-pulse"></div>
                    <span class="font-semibold text-error">{{ $monitorsDown }} {{ trans_choice('monitor is down|monitors are down', $monitorsDown) }}</span>
                    @if ($monitorsDegraded > 0)
                        <span class="text-sm text-warning">&middot; {{ $monitorsDegraded }} {{ __('degraded') }}</span>
                    @endif
                @elseif ($monitorsDegraded > 0)
                    <div class="w-3 h-3 rounded-full bg-warning animate-pulse"></div>
                    <span class="font-semibold text-warning">{{ $monitorsDegraded }} {{ trans_choice('monitor is degraded|monitors are degraded', $monitorsDegraded) }}</span>
                    <span class="text-sm text-base-content/60">{{ __('some probes failing') }}</span>
                @else
                    <div class="w-3 h-3 rounded-full bg-success"></div>
                    <span class="font-semibold text-success">{{ __('All systems operational') }}</span>
                @endif
                @if ($monitorsPending > 0)
                    <span class="text-sm text-base-content/50">&middot; {{ $monitorsPending }} {{ __('pending') }}</span>
                @endif
            </div>
        </div>
    @endif

    <!-- Stats Cards -->
    <div class="grid gap-4 md:grid-cols-4 mb-6">
        <div class="stats bg-base-100 border border-base-300 rounded-xl">
            <div class="stat">
                <div class="stat-figure text-primary">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z" />
                    </svg>
                </div>
                <div class="stat-title">{{ __('Monitors') }}</div>
                <div class="stat-value text-2xl">{{ $monitorsUp }}<span class="text-base-content/30 text-lg">/{{ $totalMonitors }}</span></div>
                <div class="stat-desc">{{ $activeMonitors }} {{ __('active') }}</div>
            </div>
        </div>

        <div class="stats bg-base-100 border border-base-300 rounded-xl">
            <div class="stat">
                <div class="stat-figure {{ $uptimePercentage !== null && $uptimePercentage < 99 ? 'text-warning' : 'text-success' }}">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                </div>
                <div class="stat-title">{{ __('Uptime') }}</div>
                <div class="stat-value text-2xl {{ $uptimePercentage !== null && $uptimePercentage < 99 ? 'text-warning' : 'text-success' }}">
                    @if ($uptimePercentage !== null)
                        {{ $uptimePercentage }}%
                    @else
                        <span class="text-base-content/40">--</span>
                    @endif
                </div>
                <div class="stat-desc">{{ __('Last 24 hours') }}</div>
            </div>
        </div>

        <div class="stats bg-base-100 border border-base-300 rounded-xl">
            <div class="stat">
                <div class="stat-figure text-base-content/30">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z" />
                    </svg>
                </div>
                <div class="stat-title">{{ __('Avg Response') }}</div>
                <div class="stat-value text-2xl">
                    @if ($avgResponseTime !== null)
                        {{ $avgResponseTime }}<span class="text-lg">ms</span>
                    @else
                        <span class="text-base-content/40">--</span>
                    @endif
                </div>
                <div class="stat-desc">{{ __('Last 24 hours') }}</div>
            </div>
        </div>

        <div class="stats bg-base-100 border border-base-300 rounded-xl">
            <div class="stat">
                <div class="stat-figure text-error">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.964-.833-2.732 0L4.082 16.5c-.77.833.192 2.5 1.732 2.5z" />
                    </svg>
                </div>
                <div class="stat-title">{{ __('Incidents') }}</div>
                <div class="stat-value text-2xl {{ $downIncidentsCount > 0 ? 'text-error' : ($degradedIncidentsCount > 0 ? 'text-warning' : '') }}">{{ $downIncidentsCount }}</div>
                <div class="stat-desc">
                    {{ __('Last 24 hours') }}
                    @if ($degradedIncidentsCount > 0)
                        <span class="text-warning">· {{ $degradedIncidentsCount }} {{ __('degraded') }}</span>
                    @endif
                </div>
            </div>
        </div>
    </div>

    <!-- Main Content -->
    <div class="grid gap-4 lg:grid-cols-3">
        <!-- Monitors List -->
        <div class="lg:col-span-2">
            <div class="card bg-base-100 border border-base-300">
                <div class="card-body">
                    <div class="flex items-center justify-between mb-4">
                        <h3 class="card-title text-lg">{{ __('Monitors') }}</h3>
                        <a href="{{ route('monitors.create') }}" wire:navigate class="btn btn-primary btn-sm">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                            </svg>
                            {{ __('Add Monitor') }}
                        </a>
                    </div>

                    @if ($monitors->isEmpty())
                        <div class="text-center py-12">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-16 w-16 mx-auto text-base-content/20" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z" />
                            </svg>
                            <h3 class="mt-4 text-lg font-semibold">{{ __('No monitors yet') }}</h3>
                            <p class="text-base-content/50 mt-1">{{ __('Add your first monitor to start tracking uptime') }}</p>
                            <a href="{{ route('monitors.create') }}" wire:navigate class="btn btn-primary btn-sm mt-4">
                                {{ __('Add Monitor') }}
                            </a>
                        </div>
                    @else
                        <div class="space-y-1">
                            @foreach ($monitors as $monitor)
                                <a href="{{ route('monitors.show', $monitor) }}" wire:navigate wire:key="monitor-{{ $monitor->id }}"
                                   class="flex items-center gap-3 px-3 py-2.5 rounded-lg hover:bg-base-200 transition-colors group">
                                    <!-- Status dot -->
                                    @if ($monitor->status === 'up')
                                        <div class="w-2.5 h-2.5 rounded-full bg-success shrink-0"></div>
                                    @elseif ($monitor->status === 'down')
                                        <div class="w-2.5 h-2.5 rounded-full bg-error animate-pulse shrink-0"></div>
                                    @else
                                        <div class="w-2.5 h-2.5 rounded-full bg-base-content/30 shrink-0"></div>
                                    @endif

                                    <!-- Name -->
                                    <div class="flex-1 min-w-0">
                                        <div class="font-medium truncate group-hover:underline">{{ $monitor->name }}</div>
                                    </div>

                                    <!-- Response time -->
                                    <div class="text-sm text-base-content/60 shrink-0">
                                        @if ($monitor->latestCheckResult?->response_time_ms)
                                            <span class="{{ $monitor->latestCheckResult->response_time_ms > 1000 ? 'text-warning' : '' }}">
                                                {{ $monitor->latestCheckResult->response_time_ms }}ms
                                            </span>
                                        @else
                                            --
                                        @endif
                                    </div>

                                    <!-- Last checked -->
                                    <div class="text-xs text-base-content/40 w-16 text-right shrink-0">
                                        {{ $monitor->last_checked_at?->diffForHumans(short: true) ?? __('Never') }}
                                    </div>

                                    <!-- Arrow -->
                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 text-base-content/20 group-hover:text-base-content/50 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
                                    </svg>
                                </a>
                            @endforeach
                        </div>

                        <div class="mt-4 pt-3 border-t border-base-200">
                            <a href="{{ route('monitors.index') }}" wire:navigate class="text-sm text-primary hover:underline">
                                {{ __('View all monitors') }} &rarr;
                            </a>
                        </div>
                    @endif
                </div>
            </div>
        </div>

        <!-- Recent Incidents -->
        <div>
            <div class="card bg-base-100 border border-base-300 h-full">
                <div class="card-body">
                    <h3 class="card-title text-lg mb-4">{{ __('Recent Incidents') }}</h3>

                    @if ($recentIncidents->isEmpty())
                        <div class="flex-1 flex flex-col items-center justify-center py-8">
                            <div class="w-12 h-12 rounded-full bg-success/10 flex items-center justify-center mb-3">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6 text-success" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                                </svg>
                            </div>
                            <p class="text-sm font-medium">{{ __('No incidents') }}</p>
                            <p class="text-xs text-base-content/50 mt-1">{{ __('Everything is running smoothly') }}</p>
                        </div>
                    @else
                        <div class="space-y-0">
                            @foreach ($recentIncidents as $incident)
                                @php($isDegraded = $incident->isDegraded())
                                @php($isOngoing = $incident->isOngoing())
                                @php($dotColor = $isDegraded ? 'bg-warning' : 'bg-error')
                                @php($bgColor = $isDegraded ? 'bg-warning/10' : 'bg-error/10')
                                <div class="flex items-start gap-3 py-2.5 {{ !$loop->last ? 'border-b border-base-200' : '' }}" wire:key="incident-{{ $incident->id }}">
                                    <div class="w-5 h-5 rounded-full {{ $bgColor }} flex items-center justify-center mt-0.5 shrink-0">
                                        <div class="w-2 h-2 rounded-full {{ $dotColor }} {{ $isOngoing ? 'animate-pulse' : '' }}"></div>
                                    </div>
                                    <div class="min-w-0 flex-1">
                                        <div class="flex items-center justify-between gap-2">
                                            <div class="flex items-center gap-2 min-w-0">
                                                <span class="text-sm font-medium truncate">{{ $incident->monitor?->name ?? __('Deleted') }}</span>
                                                @if ($isDegraded)
                                                    <span class="badge badge-warning badge-xs">{{ __('Degraded') }}</span>
                                                @else
                                                    <span class="badge badge-error badge-xs">{{ __('Down') }}</span>
                                                @endif
                                            </div>
                                            <span class="text-xs text-base-content/40 shrink-0">{{ $incident->started_at->diffForHumans(short: true) }}</span>
                                        </div>
                                        <div class="text-xs text-base-content/50 truncate mt-0.5">
                                            {{ $incident->error_message ?? __('Check failed') }}
                                        </div>
                                        @if (!empty($incident->affected_node_ids))
                                            <div class="text-xs text-base-content/40 mt-0.5">
                                                {{ __('Affected') }}:
                                                @foreach ($incident->affected_node_ids as $node)
                                                    <span class="font-mono">{{ $node }}</span>{{ !$loop->last ? ',' : '' }}
                                                @endforeach
                                            </div>
                                        @endif
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>
</div>
