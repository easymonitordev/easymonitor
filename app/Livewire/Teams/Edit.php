<?php

namespace App\Livewire\Teams;

use App\Models\Team;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Livewire\Component;

class Edit extends Component
{
    use AuthorizesRequests;

    public Team $team;

    public string $name = '';

    public string $description = '';

    /**
     * Mount the component and authorize access
     */
    public function mount(Team $team): void
    {
        $this->authorize('update', $team);

        $this->team = $team;
        $this->name = $team->name;
        $this->description = $team->description ?? '';
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
     * Save the updated team
     */
    public function save(): void
    {
        $this->authorize('update', $this->team);

        $validated = $this->validate();

        $this->team->update([
            'name' => $validated['name'],
            'description' => $validated['description'],
        ]);

        session()->flash('message', __('Team updated successfully.'));

        $this->redirect(route('teams.index'), navigate: true);
    }

    /**
     * Render the component
     */
    public function render()
    {
        return view('livewire.teams.edit');
    }
}
