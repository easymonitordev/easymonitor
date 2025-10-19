<div class="w-full">
    <div class="mb-6">
        <h2 class="text-2xl font-bold">{{ __('Teams') }}</h2>
        <p class="text-base-content/70 mt-1">{{ __('Manage your teams and team memberships') }}</p>
    </div>

    <x-alert-success />
    <x-alert-error />

    <div class="flex justify-end mb-6">
        <a href="{{ route('teams.create') }}" wire:navigate class="btn btn-primary rounded-lg">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
            </svg>
            {{ __('Create Team') }}
        </a>
    </div>

    <div class="space-y-8">
        @if ($ownedTeams->count() > 0)
            <div>
                <h3 class="text-lg font-semibold mb-4">{{ __('Teams You Own') }}</h3>
                <div class="grid gap-4">
                    @foreach ($ownedTeams as $team)
                        <div class="card bg-base-100 border border-base-300">
                            <div class="card-body">
                                <div class="flex items-start justify-between">
                                    <div class="flex-1">
                                        <h4 class="card-title">{{ $team->name }}</h4>
                                        @if ($team->description)
                                            <p class="text-base-content/70 mt-2">{{ $team->description }}</p>
                                        @endif
                                        <div class="flex items-center gap-2 mt-3">
                                            <div class="badge badge-primary">{{ __('Owner') }}</div>
                                            <div class="badge badge-ghost">
                                                {{ trans_choice(':count member|:count members', $team->users->count(), ['count' => $team->users->count()]) }}
                                            </div>
                                        </div>
                                    </div>
                                    <div class="flex gap-2">
                                        <a href="{{ route('teams.members', $team) }}" wire:navigate class="btn btn-sm btn-ghost rounded-lg">
                                            {{ __('Members') }}
                                        </a>
                                        <a href="{{ route('teams.edit', $team) }}" wire:navigate class="btn btn-sm btn-ghost rounded-lg">
                                            {{ __('Edit') }}
                                        </a>
                                        <button
                                            wire:click="delete({{ $team->id }})"
                                            wire:confirm="{{ __('Are you sure you want to delete this team?') }}"
                                            class="btn btn-sm btn-ghost text-error rounded-lg"
                                        >
                                            {{ __('Delete') }}
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        @endif

        @if ($memberTeams->count() > 0)
            <div>
                <h3 class="text-lg font-semibold mb-4">{{ __('Teams You Belong To') }}</h3>
                <div class="grid gap-4">
                    @foreach ($memberTeams as $team)
                        <div class="card bg-base-100 border border-base-300">
                            <div class="card-body">
                                <div class="flex items-start justify-between">
                                    <div class="flex-1">
                                        <h4 class="card-title">{{ $team->name }}</h4>
                                        @if ($team->description)
                                            <p class="text-base-content/70 mt-2">{{ $team->description }}</p>
                                        @endif
                                        <div class="flex items-center gap-2 mt-3">
                                            <div class="badge {{ $team->pivot->role === 'admin' ? 'badge-secondary' : 'badge-ghost' }}">
                                                {{ ucfirst($team->pivot->role) }}
                                            </div>
                                            <div class="text-sm text-base-content/70">
                                                {{ __('Owner:') }} {{ $team->owner->name }}
                                            </div>
                                        </div>
                                    </div>
                                    <div class="flex gap-2">
                                        <a href="{{ route('teams.members', $team) }}" wire:navigate class="btn btn-sm btn-ghost rounded-lg">
                                            {{ __('Members') }}
                                        </a>
                                    </div>
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        @endif

        @if ($ownedTeams->count() === 0 && $memberTeams->count() === 0)
            <div class="text-center py-12">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-16 w-16 mx-auto text-base-content/20" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z" />
                </svg>
                <h3 class="mt-4 text-lg font-semibold">{{ __('No teams yet') }}</h3>
                <p class="text-base-content/70 mt-2">{{ __('Get started by creating your first team') }}</p>
                <div class="mt-6">
                    <a href="{{ route('teams.create') }}" wire:navigate class="btn btn-primary rounded-lg">
                        {{ __('Create Your First Team') }}
                    </a>
                </div>
            </div>
        @endif
    </div>
</div>
