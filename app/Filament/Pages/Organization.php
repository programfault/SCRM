<?php

namespace App\Filament\Pages;

use App\Filament\Widgets\DepartmentTable;
use App\Filament\Widgets\EmployeeTable;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;

class Organization extends Page
{
    protected static string|\BackedEnum|null $navigationIcon = Heroicon::OutlinedBuildingOffice2;

    protected static ?string $navigationLabel = '组织架构';

    protected static ?string $title = '组织架构';

    protected static ?int $navigationSort = 1;

    protected function getFooterWidgets(): array
    {
        return [
            DepartmentTable::class,
            EmployeeTable::class,
        ];
    }
}
