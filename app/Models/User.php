<?php

namespace App\Models;

use App\Enums\UserStatus;
use Database\Factories\UserFactory;
use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

#[Fillable([
    'name',
    'email',
    'password',
    'wecom_userid',
    'mobile',
    'position',
    'avatar',
    'status',
    'left_at',
    'is_admin',
    'store_id',
    'counter_id',
])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable implements FilamentUser
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'left_at' => 'datetime',
            'is_admin' => 'boolean',
            'status' => UserStatus::class,
        ];
    }

    public function store(): BelongsTo
    {
        return $this->belongsTo(Department::class, 'store_id');
    }

    public function counter(): BelongsTo
    {
        return $this->belongsTo(Department::class, 'counter_id');
    }

    public function customers(): HasMany
    {
        return $this->hasMany(Customer::class, 'owner_id');
    }

    public function canLogin(): bool
    {
        return ($this->status ?? UserStatus::Active)->canLogin();
    }

    public function canAccessPanel(Panel $panel): bool
    {
        return $this->is_admin && $this->canLogin();
    }
}
