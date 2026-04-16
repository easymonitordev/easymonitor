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
                <div class="stat-desc">
                    {{ $monitor->is_active ? __('Active') : __('Paused') }}
                    @if ($activeProbeCount > 0)
                        · {{ trans_choice(':count probe|:count probes', $activeProbeCount, ['count' => $activeProbeCount]) }}
                    @endif
                </div>
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

    <!-- Uptime Chart -->
    <div class="card bg-base-100 border border-base-300 mb-6 overflow-visible">
        <div class="card-body overflow-visible">
            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-2 mb-4">
                <h3 class="card-title text-lg">{{ __('Uptime') }}</h3>
                @if ($uptimePercentage !== null)
                    <div class="text-sm text-base-content/50">
                        {{ $uptimePercentage }}% {{ __('over period') }}
                    </div>
                @endif
            </div>

            @if ($chartData->isEmpty() || $chartData->count() < 3)
                <div class="text-center py-10">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-10 w-10 mx-auto text-base-content/20" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z" />
                    </svg>
                    <p class="mt-3 text-sm text-base-content/50">{{ __('Collecting data. The chart will appear once enough check results have been gathered.') }}</p>
                </div>
            @else
                @php $chartHeight = 60; @endphp
                <div class="w-full">
                    <div class="flex items-end gap-[2px] w-full" style="height: {{ $chartHeight }}px">
                        @foreach ($chartData as $point)
                            @php
                                $upPct = $point['total'] > 0 ? ($point['up'] / $point['total']) * 100 : 0;
                                $downPct = $point['total'] > 0 ? 100 - $upPct : 0;
                                $hasFailures = $point['down'] > 0;
                                $hasData = $point['total'] > 0;
                            @endphp
                            <div class="relative group flex flex-col justify-end flex-1 min-w-0" style="height: {{ $chartHeight }}px;"
                                 wire:key="chart-{{ $loop->index }}">
                                <div class="absolute bottom-full left-1/2 -translate-x-1/2 mb-2 hidden group-hover:block z-50 pointer-events-none">
                                    <div class="bg-neutral text-neutral-content text-xs rounded px-2 py-1 whitespace-nowrap shadow-lg">
                                        <div class="font-semibold">{{ $point['time'] }}</div>
                                        @if (! $hasData)
                                            <div class="text-base-content/50">{{ __('No data') }}</div>
                                        @elseif ($hasFailures)
                                            <div>{{ $point['down'] }}/{{ $point['total'] }} {{ __('failed') }}</div>
                                            @if (!empty($point['failed_nodes']))
                                                <div class="text-error">{{ implode(', ', $point['failed_nodes']) }}</div>
                                            @endif
                                        @else
                                            <div>{{ $point['total'] }} {{ trans_choice('check ok|checks ok', $point['total']) }}</div>
                                        @endif
                                    </div>
                                </div>
                                @if (! $hasData)
                                    <div class="w-full bg-base-content/10 rounded-sm" style="height: 25%"></div>
                                @else
                                    @if ($downPct > 0)
                                        <div class="w-full bg-error" style="height: {{ $downPct }}%"></div>
                                    @endif
                                    @if ($upPct > 0)
                                        <div class="w-full bg-success {{ $downPct === 0 ? 'rounded-sm' : '' }}" style="height: {{ $upPct }}%"></div>
                                    @endif
                                @endif
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

    <!-- Incidents -->
    <div class="card bg-base-100 border border-base-300 mb-6">
        <div class="card-body">
            <div class="flex items-center justify-between mb-4">
                <h3 class="card-title text-lg">{{ __('Incidents') }}</h3>
                <span class="text-sm text-base-content/50">
                    {{ trans_choice(':count incident|:count incidents', $incidents->count(), ['count' => $incidents->count()]) }}
                </span>
            </div>

            @if ($incidents->isEmpty())
                <div class="text-center py-6 text-sm text-base-content/60">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-8 w-8 mx-auto text-success/50 mb-2" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                    {{ __('No incidents in this period.') }}
                </div>
            @else
                <div class="overflow-x-auto">
                    <table class="table table-sm">
                        <thead>
                            <tr>
                                <th>{{ __('Status') }}</th>
                                <th>{{ __('Started') }}</th>
                                <th>{{ __('Duration') }}</th>
                                <th>{{ __('Reason') }}</th>
                                <th>{{ __('Code') }}</th>
                                <th>{{ __('Node') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($incidents as $incident)
                                <tr wire:key="incident-{{ $incident->id }}">
                                    <td>
                                        @if ($incident->isOngoing())
                                            @if ($incident->isDegraded())
                                                <div class="badge badge-warning badge-sm gap-1">
                                                    <div class="w-1.5 h-1.5 rounded-full bg-warning-content animate-pulse"></div>
                                                    {{ __('Degraded') }}
                                                </div>
                                            @else
                                                <div class="badge badge-error badge-sm gap-1">
                                                    <div class="w-1.5 h-1.5 rounded-full bg-error-content animate-pulse"></div>
                                                    {{ __('Down') }}
                                                </div>
                                            @endif
                                        @else
                                            <div class="badge {{ $incident->isDegraded() ? 'badge-warning badge-outline' : 'badge-ghost' }} badge-sm">{{ __('Resolved') }}</div>
                                        @endif
                                    </td>
                                    <td class="text-sm whitespace-nowrap">
                                        <div>{{ $incident->started_at->format('M d, H:i:s') }}</div>
                                        <div class="text-xs text-base-content/50">{{ $incident->started_at->diffForHumans(short: true) }}</div>
                                    </td>
                                    <td class="text-sm whitespace-nowrap">
                                        @if ($incident->isOngoing())
                                            <span class="text-error">{{ \App\Livewire\Monitors\Show::formatDuration($incident->started_at->diffInSeconds(now())) }}</span>
                                        @else
                                            {{ \App\Livewire\Monitors\Show::formatDuration($incident->duration_seconds) }}
                                        @endif
                                    </td>
                                    <td>
                                        @if ($incident->error_message)
                                            <span class="text-xs text-error truncate max-w-md inline-block" title="{{ $incident->error_message }}">{{ $incident->error_message }}</span>
                                        @else
                                            <span class="text-xs text-base-content/40">--</span>
                                        @endif
                                    </td>
                                    <td>
                                        @if ($incident->status_code)
                                            <span class="text-xs {{ $incident->status_code >= 400 ? 'text-error' : '' }}">{{ $incident->status_code }}</span>
                                        @else
                                            <span class="text-xs text-base-content/40">--</span>
                                        @endif
                                    </td>
                                    <td class="text-xs text-base-content/60">
                                        @if (!empty($incident->affected_node_ids))
                                            @foreach ($incident->affected_node_ids as $node)
                                                <span class="font-mono">{{ $node }}</span>{{ !$loop->last ? ',' : '' }}
                                            @endforeach
                                        @else
                                            {{ $incident->trigger_node_id ?? '--' }}
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>
    </div>

    <!-- Response Time by Node -->
    <div class="card bg-base-100 border border-base-300" wire:key="node-stats-{{ $period }}">
        <div class="card-body">
            <div class="flex items-center justify-between mb-4">
                <h3 class="card-title text-lg">{{ __('Response Time') }}</h3>
                @if ($avgResponseTime !== null)
                    <div class="flex gap-4 text-sm">
                        <span class="text-base-content/50">{{ __('Avg') }} <span class="font-semibold text-base-content">{{ \App\Support\Format::ms($avgResponseTime) }}</span></span>
                        <span class="text-base-content/50">{{ __('Min') }} <span class="font-semibold text-success">{{ \App\Support\Format::ms($minResponseTime) }}</span></span>
                        <span class="text-base-content/50">{{ __('Max') }} <span class="font-semibold {{ $maxResponseTime > 1000 ? 'text-warning' : 'text-base-content' }}">{{ \App\Support\Format::ms($maxResponseTime) }}</span></span>
                    </div>
                @endif
            </div>

            @if ($nodeStats->isEmpty())
                <div class="text-center py-8">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-12 w-12 mx-auto text-base-content/30" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z" />
                    </svg>
                    <p class="mt-3 text-base-content/70">{{ __('No check results yet for this period.') }}</p>
                </div>
            @else
                <div class="overflow-x-auto">
                    <table class="table table-sm">
                        <thead>
                            <tr>
                                <th>{{ __('Node') }}</th>
                                <th class="w-[40%]">{{ __('Trend') }}</th>
                                <th class="text-right">{{ __('Min') }}</th>
                                <th class="text-right">{{ __('Avg') }}</th>
                                <th class="text-right">{{ __('Max') }}</th>
                                <th class="text-right">{{ __('Checks') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($nodeStats as $node)
                                @php
                                    $maxTrend = collect($node['trend'])->filter()->max() ?: 0;
                                    $minTrend = collect($node['trend'])->filter()->min() ?: 0;
                                @endphp
                                <tr wire:key="node-{{ $node['node_id'] }}">
                                    <td class="align-middle">
                                        <div class="flex items-center gap-2">
                                            <div class="w-2 h-2 rounded-full {{ $node['failures'] > 0 ? 'bg-error' : 'bg-success' }}"></div>
                                            <span class="font-mono text-sm">{{ $node['node_id'] }}</span>
                                        </div>
                                    </td>
                                    <td class="align-middle">
                                        <div class="flex items-center gap-3">
                                            <div class="relative"
                                                 style="width: 100%; max-width: 320px; height: 40px;"
                                                 wire:key="trend-{{ $node['node_id'] }}-{{ md5(implode(',', array_map(fn ($v) => $v ?? '', $node['trend']))) }}"
                                                 wire:ignore
                                                 x-data="nodeTrend({{ json_encode(array_values($node['trend'])) }})"
                                                 x-init="mount($refs.canvas)">
                                                <canvas x-ref="canvas" width="320" height="40"></canvas>
                                            </div>
                                            <div class="text-xs text-base-content/50 leading-tight hidden sm:block whitespace-nowrap">
                                                <div>{{ \App\Support\Format::ms($maxTrend) }}</div>
                                                <div>{{ \App\Support\Format::ms($minTrend) }}</div>
                                            </div>
                                        </div>
                                    </td>
                                    @php
                                        $minParts = \App\Support\Format::msParts($node['min']);
                                        $avgParts = \App\Support\Format::msParts($node['avg']);
                                        $maxParts = \App\Support\Format::msParts($node['max']);
                                    @endphp
                                    <td class="text-right align-middle text-sm tabular-nums">{{ $minParts[0] }}<span class="text-base-content/50 text-xs">{{ $minParts[1] }}</span></td>
                                    <td class="text-right align-middle text-sm tabular-nums">{{ $avgParts[0] }}<span class="text-base-content/50 text-xs">{{ $avgParts[1] }}</span></td>
                                    <td class="text-right align-middle text-sm tabular-nums {{ $node['max'] > 1000 ? 'text-warning' : '' }}">{{ $maxParts[0] }}<span class="text-base-content/50 text-xs">{{ $maxParts[1] }}</span></td>
                                    <td class="text-right align-middle text-xs text-base-content/70">
                                        {{ $node['total'] }}
                                        @if ($node['failures'] > 0)
                                            <span class="text-error">({{ $node['failures'] }} {{ __('failed') }})</span>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>
    </div>
</div>

@script
<script>
    window.nodeTrend = (trend) => ({
        chart: null,
        mount(canvas) {
            const labels = trend.map((_, i) => i);
            this.chart = new window.Chart(canvas, {
                type: 'line',
                data: {
                    labels,
                    datasets: [{
                        data: trend,
                        borderColor: '#22c55e',
                        borderWidth: 1.5,
                        pointRadius: 0,
                        tension: 0.35,
                        spanGaps: true,
                        fill: false,
                    }],
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    animation: false,
                    plugins: {
                        legend: { display: false },
                        tooltip: {
                            enabled: true,
                            displayColors: false,
                            callbacks: {
                                title: () => '',
                                label: (ctx) => {
                                    const v = ctx.parsed.y;
                                    if (v === null) return '';
                                    if (v < 1000) return v + 'ms';
                                    const s = (v / 1000).toFixed(1).replace(/\.0$/, '');
                                    return s + 's';
                                },
                            },
                        },
                    },
                    scales: {
                        x: { display: false },
                        y: { display: false, beginAtZero: true },
                    },
                    interaction: { intersect: false, mode: 'index' },
                },
            });

            this.$cleanup(() => this.chart?.destroy());
        },
    });
</script>
@endscript
