<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $status_page_id
 * @property string $type 'project' | 'monitor'
 * @property int|null $project_id
 * @property int|null $monitor_id
 * @property string|null $label
 * @property int $sort_order
 */
class StatusPageItem extends Model
{
    protected $fillable = [
        'status_page_id',
        'type',
        'project_id',
        'monitor_id',
        'label',
        'sort_order',
    ];

    public function statusPage(): BelongsTo
    {
        return $this->belongsTo(StatusPage::class);
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function monitor(): BelongsTo
    {
        return $this->belongsTo(Monitor::class);
    }
}
