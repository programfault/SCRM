<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WecomSetting extends Model
{
    protected $fillable = [
        'corp_id',
        'agent_id',
        'secret',
    ];

    protected $hidden = [
        'secret',
    ];

    protected function casts(): array
    {
        return [
            'secret' => 'encrypted',
        ];
    }

    public static function current(): ?self
    {
        return self::query()->first();
    }

    public function isReady(): bool
    {
        return $this->corp_id !== '' && $this->agent_id !== '' && $this->secret !== '';
    }
}
