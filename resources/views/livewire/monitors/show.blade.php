<div class="w-full" wire:poll.30s>
    <!-- Header -->
    <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between mb-6 gap-4">
        <div>
            <div class="flex items-center gap-3">
                <a href="{{ route('monitors.index') }}" wire:navigate class="btn btn-ghost btn-sm btn-circle">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" />
                    </svg>
                </a>
                <h2 class="text-2xl font-bold">{{ $monitor->name }}</h2>
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
                @if ($monitor->project)
                    <a href="{{ route('projects.show', $monitor->project) }}" wire:navigate class="badge badge-ghost gap-1 hover:badge-neutral">
                        @if ($monitor->project->color)
                            <div class="w-1.5 h-1.5 rounded-full" style="background: {{ $monitor->project->color }}"></div>
                        @endif
                        {{ $monitor->project->name }}
                    </a>
                @endif
            </div>
            <p class="text-sm text-base-content/70 mt-1 ml-12">{{ $monitor->url }}</p>
        </div>
        <a href="{{ route('monitors.edit', $monitor) }}" wire:navigate class="btn btn-ghost btn-sm">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
            </svg>
            {{ __('Edit') }}
        </a>
    </div>

    @if ($monitor->last_error)
        <div class="alert alert-error mb-6">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 shrink-0 stroke-current" fill="none" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 14l2-2m0 0l2-2m-2 2l-2-2m2 2l2 2m7-2a9 9 0 11-18 0 9 9 0 0118 0z" />
            </svg>
            <span>{{ $monitor->last_error }}</span>
        </div>
    @endif

    <!-- Period Selector -->
    <div class="flex gap-2 mb-6">
        <div role="tablist" class="tabs tabs-boxed">
            @foreach (['1h' => '1 Hour', '24h' => '24 Hours', '7d' => '7 Days', '30d' => '30 Days'] as $value => $label)
                <a role="tab"
                   class="tab @if($period === $value) tab-active @endif"
                   wire:click="$set('period', '{{ $value }}')">
                    {{ __($label) }}
                </a>
            @endforeach
        </div>
    </div>

    <!-- Stats Cards -->
    <div class="grid gap-4 md:grid-cols-4 mb-6">
        <div class="stats bg-base-100 border border-base-300 rounded-xl">
            <div class="stat">
                <div class="stat-figure text-success">
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
                <div class="stat-desc">{{ $totalChecks }} {{ __('checks') }}</div>
            </div>
        </div>

        <div class="stats bg-base-100 border border-base-300 rounded-xl">
            <div class="stat">
                <div class="stat-figure text-base-content/30">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                </div>
                <div class="stat-title">{{ __('Check Interval') }}</div>
                <div class="stat-value text-2xl">{{ $monitor->check_interval }}<span class="text-lg">s</span></div>
                <div class="stat-desc">{{ $monitor->is_active ? __('Active') : __('Paused') }}</div>
            </div>
        </div>

        <div class="stats bg-base-100 border border-base-300 rounded-xl">
            <div class="stat">
                <div class="stat-figure text-base-content/30">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z" />
                    </svg>
                </div>
                <div class="stat-title">{{ __('Last Checked') }}</div>
                <div class="stat-value text-xl">{{ $monitor->last_checked_at?->diffForHumans(short: true) ?? '--' }}</div>
                <div class="stat-desc">{{ $monitor->last_checked_at?->format('M d, H:i') ?? __('Never') }}</div>
            </div>
        </div>

        <div class="stats bg-base-100 border border-base-300 rounded-xl">
            <div class="stat">
                <div class="stat-figure text-base-content/30">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                    </svg>
                </div>
                <div class="stat-title">{{ __('Created') }}</div>
                <div class="stat-value text-xl">{{ $monitor->created_at->diffForHumans(short: true) }}</div>
                <div class="stat-desc">{{ $monitor->created_at->format('M d, Y') }}</div>
            </div>
        </div>
    </div>

    <!-- Response Time Chart -->
    <div class="card bg-base-100 border border-base-300 mb-6 overflow-visible">
        <div class="card-body overflow-visible">
            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-2 mb-4">
                <h3 class="card-title text-lg">{{ __('Response Time') }}</h3>
                @if ($avgResponseTime !== null)
                    <div class="flex gap-4 text-sm">
                        <span class="text-base-content/50">{{ __('Avg') }} <span class="font-semibold text-base-content">{{ $avgResponseTime }}ms</span></span>
                        <span class="text-base-content/50">{{ __('Min') }} <span class="font-semibold text-success">{{ $minResponseTime }}ms</span></span>
                        <span class="text-base-content/50">{{ __('Max') }} <span class="font-semibold {{ $maxResponseTime > 1000 ? 'text-warning' : 'text-base-content' }}">{{ $maxResponseTime }}ms</span></span>
                    </div>
                @endif
            </div>

            @if ($chartData->isEmpty() || $chartData->count() < 3)
                <div class="text-center py-10">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-10 w-10 mx-auto text-base-content/20" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z" />
                    </svg>
                    <p class="mt-3 text-sm text-base-content/50">{{ __('Collecting data... The chart will appear once enough check results have been gathered.') }}</p>
                </div>
            @else
                @php
                    $chartHeight = 120; // px
                    $maxMs = $chartData->max('ms') ?: 1;
                @endphp
                <div class="inline-block">
                    <div class="flex items-end gap-0.5" style="height: {{ $chartHeight }}px">
                        @foreach ($chartData as $point)
                            @php
                                $barHeight = $point['ms'] > 0
                                    ? max((int) round(($point['ms'] / $maxMs) * $chartHeight), 4)
                                    : 4;
                            @endphp
                            <div class="relative group" style="width: 1.5rem"
                                 wire:key="chart-{{ $loop->index }}">
                                <div class="absolute bottom-full left-1/2 -translate-x-1/2 mb-2 hidden group-hover:block z-50 pointer-events-none">
                                    <div class="bg-neutral text-neutral-content text-xs rounded px-2 py-1 whitespace-nowrap shadow-lg">
                                        {{ $point['time'] }} — {{ $point['ms'] }}ms
                                        @if (!$point['up'])
                                            ({{ __('Down') }})
                                        @endif
                                    </div>
                                </div>
                                <div class="w-full rounded-sm {{ $point['up'] ? 'bg-success' : 'bg-error' }}"
                                     style="height: {{ $barHeight }}px">
                                </div>
                            </div>
                        @endforeach
                    </div>
                    <div class="flex justify-between mt-2 text-xs text-base-content/40">
                        <span>{{ $chartData->first()['time'] }}</span>
                        <span>{{ $chartData->last()['time'] }}</span>
                    </div>
                </div>
            @endif
        </div>
    </div>

    <!-- Check History -->
    <div class="card bg-base-100 border border-base-300">
        <div class="card-body">
            <h3 class="card-title text-lg mb-4">{{ __('Check History') }}</h3>

            @if ($checkResults->isEmpty())
                <div class="text-center py-8">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-12 w-12 mx-auto text-base-content/30" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                    <p class="mt-3 text-base-content/70">{{ __('No check results yet for this period.') }}</p>
                </div>
            @else
                <div class="text-sm text-base-content/50 mb-3">
                    {{ __('Showing :from-:to of :total results', [
                        'from' => $checkResults->firstItem() ?? 0,
                        'to' => $checkResults->lastItem() ?? 0,
                        'total' => $checkResults->total(),
                    ]) }}
                </div>
                <div class="overflow-x-auto">
                    <table class="table table-sm">
                        <thead>
                            <tr>
                                <th>{{ __('Status') }}</th>
                                <th>{{ __('Response Time') }}</th>
                                <th>{{ __('Status Code') }}</th>
                                <th>{{ __('Node') }}</th>
                                <th>{{ __('Checked At') }}</th>
                                <th>{{ __('Error') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($checkResults as $result)
                                <tr wire:key="result-{{ $result->id }}-{{ $result->created_at->timestamp }}">
                                    <td>
                                        @if ($result->is_up)
                                            <div class="badge badge-success badge-sm">{{ __('Up') }}</div>
                                        @else
                                            <div class="badge badge-error badge-sm">{{ __('Down') }}</div>
                                        @endif
                                    </td>
                                    <td>
                                        @if ($result->response_time_ms)
                                            <span class="{{ $result->response_time_ms > 1000 ? 'text-warning' : '' }}">
                                                {{ $result->response_time_ms }}ms
                                            </span>
                                        @else
                                            <span class="text-base-content/40">--</span>
                                        @endif
                                    </td>
                                    <td>
                                        @if ($result->status_code)
                                            <span class="{{ $result->status_code >= 400 ? 'text-error' : '' }}">
                                                {{ $result->status_code }}
                                            </span>
                                        @else
                                            <span class="text-base-content/40">--</span>
                                        @endif
                                    </td>
                                    <td class="text-xs text-base-content/60">{{ $result->node_id }}</td>
                                    <td class="text-sm">{{ $result->created_at->format('M d, H:i:s') }}</td>
                                    <td>
                                        @if ($result->error_message)
                                            <span class="text-xs text-error truncate max-w-xs inline-block">{{ $result->error_message }}</span>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                @if ($checkResults->hasPages())
                    <div class="mt-4">
                        {{ $checkResults->links('vendor.pagination.tailwind') }}
                    </div>
                @endif
            @endif
        </div>
    </div>
</div>
