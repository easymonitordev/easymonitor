<?php

declare(strict_types=1);

namespace App\Livewire\StatusPages;

use App\Models\StatusPage;
use App\Models\Team;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Livewire\Component;

class Index extends Component
{
    use AuthorizesRequests;

    public string $filter = 'my';

    public ?int $selectedTeamId = null;

    public function mount(): void
    {
        $this->authorize('viewAny', StatusPage::class);
    }

    public function delete(StatusPage $statusPage): void
    {
        $this->authorize('delete', $statusPage);

        $statusPage->delete();

        session()->flash('message', __('Status page deleted.'));
    }

    public function render()
    {
        $user = auth()->user();
        $teams = $user->ownedTeams->merge($user->teams);

        $statusPages = collect();

        if ($this->filter === 'my') {
            $statusPages = $user->statusPages()
                ->whereNull('team_id')
                ->latest()
                ->get();
        } elseif ($this->filter === 'team' && $this->selectedTeamId) {
            $team = Team::find($this->selectedTeamId);
            if ($team && ($team->isOwner($user) || $team->hasUser($user))) {
                $statusPages = $team->statusPages()->latest()->get();
            }
        }

        return view('livewire.status-pages.index', [
            'statusPages' => $statusPages,
            'teams' => $teams,
        ]);
    }
}
