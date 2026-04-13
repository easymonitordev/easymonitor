<?php

namespace App\Livewire\Monitors;

use App\Models\Monitor;
use App\Models\Project;
use App\Models\Team;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Livewire\Attributes\Url;
use Livewire\Component;

class Create extends Component
{
    use AuthorizesRequests;

    public ?int $teamId = null;

    #[Url]
    public ?int $projectId = null;

    public string $name = '';

    public string $url = '';

    public int $checkInterval = 60;

    public bool $isActive = true;

    public int $failureThreshold = 3;

    /**
     * Mount the component and authorize access
     */
    public function mount(): void
    {
        $this->authorize('create', Monitor::class);
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
            'projectId' => ['nullable', 'exists:projects,id'],
            'name' => ['required', 'string', 'max:255'],
            'url' => ['required', 'url', 'max:255'],
            'checkInterval' => ['required', 'integer', 'min:30', 'max:3600'],
            'isActive' => ['boolean'],
            'failureThreshold' => ['required', 'integer', 'min:1', 'max:10'],
        ];
    }

    /**
     * Save the new monitor
     */
    public function save(): void
    {
        $this->authorize('create', Monitor::class);

        $validated = $this->validate();

        // Verify user has access to selected team if provided
        if ($validated['teamId']) {
            $team = Team::findOrFail($validated['teamId']);
            if (! $team->isOwner(auth()->user()) && ! $team->hasUser(auth()->user())) {
                abort(403);
            }
        }

        // Verify user has access to selected project
        if ($validated['projectId']) {
            $project = Project::findOrFail($validated['projectId']);
            if (! auth()->user()->can('view', $project)) {
                abort(403);
            }
            // Project ownership governs — ignore team_id when inside a project
            $validated['teamId'] = null;
        }

        Monitor::create([
            'user_id' => auth()->id(),
            'team_id' => $validated['teamId'],
            'project_id' => $validated['projectId'],
            'name' => $validated['name'],
            'url' => $validated['url'],
            'check_interval' => $validated['checkInterval'],
            'is_active' => $validated['isActive'],
            'failure_threshold' => $validated['failureThreshold'],
        ]);

        session()->flash('message', __('Monitor created successfully.'));

        $this->redirect(route('monitors.index'), navigate: true);
    }

    /**
     * Render the component
     */
    public function render()
    {
        $user = auth()->user();
        $teams = $user->ownedTeams->merge($user->teams);

        // Projects the user can view
        $teamIds = $teams->pluck('id');
        $projects = Project::query()
            ->where(function ($query) use ($user, $teamIds) {
                $query->where('user_id', $user->id)
                    ->orWhereIn('team_id', $teamIds);
            })
            ->orderBy('name')
            ->get();

        return view('livewire.monitors.create', [
            'teams' => $teams,
            'projects' => $projects,
        ]);
    }
}
