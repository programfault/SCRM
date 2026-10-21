<?php

namespace App\Enums;

enum DepartmentType: string
{
    case Company = 'company';
    case Store = 'store';
    case Counter = 'counter';

    public function label(): string
    {
        return match ($this) {
            self::Company => '公司',
            self::Store => '门店',
            self::Counter => '专柜',
        };
    }
}
