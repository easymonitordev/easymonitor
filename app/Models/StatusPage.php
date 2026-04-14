<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

/**
 * A public (or unlisted, or private) status page.
 *
 * Composed of "items" which can be either a Project (live link — all its
 * monitors appear) or a single Monitor. Individual monitors inside a project
 * can be hidden per status page via excludedMonitors.
 *
 * @property int $id
 * @property int $user_id
 * @property int|null $team_id
 * @property string $name
 * @property string $slug
 * @property string $visibility public | unlisted | private
 * @property string|null $access_key
 * @property string|null $description
 * @property string|null $footer_text
 * @property string|null $logo_path
 * @property string $theme
 * @property string|null $custom_css
 * @property string|null $custom_domain
 * @property \Illuminate\Support\Carbon|null $domain_verified_at
 */
class StatusPage extends Model
{
    /** @use HasFactory<\Database\Factories\StatusPageFactory> */
    use HasFactory;

    /**
     * Themes available for status pages. Each entry is [theme_id => display_name].
     * These must also be enabled in resources/css/app.css @plugin block.
     *
     * @var array<string, string>
     */
    public const AVAILABLE_THEMES = [
        'business' => 'Business (Dark)',
        'corporate' => 'Corporate (Light)',
        'light' => 'Light',
        'dark' => 'Dark',
        'cupcake' => 'Cupcake',
        'emerald' => 'Emerald',
        'dracula' => 'Dracula',
        'night' => 'Night',
        'winter' => 'Winter',
        'nord' => 'Nord',
        'sunset' => 'Sunset',
    ];

    protected $fillable = [
        'user_id',
        'team_id',
        'name',
        'slug',
        'description',
        'footer_text',
        'visibility',
        'access_key',
        'logo_path',
        'theme',
        'custom_css',
        'custom_domain',
        'domain_verified_at',
    ];

    protected function casts(): array
    {
        return [
            'domain_verified_at' => 'datetime',
        ];
    }

    /**
     * Generate a random access key for unlisted pages
     */
    public static function generateAccessKey(): string
    {
        return Str::random(48);
    }

    /**
     * Generate a deterministic verification token for the current domain.
     * The user must add this as a TXT record at _easymonitor-verify.{domain}.
     */
    public function domainVerificationToken(): string
    {
        if (! $this->custom_domain) {
            return '';
        }

        return 'easymonitor-verify-'.substr(
            hash_hmac('sha256', $this->custom_domain.'|'.$this->id, config('app.key')),
            0,
            32
        );
    }

    /**
     * The hostname where the verification TXT record should be set.
     */
    public function domainVerificationHost(): string
    {
        return '_easymonitor-verify.'.$this->custom_domain;
    }

    /**
     * Verify the custom domain by looking up the TXT record.
     * Marks domain_verified_at on success.
     */
    public function verifyCustomDomain(): bool
    {
        if (! $this->custom_domain) {
            return false;
        }

        $expected = $this->domainVerificationToken();
        $records = @dns_get_record($this->domainVerificationHost(), DNS_TXT);

        if (! is_array($records)) {
            return false;
        }

        foreach ($records as $record) {
            $value = $record['txt'] ?? ($record['entries'][0] ?? '');
            if (is_string($value) && trim($value) === $expected) {
                $this->update(['domain_verified_at' => now()]);

                return true;
            }
        }

        return false;
    }

    public function isDomainVerified(): bool
    {
        return $this->custom_domain && $this->domain_verified_at !== null;
    }

    /**
     * Resolve which theme name to apply on `data-theme`.
     *
     * If custom_css contains a DaisyUI `@plugin "daisyui/theme" { name: "X"; ... }`
     * block, "X" is used. Otherwise the configured theme is used.
     */
    public function effectiveTheme(): string
    {
        if ($this->custom_css && preg_match('/@plugin\s+["\']daisyui\/theme["\']\s*\{[^}]*?name:\s*["\']([^"\']+)["\']\s*;/s', $this->custom_css, $m)) {
            return $m[1];
        }

        return $this->theme ?: 'business';
    }

    /**
     * Return CSS that's safe to inject inside a <style> tag at runtime.
     *
     * If the user pasted a DaisyUI `@plugin "daisyui/theme" { ... }` block
     * (a build-time directive that browsers can't execute), strip the wrapper
     * and metadata, then re-emit the variable definitions under the
     * matching [data-theme="..."] selector.
     */
    public function renderableCustomCss(): string
    {
        if (! $this->custom_css) {
            return '';
        }

        $css = $this->custom_css;

        // Find DaisyUI theme block(s) and convert each to a data-theme selector.
        $css = preg_replace_callback(
            '/@plugin\s+["\']daisyui\/theme["\']\s*\{(.+?)\n\}/s',
            function ($matches) {
                $body = $matches[1];

                $themeName = 'business';
                if (preg_match('/name:\s*["\']([^"\']+)["\']\s*;/', $body, $nameMatch)) {
                    $themeName = $nameMatch[1];
                }

                // Strip DaisyUI plugin metadata lines.
                $body = preg_replace('/^\s*(name|default|prefersdark|color-scheme):[^;]*;\s*$/m', '', $body);
                $body = trim($body);

                return '[data-theme="'.$themeName.'"] { '.$body.' }';
            },
            $css
        );

        return $css;
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    /**
     * Items attached to this page (projects or individual monitors)
     */
    public function items(): HasMany
    {
        return $this->hasMany(StatusPageItem::class)->orderBy('sort_order');
    }

    /**
     * Monitors hidden on this page (even if inside a linked project)
     */
    public function excludedMonitors(): BelongsToMany
    {
        return $this->belongsToMany(Monitor::class, 'status_page_excluded_monitors')
            ->withTimestamps();
    }

    /**
     * Incidents published on this page
     */
    public function incidents(): HasMany
    {
        return $this->hasMany(StatusPageIncident::class)->latest();
    }

    /**
     * Resolve all monitors visible on this page, grouped by project item.
     *
     * Returns a collection of sections: each section has an optional project
     * (null for standalone-monitor items) and a collection of monitors.
     *
     * @return \Illuminate\Support\Collection<int, array{label: string, project: ?Project, monitors: \Illuminate\Support\Collection}>
     */
    public function resolveSections(): \Illuminate\Support\Collection
    {
        $excludedIds = $this->excludedMonitors()->pluck('monitors.id')->all();

        return $this->items->map(function (StatusPageItem $item) use ($excludedIds) {
            if ($item->type === 'project' && $item->project) {
                $monitors = $item->project->monitors()
                    ->with('latestCheckResult')
                    ->whereNotIn('id', $excludedIds)
                    ->where('is_active', true)
                    ->orderBy('id')
                    ->get();

                return [
                    'label' => $item->label ?: $item->project->name,
                    'project' => $item->project,
                    'monitors' => $monitors,
                ];
            }

            if ($item->type === 'monitor' && $item->monitor) {
                return [
                    'label' => $item->label ?: $item->monitor->name,
                    'project' => null,
                    'monitors' => collect([$item->monitor->loadMissing('latestCheckResult')]),
                ];
            }

            return null;
        })->filter()->values();
    }

    /**
     * Aggregate status across all visible monitors.
     *
     * Returns operational | degraded | outage | unknown.
     */
    public function aggregateStatus(): string
    {
        $monitors = $this->resolveSections()
            ->flatMap(fn ($section) => $section['monitors']);

        if ($monitors->isEmpty()) {
            return 'unknown';
        }

        $down = $monitors->where('status', 'down')->count();
        $up = $monitors->where('status', 'up')->count();
        $total = $monitors->count();

        if ($down === 0 && $up > 0) {
            return 'operational';
        }

        if ($down === $total) {
            return 'outage';
        }

        if ($down > 0) {
            return 'degraded';
        }

        return 'unknown';
    }

    /**
     * Get currently active incidents (unresolved)
     *
     * @return \Illuminate\Database\Eloquent\Collection<int, StatusPageIncident>
     */
    public function activeIncidents(): \Illuminate\Database\Eloquent\Collection
    {
        return $this->incidents()
            ->whereNull('resolved_at')
            ->where('type', 'incident')
            ->get();
    }

    /**
     * Get upcoming maintenance windows
     *
     * @return \Illuminate\Database\Eloquent\Collection<int, StatusPageIncident>
     */
    public function upcomingMaintenance(): \Illuminate\Database\Eloquent\Collection
    {
        return $this->incidents()
            ->where('type', 'maintenance')
            ->whereIn('status', ['scheduled', 'in_progress'])
            ->whereNull('resolved_at')
            ->orderBy('scheduled_for')
            ->get();
    }
}
