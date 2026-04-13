<?php

declare(strict_types=1);

namespace App\Livewire\StatusPages;

use App\Models\Monitor;
use App\Models\Project;
use App\Models\StatusPage;
use App\Models\StatusPageItem;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Support\Facades\Storage;
use Livewire\Component;
use Livewire\WithFileUploads;

class Manage extends Component
{
    use AuthorizesRequests;
    use WithFileUploads;

    public StatusPage $statusPage;

    public string $tab = 'overview'; // overview | settings | items | incidents | branding

    // Settings form
    public string $name = '';

    public string $slug = '';

    public string $description = '';

    public string $footerText = '';

    public string $visibility = 'public';

    public string $customDomain = '';

    // Branding form
    public string $theme = 'business';

    public string $customCss = '';

    public $logo = null; // uploaded file

    // Add item form
    public string $addItemType = 'project'; // project | monitor

    public ?int $addProjectId = null;

    public ?int $addMonitorId = null;

    public function mount(StatusPage $statusPage): void
    {
        $this->authorize('update', $statusPage);

        $this->statusPage = $statusPage;
        $this->name = $statusPage->name;
        $this->slug = $statusPage->slug;
        $this->description = $statusPage->description ?? '';
        $this->footerText = $statusPage->footer_text ?? '';
        $this->visibility = $statusPage->visibility;
        $this->theme = $statusPage->theme ?: 'business';
        $this->customCss = $statusPage->custom_css ?? '';
        $this->customDomain = $statusPage->custom_domain ?? '';
    }

    /**
     * Save the custom domain (does not auto-verify; user must run verify separately)
     */
    public function saveCustomDomain(): void
    {
        $this->authorize('update', $this->statusPage);

        $validated = $this->validate([
            'customDomain' => [
                'nullable',
                'string',
                'max:255',
                'regex:/^([a-z0-9]([a-z0-9-]{0,61}[a-z0-9])?\.)+[a-z]{2,}$/i',
                'unique:status_pages,custom_domain,'.$this->statusPage->id,
            ],
        ], [
            'customDomain.regex' => __('Please enter a valid domain (e.g. status.example.com).'),
        ]);

        $newDomain = strtolower(trim($validated['customDomain'] ?? '')) ?: null;
        $changed = $newDomain !== $this->statusPage->custom_domain;

        $this->statusPage->update([
            'custom_domain' => $newDomain,
            // Reset verification when the domain changes
            'domain_verified_at' => $changed ? null : $this->statusPage->domain_verified_at,
        ]);

        session()->flash('message', $changed
            ? __('Domain saved. Add the TXT record below and click Verify.')
            : __('Domain unchanged.'));
    }

    /**
     * Verify the custom domain via DNS TXT lookup
     */
    public function verifyCustomDomain(): void
    {
        $this->authorize('update', $this->statusPage);

        if (! $this->statusPage->custom_domain) {
            session()->flash('error', __('Save a domain first.'));

            return;
        }

        $verified = $this->statusPage->verifyCustomDomain();

        if ($verified) {
            session()->flash('message', __('Domain verified! HTTPS will be provisioned automatically on first request.'));
        } else {
            session()->flash('error', __('TXT record not found. DNS may take a few minutes to propagate.'));
        }
    }

    /**
     * Remove the custom domain entirely
     */
    public function removeCustomDomain(): void
    {
        $this->authorize('update', $this->statusPage);

        $this->statusPage->update([
            'custom_domain' => null,
            'domain_verified_at' => null,
        ]);

        $this->customDomain = '';

        session()->flash('message', __('Custom domain removed.'));
    }

    /**
     * Save branding (theme, custom CSS, logo)
     */
    public function saveBranding(): void
    {
        $this->authorize('update', $this->statusPage);

        $themes = array_keys(StatusPage::AVAILABLE_THEMES);

        $validated = $this->validate([
            'theme' => ['required', 'in:'.implode(',', $themes)],
            'customCss' => ['nullable', 'string', 'max:50000'],
            'logo' => ['nullable', 'image', 'mimes:png,jpg,jpeg,svg,webp', 'max:2048'], // 2MB
        ]);

        $logoPath = $this->statusPage->logo_path;

        if ($this->logo) {
            // Delete old logo if any
            if ($logoPath && Storage::disk('public')->exists($logoPath)) {
                Storage::disk('public')->delete($logoPath);
            }
            $logoPath = $this->logo->store('status-page-logos', 'public');
        }

        $this->statusPage->update([
            'theme' => $validated['theme'],
            'custom_css' => $validated['customCss'] ?: null,
            'logo_path' => $logoPath,
        ]);

        $this->logo = null;

        session()->flash('message', __('Branding saved.'));
    }

    /**
     * Remove the uploaded logo
     */
    public function removeLogo(): void
    {
        $this->authorize('update', $this->statusPage);

        if ($this->statusPage->logo_path && Storage::disk('public')->exists($this->statusPage->logo_path)) {
            Storage::disk('public')->delete($this->statusPage->logo_path);
        }

        $this->statusPage->update(['logo_path' => null]);

        session()->flash('message', __('Logo removed.'));
    }

    /**
     * Save settings tab
     */
    public function saveSettings(): void
    {
        $this->authorize('update', $this->statusPage);

        $validated = $this->validate([
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['required', 'string', 'max:100', 'regex:/^[a-z0-9-]+$/', 'unique:status_pages,slug,'.$this->statusPage->id],
            'description' => ['nullable', 'string', 'max:1000'],
            'footerText' => ['nullable', 'string', 'max:500'],
            'visibility' => ['required', 'in:public,unlisted,private'],
        ]);

        // Generate access key if switching to unlisted and none set
        if ($validated['visibility'] === 'unlisted' && ! $this->statusPage->access_key) {
            $this->statusPage->access_key = StatusPage::generateAccessKey();
        }

        $this->statusPage->update([
            'name' => $validated['name'],
            'slug' => $validated['slug'],
            'description' => $validated['description'] ?: null,
            'footer_text' => $validated['footerText'] ?: null,
            'visibility' => $validated['visibility'],
            'access_key' => $this->statusPage->access_key,
        ]);

        session()->flash('message', __('Settings saved.'));
    }

    /**
     * Regenerate the access key (invalidates existing secret links)
     */
    public function regenerateAccessKey(): void
    {
        $this->authorize('update', $this->statusPage);

        $this->statusPage->update([
            'access_key' => StatusPage::generateAccessKey(),
        ]);

        session()->flash('message', __('Access key regenerated. Old links no longer work.'));
    }

    /**
     * Add a project or monitor as an item on this page
     */
    public function addItem(): void
    {
        $this->authorize('update', $this->statusPage);

        if ($this->addItemType === 'project' && $this->addProjectId) {
            $project = Project::findOrFail($this->addProjectId);
            if (! auth()->user()->can('view', $project)) {
                abort(403);
            }

            StatusPageItem::create([
                'status_page_id' => $this->statusPage->id,
                'type' => 'project',
                'project_id' => $project->id,
                'sort_order' => $this->statusPage->items()->max('sort_order') + 1,
            ]);

            $this->addProjectId = null;
            session()->flash('message', __('Project added to status page.'));
        } elseif ($this->addItemType === 'monitor' && $this->addMonitorId) {
            $monitor = Monitor::findOrFail($this->addMonitorId);
            if (! auth()->user()->can('view', $monitor)) {
                abort(403);
            }

            StatusPageItem::create([
                'status_page_id' => $this->statusPage->id,
                'type' => 'monitor',
                'monitor_id' => $monitor->id,
                'sort_order' => $this->statusPage->items()->max('sort_order') + 1,
            ]);

            $this->addMonitorId = null;
            session()->flash('message', __('Monitor added to status page.'));
        }
    }

    /**
     * Remove an item from the page
     */
    public function removeItem(StatusPageItem $item): void
    {
        $this->authorize('update', $this->statusPage);

        if ($item->status_page_id !== $this->statusPage->id) {
            abort(403);
        }

        $item->delete();
        session()->flash('message', __('Item removed.'));
    }

    /**
     * Toggle whether a specific monitor (inside a project) is visible on this page
     */
    public function toggleMonitorVisibility(int $monitorId): void
    {
        $this->authorize('update', $this->statusPage);

        $monitor = Monitor::findOrFail($monitorId);
        if (! auth()->user()->can('view', $monitor)) {
            abort(403);
        }

        if ($this->statusPage->excludedMonitors()->where('monitor_id', $monitorId)->exists()) {
            $this->statusPage->excludedMonitors()->detach($monitorId);
        } else {
            $this->statusPage->excludedMonitors()->attach($monitorId);
        }
    }

    public function deleteStatusPage(): void
    {
        $this->authorize('delete', $this->statusPage);

        $this->statusPage->delete();

        session()->flash('message', __('Status page deleted.'));
        $this->redirect(route('status-pages.index'), navigate: true);
    }

    public function render()
    {
        $user = auth()->user();
        $teamIds = $user->ownedTeams->pluck('id')->merge($user->teams->pluck('id'));

        // Available projects for adding
        $availableProjects = Project::query()
            ->where(function ($query) use ($user, $teamIds) {
                $query->where('user_id', $user->id)
                    ->orWhereIn('team_id', $teamIds);
            })
            ->orderBy('name')
            ->get();

        // Available monitors for adding
        $availableMonitors = Monitor::query()
            ->where(function ($query) use ($user, $teamIds) {
                $query->where('user_id', $user->id)
                    ->orWhereIn('team_id', $teamIds)
                    ->orWhereHas('project', fn ($q) => $q->whereIn('team_id', $teamIds)->orWhere('user_id', $user->id));
            })
            ->orderBy('name')
            ->get();

        $items = $this->statusPage->items()->with(['project', 'monitor'])->get();
        $excludedMonitorIds = $this->statusPage->excludedMonitors()->pluck('monitors.id')->all();

        $incidents = $this->statusPage->incidents()->with('author')->limit(20)->get();

        return view('livewire.status-pages.manage', [
            'availableProjects' => $availableProjects,
            'availableMonitors' => $availableMonitors,
            'items' => $items,
            'excludedMonitorIds' => $excludedMonitorIds,
            'incidents' => $incidents,
            'availableThemes' => StatusPage::AVAILABLE_THEMES,
        ]);
    }
}
