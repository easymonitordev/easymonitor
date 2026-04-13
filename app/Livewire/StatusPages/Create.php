<?php

declare(strict_types=1);

namespace App\Livewire\StatusPages;

use App\Models\StatusPage;
use App\Models\Team;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Support\Str;
use Livewire\Component;

class Create extends Component
{
    use AuthorizesRequests;

    public ?int $teamId = null;

    public string $name = '';

    public string $slug = '';

    public string $description = '';

    public string $visibility = 'public';

    /**
     * Track the slug we last auto-generated so we can detect if the user
     * manually edited it. Once they do, we stop syncing from the name.
     */
    public string $lastAutoSlug = '';

    public function mount(): void
    {
        $this->authorize('create', StatusPage::class);
    }

    public function updatedName(string $value): void
    {
        // Only auto-generate while the slug is empty or still matches what we last set.
        if ($this->slug === '' || $this->slug === $this->lastAutoSlug) {
            $this->slug = Str::slug($value);
            $this->lastAutoSlug = $this->slug;
        }
    }

    public function rules(): array
    {
        return [
            'teamId' => ['nullable', 'exists:teams,id'],
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['required', 'string', 'max:100', 'regex:/^[a-z0-9-]+$/', 'unique:status_pages,slug'],
            'description' => ['nullable', 'string', 'max:1000'],
            'visibility' => ['required', 'in:public,unlisted,private'],
        ];
    }

    public function save(): void
    {
        $this->authorize('create', StatusPage::class);

        $validated = $this->validate();

        if ($validated['teamId']) {
            $team = Team::findOrFail($validated['teamId']);
            if (! $team->isOwner(auth()->user()) && $team->userRole(auth()->user()) !== 'admin') {
                abort(403);
            }
        }

        $statusPage = StatusPage::create([
            'user_id' => auth()->id(),
            'team_id' => $validated['teamId'],
            'name' => $validated['name'],
            'slug' => $validated['slug'],
            'description' => $validated['description'] ?: null,
            'visibility' => $validated['visibility'],
            'access_key' => $validated['visibility'] === 'unlisted' ? StatusPage::generateAccessKey() : null,
        ]);

        session()->flash('message', __('Status page created. Now add projects or monitors to show on it.'));

        $this->redirect(route('status-pages.manage', $statusPage), navigate: true);
    }

    public function render()
    {
        $user = auth()->user();
        $teams = $user->ownedTeams->merge(
            $user->teams->filter(fn ($t) => $t->userRole($user) === 'admin')
        );

        return view('livewire.status-pages.create', [
            'teams' => $teams,
        ]);
    }
}
