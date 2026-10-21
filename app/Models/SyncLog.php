<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SyncLog extends Model
{
    protected $fillable = [
        'triggered_by',
        'status',
        'departments_created',
        'departments_updated',
        'users_created',
        'users_updated',
        'users_left',
        'message',
        'started_at',
        'finished_at',
    ];

    protected function casts(): array
    {
        return [
            'started_at' => 'datetime',
            'finished_at' => 'datetime',
        ];
    }

    public function trigger(): BelongsTo
    {
        return $this->belongsTo(User::class, 'triggered_by');
    }
}
