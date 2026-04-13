<?php

declare(strict_types=1);

namespace App\Livewire\StatusPages\Incidents;

use App\Models\StatusPage;
use App\Models\StatusPageIncident;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Livewire\Component;

class Create extends Component
{
    use AuthorizesRequests;

    public StatusPage $statusPage;

    public string $type = 'incident'; // incident | maintenance

    public string $title = '';

    public string $body = '';

    public string $status = 'investigating';

    public string $severity = 'minor';

    /** @var array<int> */
    public array $affectedMonitorIds = [];

    public ?string $scheduledFor = null;

    public ?string $scheduledUntil = null;

    public function mount(StatusPage $statusPage): void
    {
        $this->authorize('update', $statusPage);
        $this->statusPage = $statusPage;
    }

    public function updatedType(string $value): void
    {
        $this->status = $value === 'maintenance' ? 'scheduled' : 'investigating';
    }

    public function rules(): array
    {
        $statusOptions = $this->type === 'maintenance'
            ? 'in:scheduled,in_progress,completed'
            : 'in:investigating,identified,monitoring,resolved';

        return [
            'type' => ['required', 'in:incident,maintenance'],
            'title' => ['required', 'string', 'max:255'],
            'body' => ['nullable', 'string', 'max:5000'],
            'status' => ['required', $statusOptions],
            'severity' => ['nullable', 'in:minor,major,critical'],
            'affectedMonitorIds' => ['array'],
            'affectedMonitorIds.*' => ['integer', 'exists:monitors,id'],
            'scheduledFor' => ['nullable', 'date'],
            'scheduledUntil' => ['nullable', 'date', 'after_or_equal:scheduledFor'],
        ];
    }

    public function save(): void
    {
        $this->authorize('update', $this->statusPage);

        $validated = $this->validate();

        $resolvedAt = null;
        if ($validated['type'] === 'incident' && $validated['status'] === 'resolved') {
            $resolvedAt = now();
        }
        if ($validated['type'] === 'maintenance' && $validated['status'] === 'completed') {
            $resolvedAt = now();
        }

        StatusPageIncident::create([
            'status_page_id' => $this->statusPage->id,
            'user_id' => auth()->id(),
            'title' => $validated['title'],
            'body' => $validated['body'] ?: null,
            'type' => $validated['type'],
            'status' => $validated['status'],
            'severity' => $validated['type'] === 'incident' ? ($validated['severity'] ?? null) : null,
            'affected_monitor_ids' => $validated['affectedMonitorIds'] ?: null,
            'scheduled_for' => $validated['scheduledFor'] ?: null,
            'scheduled_until' => $validated['scheduledUntil'] ?: null,
            'started_at' => in_array($validated['status'], ['investigating', 'identified', 'monitoring', 'in_progress'], true) ? now() : null,
            'resolved_at' => $resolvedAt,
        ]);

        session()->flash('message', __('Incident created.'));
        $this->redirect(route('status-pages.manage', ['statusPage' => $this->statusPage, 'tab' => 'incidents']), navigate: true);
    }

    public function render()
    {
        $sections = $this->statusPage->resolveSections();
        $availableMonitors = $sections->flatMap(fn ($s) => $s['monitors']);

        return view('livewire.status-pages.incidents.create', [
            'availableMonitors' => $availableMonitors,
        ]);
    }
}
