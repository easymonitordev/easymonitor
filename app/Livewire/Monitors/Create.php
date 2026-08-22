<?php

namespace App\Livewire\Monitors;

use App\Enums\AssertionType;
use App\Enums\CheckType;
use App\Models\Monitor;
use App\Models\Project;
use App\Models\Team;
use App\Services\MonitorTargetRules;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Url;
use Livewire\Component;

class Create extends Component
{
    use AuthorizesRequests;

    public ?int $teamId = null;

    #[Url]
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
    public function mount(): void
    {
        $this->authorize('create', Monitor::class);

        $default = auth()->user()->defaultNotificationChannel();

        if ($default) {
            $this->notificationChannelIds = [$default->id];
        }
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
     * Save the new monitor
     */
    public function save(): void
    {
        $this->authorize('create', Monitor::class);

        $validated = $this->validate();

        $validated['url'] = MonitorTargetRules::urlFromInput(
            $this->checkType, $this->url, $this->tcpHost, $this->tcpPort
        );

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

        $monitor = Monitor::create([
            'user_id' => auth()->id(),
            'team_id' => $validated['teamId'],
            'project_id' => $validated['projectId'],
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

        $monitor->notificationChannels()->sync($ownedChannelIds);

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

        $channels = $user->notificationChannels()
            ->where('is_active', true)
            ->get()
            ->sortBy(fn (\App\Models\NotificationChannel $channel) => $channel->type->sortOrder())
            ->values();

        return view('livewire.monitors.create', [
            'teams' => $teams,
            'projects' => $projects,
            'channels' => $channels,
        ]);
    }
}
