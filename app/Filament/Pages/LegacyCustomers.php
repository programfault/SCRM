<?php

namespace App\Filament\Pages;

use App\Enums\DepartmentType;
use App\Enums\UserStatus;
use App\Models\Customer;
use App\Models\Department;
use App\Models\User;
use App\Services\Customers\AssignCustomerOwner;
use App\Services\Customers\CustomerAssignmentFailed;
use App\Services\Customers\CustomerImportFailed;
use App\Services\Customers\ImportLegacyCustomers;
use App\Services\Customers\ReconcileWecomCustomers;
use App\Services\Wecom\WecomNotConfigured;
use App\Services\Wecom\WecomRequestFailed;
use Filament\Actions\Action;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\EmbeddedTable;
use Filament\Schemas\Components\Text;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;

class LegacyCustomers extends Page implements HasTable
{
    use InteractsWithTable;

    protected static string|\BackedEnum|null $navigationIcon = Heroicon::OutlinedClipboardDocumentList;

    protected static ?string $navigationLabel = '旧客户';

    protected static ?string $title = '旧客户';

    protected static ?string $slug = 'legacy-customers';

    protected static ?int $navigationSort = 3;

    public static function canAccess(): bool
    {
        $user = auth()->user();

        return $user !== null && $user->can('viewAny', Customer::class);
    }

    public function content(Schema $schema): Schema
    {
        return $schema->components([
            Text::make(fn (): string => $this->progressText()),
            EmbeddedTable::make(),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(Customer::query()->with(['owner.store']))
            ->columns([
                TextColumn::make('name')->label('客户')->searchable(),
                TextColumn::make('phone')->label('手机号')->searchable(),
                TextColumn::make('owner.name')->label('负责人')->placeholder('—'),
                TextColumn::make('owner.store.name')->label('门店')->placeholder('—'),
                TextColumn::make('imported_department_name')->label('导入部门')->placeholder('—'),
                TextColumn::make('progress_label')
                    ->label('进度')
                    ->getStateUsing(fn (Customer $record): string => $record->progressLabel()),
                TextColumn::make('assignment_note')->label('说明')->placeholder('—'),
                TextColumn::make('conflict_wecom_userid')
                    ->label('他人名片')
                    ->placeholder('—')
                    ->formatStateUsing(function (?string $state): ?string {
                        if ($state === null || $state === '') {
                            return null;
                        }

                        $name = User::query()->where('wecom_userid', $state)->value('name');

                        return $name === null ? $state : "{$name}（{$state}）";
                    }),
            ])
            ->filters([
                SelectFilter::make('progress')
                    ->label('进度')
                    ->options([
                        'unassigned' => '未分配',
                        'pending' => '待添加',
                        'added' => '已加入',
                        'added_by_other' => '他人已加',
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return match ($data['value'] ?? null) {
                            'unassigned' => $query->whereNull('owner_id')->whereNull('wecom_external_userid'),
                            'pending' => $query->whereNotNull('owner_id')->whereNull('wecom_external_userid'),
                            'added' => $query->whereNotNull('wecom_external_userid'),
                            'added_by_other' => $query->whereNull('wecom_external_userid')->whereNotNull('conflict_wecom_userid'),
                            default => $query,
                        };
                    }),
                SelectFilter::make('store_id')
                    ->label('门店')
                    ->options(fn (): array => Department::query()
                        ->where('type', DepartmentType::Store)
                        ->orderBy('name')
                        ->pluck('name', 'id')
                        ->all())
                    ->query(fn (Builder $query, array $data): Builder => $query->when(
                        $data['value'] ?? null,
                        fn (Builder $query, int|string $storeId): Builder => $query->whereHas(
                            'owner',
                            fn (Builder $owner): Builder => $owner->where('store_id', $storeId),
                        ),
                    )),
                SelectFilter::make('owner_id')
                    ->label('负责人')
                    ->options(fn (): array => $this->ownerOptions())
                    ->searchable()
                    ->query(fn (Builder $query, array $data): Builder => $query->when(
                        $data['value'] ?? null,
                        fn (Builder $query, int|string $ownerId): Builder => $query->where('owner_id', $ownerId),
                    )),
            ])
            ->recordActions([
                Action::make('assign')
                    ->label('指定负责人')
                    ->visible(fn (Customer $record): bool => $record->wecom_external_userid === null)
                    ->authorize('update')
                    ->schema([
                        Select::make('owner_id')
                            ->label('负责人')
                            ->options(fn (): array => $this->ownerOptions())
                            ->searchable()
                            ->required(),
                    ])
                    ->action(function (Customer $record, array $data): void {
                        $owner = User::query()->find($data['owner_id']);

                        if ($owner === null) {
                            Notification::make()->title('找不到这个员工')->danger()->send();

                            return;
                        }

                        try {
                            app(AssignCustomerOwner::class)->assign($record, $owner);
                        } catch (CustomerAssignmentFailed $exception) {
                            Notification::make()->title($exception->getMessage())->danger()->send();

                            return;
                        }

                        Notification::make()->title('已指定负责人')->success()->send();
                    }),
            ])
            ->defaultSort('id', 'desc')
            ->paginated([25, 50, 100])
            ->emptyStateHeading('还没有旧客户')
            ->emptyStateDescription('导入 CSV 后，姓名和部门能对上的客户会分给在职员工。');
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('import')
                ->label('导入')
                ->authorize(fn (): bool => auth()->user()?->can('import', Customer::class) ?? false)
                ->schema([
                    FileUpload::make('file')
                        ->label('CSV 文件')
                        ->helperText('表头：姓名、手机号、负责人、部门。同一手机号再次导入只会更新，不会新建第二条。')
                        ->storeFiles(false)
                        ->required(),
                ])
                ->action(function (array $data): void {
                    try {
                        $result = app(ImportLegacyCustomers::class)->import($this->csvFromUpload($data['file'] ?? null));
                    } catch (CustomerImportFailed $exception) {
                        Notification::make()->title($exception->getMessage())->danger()->send();

                        return;
                    }

                    Notification::make()
                        ->title("新建 {$result['created']}，更新 {$result['updated']}，已分配 {$result['assigned']}，未分配 {$result['unassigned']}，跳过 {$result['skipped']}")
                        ->success()
                        ->send();
                }),
            Action::make('reconcile')
                ->label('核对已添加')
                ->requiresConfirmation()
                ->modalDescription('读取企业微信客户名片上的备注手机号。号码和负责人都对上时，标成已加入。')
                ->authorize(fn (): bool => auth()->user()?->can('reconcile', Customer::class) ?? false)
                ->action(function (): void {
                    try {
                        $result = app(ReconcileWecomCustomers::class)->reconcile();
                    } catch (WecomNotConfigured) {
                        Notification::make()->title('请先保存企业微信配置')->danger()->send();

                        return;
                    } catch (WecomRequestFailed $exception) {
                        Notification::make()->title($exception->getMessage())->danger()->send();

                        return;
                    }

                    Notification::make()
                        ->title("新标为已加入 {$result['linked']}，新发现他人已加 {$result['conflicts']}")
                        ->success()
                        ->send();
                }),
        ];
    }

    private function progressText(): string
    {
        $progress = Customer::progress();

        return "已导入 {$progress['imported']}，已分配 {$progress['assigned']}，未分配 {$progress['unassigned']}，已加入 {$progress['added']}，待添加 {$progress['pending']}，他人已加 {$progress['added_by_other']}";
    }

    /**
     * @return array<int, string>
     */
    private function ownerOptions(): array
    {
        return User::query()
            ->where('status', UserStatus::Active)
            ->whereNotNull('wecom_userid')
            ->with('store:id,name')
            ->orderBy('name')
            ->get()
            ->mapWithKeys(fn (User $user): array => [
                $user->id => $user->store === null ? $user->name : "{$user->name}（{$user->store->name}）",
            ])
            ->all();
    }

    private function csvFromUpload(mixed $file): string
    {
        if (is_array($file)) {
            $file = collect($file)->first();
        }

        if ($file instanceof TemporaryUploadedFile || $file instanceof \Illuminate\Http\UploadedFile) {
            return $file->get();
        }

        if (is_string($file) && is_file($file)) {
            return (string) file_get_contents($file);
        }

        throw new CustomerImportFailed('没有读到 CSV 文件。');
    }
}
