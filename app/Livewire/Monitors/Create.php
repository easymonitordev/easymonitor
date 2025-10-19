<?php

namespace App\Livewire\Monitors;

use App\Models\Monitor;
use App\Models\Team;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Livewire\Component;

class Create extends Component
{
    use AuthorizesRequests;

    public ?int $teamId = null;

    public string $name = '';

    public string $url = '';

    public int $checkInterval = 60;

    public bool $isActive = true;

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
            'name' => ['required', 'string', 'max:255'],
            'url' => ['required', 'url', 'max:255'],
            'checkInterval' => ['required', 'integer', 'min:30', 'max:3600'],
            'isActive' => ['boolean'],
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

        Monitor::create([
            'user_id' => auth()->id(),
            'team_id' => $validated['teamId'],
            'name' => $validated['name'],
            'url' => $validated['url'],
            'check_interval' => $validated['checkInterval'],
            'is_active' => $validated['isActive'],
        ]);

        session()->flash('message', __('Monitor created successfully.'));

        $this->redirect(route('monitors.index'), navigate: true);
    }

    /**
     * Render the component
     */
    public function render()
    {
        $teams = auth()->user()->ownedTeams
            ->merge(auth()->user()->teams);

        return view('livewire.monitors.create', [
            'teams' => $teams,
        ]);
    }
}
