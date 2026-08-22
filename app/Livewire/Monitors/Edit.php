<?php

namespace App\Livewire\Monitors;

use App\Enums\AssertionType;
use App\Enums\CheckType;
use App\Models\Monitor;
use App\Models\Project;
use App\Services\MonitorTargetRules;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Validation\Rule;
use Livewire\Component;

class Edit extends Component
{
    use AuthorizesRequests;

    public Monitor $monitor;

    public ?int $projectId = null;

    public string $name = '';

    public string $checkType = 'http';

    public string $url = '';

    public string $tcpHost = '';

    public ?int $tcpPort = null;

    public string $assertionType = 'none';

    public string $assertionValue = '';

    public int $checkInterval = 60;

    public bool $isActive = true;

    public int $failureThreshold = 1;

    /**
     * @var array<int, int>
     */
    public array $notificationChannelIds = [];

    /**
     * Mount the component and authorize access
     */
    public function mount(Monitor $monitor): void
    {
        $this->authorize('update', $monitor);

        $this->monitor = $monitor;
        $this->projectId = $monitor->project_id;
        $this->name = $monitor->name;
        $this->checkType = $monitor->check_type->value;
        $this->url = $monitor->url;

        if ($monitor->check_type === CheckType::Tcp) {
            ['host' => $this->tcpHost, 'port' => $this->tcpPort] = MonitorTargetRules::splitHostPort($monitor->url);
        }

        $this->assertionType = $monitor->assertion_type?->value ?? AssertionType::None->value;
        $this->assertionValue = $monitor->assertion_value ?? '';

        $this->checkInterval = $monitor->check_interval;
        $this->isActive = $monitor->is_active;
        $this->failureThreshold = $monitor->failure_threshold ?? 1;
        $this->notificationChannelIds = $monitor->notificationChannels()
            ->pluck('notification_channels.id')
            ->all();
    }

    /**
     * Get the validation rules
     *
     * @return array<string, array<int, string|int>>
     */
    public function rules(): array
    {
        return [
            'projectId' => ['nullable', 'exists:projects,id'],
            'name' => ['required', 'string', 'max:255'],
            'checkType' => ['required', Rule::in(CheckType::values())],
            ...MonitorTargetRules::for($this->checkType),
            'assertionType' => ['required', Rule::in(AssertionType::values())],
            'assertionValue' => $this->requiresAssertionValue()
                ? ['required', 'string', 'max:255']
                : ['nullable', 'string', 'max:255'],
            'checkInterval' => ['required', 'integer', 'min:30', 'max:3600'],
            'isActive' => ['boolean'],
            'failureThreshold' => ['required', 'integer', 'min:1', 'max:10'],
            'notificationChannelIds' => ['array'],
            'notificationChannelIds.*' => ['integer'],
        ];
    }

    /**
     * Custom validation messages
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            ...MonitorTargetRules::messages(),
            'assertionValue.required' => __('Enter the keyword to look for in the response body.'),
        ];
    }

    /**
     * Whether the current form state needs a non-empty assertion keyword
     */
    private function requiresAssertionValue(): bool
    {
        return $this->checkType === CheckType::Http->value
            && $this->assertionType !== AssertionType::None->value;
    }

    /**
     * Save the updated monitor
     */
    public function save(): void
    {
        $this->authorize('update', $this->monitor);

        $validated = $this->validate();

        $validated['url'] = MonitorTargetRules::urlFromInput(
            $this->checkType, $this->url, $this->tcpHost, $this->tcpPort
        );

        // If assigning to a project, verify access and clear team_id
        if ($validated['projectId']) {
            $project = Project::findOrFail($validated['projectId']);
            if (! auth()->user()->can('view', $project)) {
                abort(403);
            }
        }

        $this->monitor->update([
            'project_id' => $validated['projectId'],
            'team_id' => $validated['projectId'] ? null : $this->monitor->team_id,
            'name' => $validated['name'],
            'check_type' => $validated['checkType'],
            'url' => $validated['url'],
            'assertion_type' => $this->requiresAssertionValue() ? $validated['assertionType'] : AssertionType::None->value,
            'assertion_value' => $this->requiresAssertionValue() ? $validated['assertionValue'] : null,
            'check_interval' => $validated['checkInterval'],
            'is_active' => $validated['isActive'],
            'failure_threshold' => $validated['failureThreshold'],
        ]);

        $ownedChannelIds = auth()->user()
            ->notificationChannels()
            ->whereIn('id', $validated['notificationChannelIds'] ?? [])
            ->pluck('id');

        $this->monitor->notificationChannels()->sync($ownedChannelIds);

        session()->flash('message', __('Monitor updated successfully.'));

        $this->redirect(route('monitors.show', $this->monitor), navigate: true);
    }

    /**
     * Render the component
     */
    public function render()
    {
        $user = auth()->user();
        $teams = $user->ownedTeams->merge($user->teams);
        $teamIds = $teams->pluck('id');

        $projects = Project::query()
            ->where(function ($query) use ($user, $teamIds) {
                $query->where('user_id', $user->id)
                    ->orWhereIn('team_id', $teamIds);
            })
            ->orderBy('name')
            ->get();

        $channels = $user->notificationChannels()
            ->where('is_active', true)
            ->get()
            ->sortBy(fn (\App\Models\NotificationChannel $channel) => $channel->type->sortOrder())
            ->values();

        return view('livewire.monitors.edit', [
            'projects' => $projects,
            'channels' => $channels,
        ]);
    }
}
