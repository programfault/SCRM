<?php

namespace App\Filament\Widgets;

use App\Enums\DepartmentType;
use App\Models\Department;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;

class DepartmentTable extends TableWidget
{
    protected static bool $isDiscovered = false;

    protected int|string|array $columnSpan = 'full';

    public function table(Table $table): Table
    {
        return $table
            ->heading('部门')
            ->query(Department::query()->with('parent')->whereNull('missing_at'))
            ->columns([
                TextColumn::make('name')->label('名称'),
                TextColumn::make('type')
                    ->label('类型')
                    ->formatStateUsing(fn (DepartmentType $state): string => $state->label()),
                TextColumn::make('parent.name')->label('上级')->placeholder('—'),
                TextColumn::make('wecom_department_id')->label('企业微信部门编号'),
            ])
            ->defaultSort('wecom_department_id')
            ->paginated(false)
            ->emptyStateHeading('还没有部门')
            ->emptyStateDescription('同步企业微信后，门店和专柜会显示在这里。');
    }
}
