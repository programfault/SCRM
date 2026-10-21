<?php

namespace App\Enums;

enum UserStatus: string
{
    case Active = 'active';
    case Disabled = 'disabled';
    case Inactive = 'inactive';
    case Left = 'left';

    public function label(): string
    {
        return match ($this) {
            self::Active => '在职',
            self::Disabled => '已禁用',
            self::Inactive => '未激活',
            self::Left => '已离职',
        };
    }

    public function canLogin(): bool
    {
        return $this === self::Active || $this === self::Inactive;
    }

    public static function fromWecom(int $status): self
    {
        return match ($status) {
            2 => self::Disabled,
            4 => self::Inactive,
            5 => self::Left,
            default => self::Active,
        };
    }
}
