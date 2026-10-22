<?php

namespace App\Filament\Pages;

use App\Filament\Widgets\SyncLogTable;
use App\Jobs\SyncWecomJob;
use App\Models\WecomSetting;
use App\Services\Wecom\SyncWecom;
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
            'secret' => $setting?->secret,
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
                    ->autocomplete('off')
                    ->dehydrated()
                    ->helperText('保存后仍显示在这里。组织架构里的员工要另点右上角「同步企业微信」才会出现。')
                    ->required(fn (): bool => blank(WecomSetting::current()?->secret)),
                Placeholder::make('secret_status')
                    ->label('Secret 状态')
                    ->content(function (): string {
                        $saved = WecomSetting::current()?->secret;

                        if (! is_string($saved) || $saved === '') {
                            return '还没有写入。';
                        }

                        return '已写入 '.strlen($saved).' 位。输入框留空不代表没保存。';
                    }),
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
        $secret = trim((string) ($this->data['secret'] ?? ''));
        $corpId = trim((string) ($this->data['corp_id'] ?? ''));
        $agentId = trim((string) ($this->data['agent_id'] ?? ''));

        $this->data['corp_id'] = $corpId;
        $this->data['agent_id'] = $agentId;
        $this->data['secret'] = $secret !== '' ? $secret : null;

        $this->getSchema('content')->getState();

        $setting = WecomSetting::current() ?? new WecomSetting;
        $setting->corp_id = $corpId;
        $setting->agent_id = $agentId;

        if ($secret !== '') {
            $setting->secret = $secret;
        }

        if (blank($setting->secret)) {
            Notification::make()->title('应用 Secret 没有写入，请重新粘贴后再保存')->danger()->send();

            return;
        }

        $setting->save();
        $this->data['secret'] = $setting->secret;

        Notification::make()
            ->title($secret !== ''
                ? '应用 Secret 已写入，共 '.strlen($secret).' 位'
                : '已保存，应用 Secret 保持原值，共 '.strlen((string) $setting->secret).' 位')
            ->success()
            ->send();
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('sync')
                ->label('同步企业微信')
                ->requiresConfirmation()
                ->modalDescription('按企业微信通讯录更新部门和员工。已同步且在职的员工可以从企业微信直接进入，不出现登录页。')
                ->action(fn () => $this->startSync()),
        ];
    }

    public function startSync(): void
    {
        if (! WecomSetting::current()?->isReady()) {
            Notification::make()->title('请先保存企业 ID、AgentId 和 Secret')->danger()->send();

            return;
        }

        $log = app(SyncWecom::class)->open(auth()->id());

        SyncWecomJob::dispatch(syncLogId: $log->id)->afterResponse();

        $this->dispatch('sync-log-updated');

        Notification::make()->title('同步已开始，记录已添加')->success()->send();
    }

    protected function getFooterWidgets(): array
    {
        return [
            SyncLogTable::class,
        ];
    }
}
