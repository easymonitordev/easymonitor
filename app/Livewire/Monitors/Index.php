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
        $user = auth()->user();
        $teams = $user->ownedTeams->merge($user->teams);

        $monitors = collect();

        if ($this->filter === 'my') {
            // Personal monitors: user's own, no team_id, and either no project or a personal project
            $monitors = $user->monitors()
                ->with(['latestCheckResult', 'project'])
                ->whereNull('team_id')
                ->where(function ($query) {
                    $query->whereNull('project_id')
                        ->orWhereHas('project', fn ($q) => $q->whereNull('team_id'));
                })
                ->latest()
                ->get();
        } elseif ($this->filter === 'team' && $this->selectedTeamId) {
            $team = Team::find($this->selectedTeamId);
            if ($team && ($team->isOwner($user) || $team->hasUser($user))) {
                // Team monitors: either directly assigned team_id, or inside a project owned by this team
                $monitors = Monitor::query()
                    ->with(['latestCheckResult', 'project'])
                    ->where(function ($query) use ($team) {
                        $query->where('team_id', $team->id)
                            ->orWhereHas('project', fn ($q) => $q->where('team_id', $team->id));
                    })
                    ->latest()
                    ->get();
            }
        }

        return view('livewire.monitors.index', [
            'teams' => $teams,
            'monitors' => $monitors,
        ]);
    }
}
