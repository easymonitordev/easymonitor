<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Represents a project — a grouping of monitors (e.g. "ConvertHub = main site + APIs").
 *
 * Projects can be personal (team_id=null) or team-owned. When a monitor is assigned
 * to a project, the project's team ownership governs access control.
 *
 * @property int $id
 * @property int $user_id
 * @property int|null $team_id
 * @property string $name
 * @property string|null $description
 * @property string|null $color
 */
class Project extends Model
{
    /** @use HasFactory<\Database\Factories\ProjectFactory> */
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'user_id',
        'team_id',
        'name',
        'description',
        'color',
    ];

    /**
     * Get the user that owns the project
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the team that owns the project (optional)
     */
    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    /**
     * Get all monitors in this project
     */
    public function monitors(): HasMany
    {
        return $this->hasMany(Monitor::class);
    }

    /**
     * Compute aggregate status based on monitors inside this project
     *
     * Returns 'operational' (all up), 'degraded' (some down), 'outage' (all down),
     * or 'unknown' (empty or all pending).
     */
    public function aggregateStatus(): string
    {
        $monitors = $this->monitors()->where('is_active', true)->get();

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
}
