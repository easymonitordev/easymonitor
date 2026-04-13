<?php

declare(strict_types=1);

namespace App\Livewire\StatusPages\Incidents;

use App\Models\StatusPage;
use App\Models\StatusPageIncident;
use App\Models\StatusPageIncidentUpdate;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Livewire\Component;

class Edit extends Component
{
    use AuthorizesRequests;

    public StatusPage $statusPage;

    public StatusPageIncident $incident;

    public string $title = '';

    public string $body = '';

    public string $status = '';

    public string $severity = '';

    /** @var array<int> */
    public array $affectedMonitorIds = [];

    // New update form
    public string $newUpdateBody = '';

    public string $newUpdateStatus = '';

    public function mount(StatusPage $statusPage, StatusPageIncident $incident): void
    {
        $this->authorize('update', $statusPage);

        if ($incident->status_page_id !== $statusPage->id) {
            abort(404);
        }

        $this->statusPage = $statusPage;
        $this->incident = $incident;
        $this->title = $incident->title;
        $this->body = $incident->body ?? '';
        $this->status = $incident->status;
        $this->severity = $incident->severity ?? 'minor';
        $this->affectedMonitorIds = $incident->affected_monitor_ids ?? [];
        $this->newUpdateStatus = $incident->status;
    }

    public function save(): void
    {
        $this->authorize('update', $this->statusPage);

        $statusOptions = $this->incident->type === 'maintenance'
            ? 'in:scheduled,in_progress,completed'
            : 'in:investigating,identified,monitoring,resolved';

        $validated = $this->validate([
            'title' => ['required', 'string', 'max:255'],
            'body' => ['nullable', 'string', 'max:5000'],
            'status' => ['required', $statusOptions],
            'severity' => ['nullable', 'in:minor,major,critical'],
            'affectedMonitorIds' => ['array'],
        ]);

        $resolvedAt = $this->incident->resolved_at;
        if (in_array($validated['status'], ['resolved', 'completed'], true) && ! $resolvedAt) {
            $resolvedAt = now();
        } elseif (! in_array($validated['status'], ['resolved', 'completed'], true)) {
            $resolvedAt = null;
        }

        $this->incident->update([
            'title' => $validated['title'],
            'body' => $validated['body'] ?: null,
            'status' => $validated['status'],
            'severity' => $this->incident->type === 'incident' ? ($validated['severity'] ?? null) : null,
            'affected_monitor_ids' => $validated['affectedMonitorIds'] ?: null,
            'resolved_at' => $resolvedAt,
        ]);

        session()->flash('message', __('Incident updated.'));
    }

    public function postUpdate(): void
    {
        $this->authorize('update', $this->statusPage);

        $statusOptions = $this->incident->type === 'maintenance'
            ? 'in:scheduled,in_progress,completed'
            : 'in:investigating,identified,monitoring,resolved';

        $validated = $this->validate([
            'newUpdateBody' => ['required', 'string', 'max:2000'],
            'newUpdateStatus' => ['required', $statusOptions],
        ]);

        StatusPageIncidentUpdate::create([
            'incident_id' => $this->incident->id,
            'user_id' => auth()->id(),
            'status_at_update' => $validated['newUpdateStatus'],
            'body' => $validated['newUpdateBody'],
        ]);

        // Move incident status forward
        $resolvedAt = $this->incident->resolved_at;
        if (in_array($validated['newUpdateStatus'], ['resolved', 'completed'], true) && ! $resolvedAt) {
            $resolvedAt = now();
        }

        $this->incident->update([
            'status' => $validated['newUpdateStatus'],
            'resolved_at' => $resolvedAt,
        ]);

        $this->status = $validated['newUpdateStatus'];
        $this->newUpdateBody = '';

        session()->flash('message', __('Update posted.'));
    }

    public function delete(): void
    {
        $this->authorize('update', $this->statusPage);

        $this->incident->delete();

        session()->flash('message', __('Incident deleted.'));
        $this->redirect(route('status-pages.manage', ['statusPage' => $this->statusPage, 'tab' => 'incidents']), navigate: true);
    }

    public function render()
    {
        $sections = $this->statusPage->resolveSections();
        $availableMonitors = $sections->flatMap(fn ($s) => $s['monitors']);
        $updates = $this->incident->updates()->with('author')->get();

        return view('livewire.status-pages.incidents.edit', [
            'availableMonitors' => $availableMonitors,
            'updates' => $updates,
        ]);
    }
}
