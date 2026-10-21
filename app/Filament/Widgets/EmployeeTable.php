<?php

namespace App\Filament\Widgets;

use App\Enums\UserStatus;
use App\Models\User;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;

class EmployeeTable extends TableWidget
{
    protected static bool $isDiscovered = false;

    protected int|string|array $columnSpan = 'full';

    public function table(Table $table): Table
    {
        return $table
            ->heading('员工')
            ->query(User::query()->with(['store', 'counter'])->whereNotNull('wecom_userid'))
            ->columns([
                TextColumn::make('name')->label('姓名'),
                TextColumn::make('wecom_userid')->label('人员编号'),
                TextColumn::make('store.name')->label('门店')->placeholder('—'),
                TextColumn::make('counter.name')->label('专柜')->placeholder('—'),
                TextColumn::make('position')->label('职位')->placeholder('—'),
                TextColumn::make('status')
                    ->label('状态')
                    ->formatStateUsing(fn (UserStatus $state): string => $state->label()),
            ])
            ->defaultSort('name')
            ->paginated([10, 25, 50])
            ->emptyStateHeading('还没有员工')
            ->emptyStateDescription('同步企业微信后，在职员工会显示在这里。');
    }
}
