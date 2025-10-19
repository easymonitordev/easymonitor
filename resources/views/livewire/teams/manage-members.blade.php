<div class="w-full">
    <div class="mb-6">
        <h2 class="text-2xl font-bold">{{ $team->name }}</h2>
        <p class="text-base-content/70 mt-1">{{ __('Manage team members and their roles') }}</p>
    </div>

    <x-alert-success />
    <x-alert-error />

    <div class="grid gap-6 lg:grid-cols-3">
        @can('addMember', $team)
            <div class="lg:col-span-1">
                <div class="card bg-base-100 border border-base-300">
                    <div class="card-body">
                        <h3 class="card-title">{{ __('Add Member') }}</h3>
                        <form wire:submit="addMember" class="space-y-4">
                            <div class="form-control">
                                <label class="label pb-2">
                                    <span class="label-text">{{ __('Email Address') }}</span>
                                </label>
                                <input
                                    type="email"
                                    wire:model="email"
                                    required
                                    class="input input-bordered w-full rounded-lg @error('email') input-error @enderror"
                                    placeholder="{{ __('user@example.com') }}"
                                />
                                @error('email')
                                    <div class="label">
                                        <span class="label-text-alt text-error">{{ $message }}</span>
                                    </div>
                                @enderror
                            </div>

                            <div class="form-control">
                                <label class="label pb-2">
                                    <span class="label-text">{{ __('Role') }}</span>
                                </label>
                                <select wire:model="role" class="select select-bordered w-full rounded-lg">
                                    <option value="member">{{ __('Member') }}</option>
                                    <option value="admin">{{ __('Admin') }}</option>
                                </select>
                            </div>

                            <button type="submit" class="btn btn-primary w-full rounded-lg">
                                {{ __('Add Member') }}
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        @endcan

        <div class="{{ $this->authorize('addMember', $team) ? 'lg:col-span-2' : 'lg:col-span-3' }}">
            <div class="card bg-base-100 border border-base-300">
                <div class="card-body">
                    <h3 class="card-title">{{ __('Team Members') }}</h3>

                    @if ($members->count() === 0 && !$team->owner)
                        <div class="text-center py-8">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-12 w-12 mx-auto text-base-content/20" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z" />
                            </svg>
                            <p class="mt-4 text-base-content/70">{{ __('No members yet') }}</p>
                        </div>
                    @else
                        <div class="overflow-x-auto">
                            <table class="table">
                                <thead>
                                    <tr>
                                        <th>{{ __('Name') }}</th>
                                        <th>{{ __('Email') }}</th>
                                        <th>{{ __('Role') }}</th>
                                        <th></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr>
                                        <td>
                                            <div class="flex items-center gap-3">
                                                <div class="avatar avatar-placeholder">
                                                    <div class="bg-primary text-primary-content w-10 rounded-full">
                                                        <span class="text-xs">{{ $team->owner->initials() }}</span>
                                                    </div>
                                                </div>
                                                <div>
                                                    <div class="font-bold">{{ $team->owner->name }}</div>
                                                </div>
                                            </div>
                                        </td>
                                        <td>{{ $team->owner->email }}</td>
                                        <td>
                                            <div class="badge badge-primary">{{ __('Owner') }}</div>
                                        </td>
                                        <td></td>
                                    </tr>

                                    @foreach ($members as $member)
                                        <tr wire:key="member-{{ $member->id }}">
                                            <td>
                                                <div class="flex items-center gap-3">
                                                    <div class="avatar avatar-placeholder">
                                                        <div class="bg-neutral text-neutral-content w-10 rounded-full">
                                                            <span class="text-xs">{{ $member->initials() }}</span>
                                                        </div>
                                                    </div>
                                                    <div>
                                                        <div class="font-bold">{{ $member->name }}</div>
                                                    </div>
                                                </div>
                                            </td>
                                            <td>{{ $member->email }}</td>
                                            <td>
                                                @can('addMember', $team)
                                                    <select
                                                        wire:change="updateRole({{ $member->id }}, $event.target.value)"
                                                        class="select select-bordered select-sm rounded-lg"
                                                    >
                                                        <option value="member" @selected($member->pivot->role === 'member')>{{ __('Member') }}</option>
                                                        <option value="admin" @selected($member->pivot->role === 'admin')>{{ __('Admin') }}</option>
                                                    </select>
                                                @else
                                                    <div class="badge {{ $member->pivot->role === 'admin' ? 'badge-secondary' : 'badge-ghost' }}">
                                                        {{ ucfirst($member->pivot->role) }}
                                                    </div>
                                                @endcan
                                            </td>
                                            <td>
                                                @can('removeMember', $team)
                                                    <button
                                                        wire:click="removeMember({{ $member->id }})"
                                                        wire:confirm="{{ __('Are you sure you want to remove this member?') }}"
                                                        class="btn btn-ghost btn-sm text-error rounded-lg"
                                                    >
                                                        {{ __('Remove') }}
                                                    </button>
                                                @endcan
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
    </div>

    <div class="mt-6">
        <a href="{{ route('teams.index') }}" wire:navigate class="btn btn-ghost rounded-lg">
            {{ __('Back to Teams') }}
        </a>
    </div>
</div>
