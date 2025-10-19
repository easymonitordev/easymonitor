<?php

namespace App\Livewire\Monitors;

use App\Models\Monitor;
use App\Models\Team;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Livewire\Component;

class Index extends Component
{
    use AuthorizesRequests;

    public ?int $selectedTeamId = null;

    public string $filter = 'my'; // my, team

    /**
     * Mount the component and authorize access
     */
    public function mount(): void
    {
        $this->authorize('viewAny', Monitor::class);
    }

    /**
     * Delete the given monitor
     */
    public function delete(Monitor $monitor): void
    {
        $this->authorize('delete', $monitor);

        $monitor->delete();

        session()->flash('message', __('Monitor deleted successfully.'));
    }

    /**
     * Toggle the monitor active status
     */
    public function toggleActive(Monitor $monitor): void
    {
        $this->authorize('update', $monitor);

        $monitor->update(['is_active' => ! $monitor->is_active]);

        session()->flash('message', __('Monitor status updated.'));
    }

    /**
     * Render the component
     */
    public function render()
    {
        $teams = auth()->user()->ownedTeams
            ->merge(auth()->user()->teams);

        $monitors = collect();

        if ($this->filter === 'my') {
            // Show user's personal monitors (not assigned to a team)
            $monitors = auth()->user()->monitors()->whereNull('team_id')->latest()->get();
        } elseif ($this->filter === 'team' && $this->selectedTeamId) {
            $team = Team::find($this->selectedTeamId);
            if ($team && ($team->isOwner(auth()->user()) || $team->hasUser(auth()->user()))) {
                $monitors = $team->monitors()->latest()->get();
            }
        }

        return view('livewire.monitors.index', [
            'teams' => $teams,
            'monitors' => $monitors,
        ]);
    }
}
