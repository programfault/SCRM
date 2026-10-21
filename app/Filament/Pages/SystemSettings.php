<?php

namespace App\Filament\Pages;

use App\Filament\Widgets\SyncLogTable;
use App\Jobs\SyncWecomJob;
use App\Models\WecomSetting;
use Filament\Actions\Action;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Form;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;

class SystemSettings extends Page
{
    protected static string|\BackedEnum|null $navigationIcon = Heroicon::OutlinedCog6Tooth;

    protected static ?string $navigationLabel = '系统配置';

    protected static ?string $title = '系统配置';

    protected static ?int $navigationSort = 2;

    /**
     * @var array<string, mixed>|null
     */
    public ?array $data = [];

    public function mount(): void
    {
        $setting = WecomSetting::current();

        $this->data = [
            'corp_id' => $setting?->corp_id,
            'agent_id' => $setting?->agent_id,
            'secret' => null,
        ];
    }

    public function content(Schema $schema): Schema
    {
        return $schema->components([
            Form::make([
                TextInput::make('corp_id')->label('企业 ID')->required()->maxLength(64),
                TextInput::make('agent_id')->label('应用 AgentId')->required()->maxLength(32),
                TextInput::make('secret')
                    ->label('应用 Secret')
                    ->password()
                    ->revealable()
                    ->helperText('已保存过的 Secret 不会回显。留空表示不修改。')
                    ->required(fn (): bool => blank(WecomSetting::current()?->secret)),
                Placeholder::make('home')
                    ->label('企业微信应用主页')
                    ->content(fn (): string => url('/wecom')),
                Placeholder::make('callback')
                    ->label('网页授权回调')
                    ->content(fn (): string => url('/wecom/callback')),
            ])
                ->statePath('data')
                ->livewireSubmitHandler('save')
                ->footer([
                    Action::make('save')->label('保存')->submit('save'),
                ]),
        ]);
    }

    public function save(): void
    {
        $state = $this->getSchema('content')->getState();
        $values = $state['data'] ?? $state;
        $setting = WecomSetting::current() ?? new WecomSetting;

        $setting->corp_id = $values['corp_id'];
        $setting->agent_id = $values['agent_id'];

        if (filled($values['secret'] ?? null)) {
            $setting->secret = $values['secret'];
        }

        $setting->save();

        Notification::make()->title('已保存')->success()->send();
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('sync')
                ->label('同步企业微信')
                ->requiresConfirmation()
                ->modalDescription('按企业微信通讯录更新部门和员工。已同步且在职的员工可以从企业微信直接进入，不出现登录页。')
                ->action(function (): void {
                    if (! WecomSetting::current()?->isReady()) {
                        Notification::make()->title('请先保存企业 ID、AgentId 和 Secret')->danger()->send();

                        return;
                    }

                    SyncWecomJob::dispatch(auth()->id());

                    Notification::make()->title('同步已开始')->success()->send();
                }),
        ];
    }

    protected function getFooterWidgets(): array
    {
        return [
            SyncLogTable::class,
        ];
    }
}
