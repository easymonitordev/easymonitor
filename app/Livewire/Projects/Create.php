<?php

declare(strict_types=1);

namespace App\Livewire\Projects;

use App\Models\Project;
use App\Models\Team;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Livewire\Component;

class Create extends Component
{
    use AuthorizesRequests;

    public ?int $teamId = null;

    public string $name = '';

    public string $description = '';

    public ?string $color = null;

    /**
     * Mount the component and authorize access
     */
    public function mount(): void
    {
        $this->authorize('create', Project::class);
    }

    /**
     * Get the validation rules
     *
     * @return array<string, array<int, string|int>>
     */
    public function rules(): array
    {
        return [
            'teamId' => ['nullable', 'exists:teams,id'],
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:1000'],
            'color' => ['nullable', 'string', 'max:20'],
        ];
    }

    /**
     * Save the new project
     */
    public function save(): void
    {
        $this->authorize('create', Project::class);

        $validated = $this->validate();

        if ($validated['teamId']) {
            $team = Team::findOrFail($validated['teamId']);
            if (! $team->isOwner(auth()->user()) && $team->userRole(auth()->user()) !== 'admin') {
                abort(403);
            }
        }

        Project::create([
            'user_id' => auth()->id(),
            'team_id' => $validated['teamId'],
            'name' => $validated['name'],
            'description' => $validated['description'] ?: null,
            'color' => $validated['color'] ?: null,
        ]);

        session()->flash('message', __('Project created successfully.'));

        $this->redirect(route('projects.index'), navigate: true);
    }

    /**
     * Render the component
     */
    public function render()
    {
        $user = auth()->user();

        // User can create team projects only for teams they own or admin
        $teams = $user->ownedTeams->merge(
            $user->teams->filter(fn ($t) => $t->userRole($user) === 'admin')
        );

        return view('livewire.projects.create', [
            'teams' => $teams,
        ]);
    }
}
