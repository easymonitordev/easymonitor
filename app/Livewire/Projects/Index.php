<?php

declare(strict_types=1);

namespace App\Livewire\Projects;

use App\Models\Project;
use App\Models\Team;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Livewire\Component;

class Index extends Component
{
    use AuthorizesRequests;

    public string $filter = 'my'; // my, team

    public ?int $selectedTeamId = null;

    /**
     * Mount the component and authorize access
     */
    public function mount(): void
    {
        $this->authorize('viewAny', Project::class);
    }

    /**
     * Delete the given project
     */
    public function delete(Project $project): void
    {
        $this->authorize('delete', $project);

        $project->delete();

        session()->flash('message', __('Project deleted successfully.'));
    }

    /**
     * Render the component
     */
    public function render()
    {
        $user = auth()->user();
        $teams = $user->ownedTeams->merge($user->teams);

        $projects = collect();

        if ($this->filter === 'my') {
            $projects = $user->projects()
                ->whereNull('team_id')
                ->withCount('monitors')
                ->latest()
                ->get();
        } elseif ($this->filter === 'team' && $this->selectedTeamId) {
            $team = Team::find($this->selectedTeamId);
            if ($team && ($team->isOwner($user) || $team->hasUser($user))) {
                $projects = $team->projects()
                    ->withCount('monitors')
                    ->latest()
                    ->get();
            }
        }

        return view('livewire.projects.index', [
            'projects' => $projects,
            'teams' => $teams,
        ]);
    }
}
