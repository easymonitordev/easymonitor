<?php

namespace App\Livewire\Teams;

use App\Models\Team;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Livewire\Component;

class Create extends Component
{
    use AuthorizesRequests;

    public string $name = '';

    public string $description = '';

    /**
     * Mount the component and authorize access
     */
    public function mount(): void
    {
        $this->authorize('create', Team::class);
    }

    /**
     * Get the validation rules
     *
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:1000'],
        ];
    }

    /**
     * Save the new team
     */
    public function save(): void
    {
        $this->authorize('create', Team::class);

        $validated = $this->validate();

        $team = auth()->user()->ownedTeams()->create([
            'name' => $validated['name'],
            'description' => $validated['description'],
        ]);

        session()->flash('message', __('Team created successfully.'));

        $this->redirect(route('teams.index'), navigate: true);
    }

    /**
     * Render the component
     */
    public function render()
    {
        return view('livewire.teams.create');
    }
}
