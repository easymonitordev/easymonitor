<?php

namespace App\Livewire\Teams;

use App\Models\Team;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Livewire\Component;

class Index extends Component
{
    use AuthorizesRequests;

    /**
     * Mount the component and authorize access
     */
    public function mount(): void
    {
        $this->authorize('viewAny', Team::class);
    }

    /**
     * Delete the given team
     */
    public function delete(Team $team): void
    {
        $this->authorize('delete', $team);

        $team->delete();

        session()->flash('message', __('Team deleted successfully.'));
    }

    /**
     * Render the component
     */
    public function render()
    {
        $ownedTeams = auth()->user()->ownedTeams()->with('users')->get();
        $memberTeams = auth()->user()->teams()->with(['owner', 'users'])->get();

        return view('livewire.teams.index', [
            'ownedTeams' => $ownedTeams,
            'memberTeams' => $memberTeams,
        ]);
    }
}
