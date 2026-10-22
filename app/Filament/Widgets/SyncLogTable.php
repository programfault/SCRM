<?php

namespace App\Filament\Widgets;

use App\Models\SyncLog;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Livewire\Attributes\On;

class SyncLogTable extends TableWidget
{
    protected static bool $isDiscovered = false;

    protected static bool $isLazy = false;

    protected int|string|array $columnSpan = 'full';

    #[On('sync-log-updated')]
    public function refreshSyncLog(): void {}

    public function table(Table $table): Table
    {
        return $table
            ->heading('同步记录')
            ->poll('3s')
            ->query(SyncLog::query()->with('trigger')->latest('id'))
            ->columns([
                TextColumn::make('started_at')->label('开始时间')->dateTime('Y-m-d H:i'),
                TextColumn::make('trigger.name')->label('操作人')->placeholder('—'),
                TextColumn::make('status')->label('结果')->formatStateUsing(fn (string $state): string => match ($state) {
                    'queued' => '排队中',
                    'succeeded' => '成功',
                    'failed' => '失败',
                    default => '进行中',
                }),
                TextColumn::make('departments_created')->label('新增部门'),
                TextColumn::make('departments_updated')->label('更新部门'),
                TextColumn::make('users_created')->label('新增员工'),
                TextColumn::make('users_updated')->label('更新员工'),
                TextColumn::make('users_left')->label('离职'),
                TextColumn::make('message')->label('说明')->wrap()->placeholder('—'),
            ])
            ->paginated([10, 25])
            ->emptyStateHeading('还没有同步记录');
    }
}
