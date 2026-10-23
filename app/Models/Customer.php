<?php

namespace App\Models;

use Database\Factories\CustomerFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'name',
    'phone',
    'owner_id',
    'imported_owner_name',
    'imported_department_name',
    'assignment_note',
    'wecom_external_userid',
    'wecom_added_at',
    'conflict_wecom_userid',
    'conflict_external_userid',
])]
class Customer extends Model
{
    /** @use HasFactory<CustomerFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'wecom_added_at' => 'datetime',
        ];
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    public function progressLabel(): string
    {
        if ($this->wecom_external_userid !== null) {
            return '已加入';
        }

        if ($this->conflict_wecom_userid !== null) {
            return '他人已加';
        }

        if ($this->owner_id === null) {
            return '未分配';
        }

        return '待添加';
    }

    /**
     * @return array{imported: int, assigned: int, unassigned: int, added: int, pending: int, added_by_other: int}
     */
    public static function progress(): array
    {
        return [
            'imported' => static::query()->count(),
            'assigned' => static::query()->whereNotNull('owner_id')->count(),
            'unassigned' => static::query()->whereNull('owner_id')->count(),
            'added' => static::query()->whereNotNull('wecom_external_userid')->count(),
            'pending' => static::query()->whereNotNull('owner_id')->whereNull('wecom_external_userid')->count(),
            'added_by_other' => static::query()->whereNull('wecom_external_userid')->whereNotNull('conflict_wecom_userid')->count(),
        ];
    }
}
