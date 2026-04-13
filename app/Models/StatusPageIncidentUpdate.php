<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $incident_id
 * @property int $user_id
 * @property string $status_at_update
 * @property string $body
 */
class StatusPageIncidentUpdate extends Model
{
    protected $table = 'status_page_incident_updates';

    protected $fillable = [
        'incident_id',
        'user_id',
        'status_at_update',
        'body',
    ];

    public function incident(): BelongsTo
    {
        return $this->belongsTo(StatusPageIncident::class, 'incident_id');
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
