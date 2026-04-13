<?php

declare(strict_types=1);

namespace App\Livewire\Projects;

use App\Models\Project;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Livewire\Component;

class Edit extends Component
{
    use AuthorizesRequests;

    public Project $project;

    public string $name = '';

    public string $description = '';

    public ?string $color = null;

    /**
     * Mount the component and authorize access
     */
    public function mount(Project $project): void
    {
        $this->authorize('update', $project);

        $this->project = $project;
        $this->name = $project->name;
        $this->description = $project->description ?? '';
        $this->color = $project->color;
    }

    /**
     * Get the validation rules
     *
     * @return array<string, array<int, string|int>>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:1000'],
            'color' => ['nullable', 'string', 'max:20'],
        ];
    }

    /**
     * Save the updated project
     */
    public function save(): void
    {
        $this->authorize('update', $this->project);

        $validated = $this->validate();

        $this->project->update([
            'name' => $validated['name'],
            'description' => $validated['description'] ?: null,
            'color' => $validated['color'] ?: null,
        ]);

        session()->flash('message', __('Project updated successfully.'));

        $this->redirect(route('projects.show', $this->project), navigate: true);
    }

    /**
     * Render the component
     */
    public function render()
    {
        return view('livewire.projects.edit');
    }
}
