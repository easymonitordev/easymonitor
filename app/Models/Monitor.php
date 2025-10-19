<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

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
        'name',
        'url',
        'is_active',
        'status',
        'check_interval',
        'last_checked_at',
        'last_error',
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
}
