<?php

declare(strict_types=1);

namespace App\Livewire\Projects;

use App\Models\Project;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Livewire\Component;

class Show extends Component
{
    use AuthorizesRequests;

    public Project $project;

    /**
     * Mount the component and authorize access
     */
    public function mount(Project $project): void
    {
        $this->authorize('view', $project);
        $this->project = $project;
    }

    /**
     * Render the component
     */
    public function render()
    {
        $monitors = $this->project->monitors()
            ->with('latestCheckResult')
            ->latest()
            ->get();

        $total = $monitors->count();
        $up = $monitors->where('status', 'up')->count();
        $down = $monitors->where('status', 'down')->count();
        $pending = $monitors->where('status', 'pending')->count();
        $status = $this->project->aggregateStatus();

        return view('livewire.projects.show', [
            'monitors' => $monitors,
            'total' => $total,
            'up' => $up,
            'down' => $down,
            'pending' => $pending,
            'status' => $status,
        ]);
    }
}
