<?php

namespace App\Livewire\Monitors;

use App\Models\Monitor;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Livewire\Component;

class Edit extends Component
{
    use AuthorizesRequests;

    public Monitor $monitor;

    public string $name = '';

    public string $url = '';

    public int $checkInterval = 60;

    public bool $isActive = true;

    /**
     * Mount the component and authorize access
     */
    public function mount(Monitor $monitor): void
    {
        $this->authorize('update', $monitor);

        $this->monitor = $monitor;
        $this->name = $monitor->name;
        $this->url = $monitor->url;
        $this->checkInterval = $monitor->check_interval;
        $this->isActive = $monitor->is_active;
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
            'url' => ['required', 'url', 'max:255'],
            'checkInterval' => ['required', 'integer', 'min:30', 'max:3600'],
            'isActive' => ['boolean'],
        ];
    }

    /**
     * Save the updated monitor
     */
    public function save(): void
    {
        $this->authorize('update', $this->monitor);

        $validated = $this->validate();

        $this->monitor->update([
            'name' => $validated['name'],
            'url' => $validated['url'],
            'check_interval' => $validated['checkInterval'],
            'is_active' => $validated['isActive'],
        ]);

        session()->flash('message', __('Monitor updated successfully.'));

        $this->redirect(route('monitors.index'), navigate: true);
    }

    /**
     * Render the component
     */
    public function render()
    {
        return view('livewire.monitors.edit');
    }
}
