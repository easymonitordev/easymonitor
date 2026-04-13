<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Monitor extends Model
{
    /** @use HasFactory<\Database\Factories\MonitorFactory> */
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'user_id',
        'team_id',
        'project_id',
        'name',
        'url',
        'is_active',
        'status',
        'check_interval',
        'last_checked_at',
        'next_run_at',
        'last_error',
        'consecutive_failures',
        'failure_threshold',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'last_checked_at' => 'datetime',
            'next_run_at' => 'datetime',
        ];
    }

    /**
     * Get the user that owns the monitor
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the team that owns the monitor (optional)
     */
    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    /**
     * Get the project this monitor belongs to (optional)
     */
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    /**
     * Return the effective team for access control.
     *
     * When a monitor is inside a project, the project's team wins over
     * the monitor's own team_id. Standalone monitors fall back to team_id.
     */
    public function effectiveTeam(): ?Team
    {
        if ($this->project_id) {
            return $this->project?->team;
        }

        return $this->team;
    }

    /**
     * Return the effective owner user_id for access control.
     *
     * When a monitor is inside a project, the project owner governs.
     */
    public function effectiveUserId(): int
    {
        if ($this->project_id && $this->project) {
            return $this->project->user_id;
        }

        return $this->user_id;
    }

    /**
     * Check if the monitor is currently up
     */
    public function isUp(): bool
    {
        return $this->status === 'up';
    }

    /**
     * Check if the monitor is currently down
     */
    public function isDown(): bool
    {
        return $this->status === 'down';
    }

    /**
     * Check if the monitor is pending first check
     */
    public function isPending(): bool
    {
        return $this->status === 'pending';
    }

    /**
     * Get all check results for this monitor
     */
    public function checkResults(): HasMany
    {
        return $this->hasMany(CheckResult::class);
    }

    /**
     * Get the latest check result for this monitor
     */
    public function latestCheckResult(): HasOne
    {
        return $this->hasOne(CheckResult::class)->latestOfMany();
    }
}
