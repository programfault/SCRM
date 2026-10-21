<?php

namespace App\Models;

use App\Enums\DepartmentType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Department extends Model
{
    protected $fillable = [
        'wecom_department_id',
        'name',
        'parent_wecom_id',
        'parent_id',
        'type',
        'missing_at',
    ];

    protected function casts(): array
    {
        return [
            'type' => DepartmentType::class,
            'missing_at' => 'datetime',
        ];
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id');
    }
}
