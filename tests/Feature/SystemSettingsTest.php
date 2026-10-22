<?php

use App\Filament\Pages\SystemSettings;
use App\Jobs\SyncWecomJob;
use App\Models\SyncLog;
use App\Models\User;
use App\Models\WecomSetting;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\Bus;
use Livewire\Livewire;

beforeEach(function () {
    $this->actingAs(User::factory()->admin()->create());
    Filament::setCurrentPanel(Filament::getPanel('admin'));
});

it('stores a pasted secret and keeps it visible', function () {
    Livewire::test(SystemSettings::class)
        ->set('data.corp_id', ' ww123 ')
        ->set('data.agent_id', ' 1000002 ')
        ->set('data.secret', " abc-secret \n")
        ->call('save')
        ->assertSet('data.secret', 'abc-secret')
        ->assertSee('已写入 10 位');

    $setting = WecomSetting::current();

    expect($setting->corp_id)->toBe('ww123')
        ->and($setting->agent_id)->toBe('1000002')
        ->and($setting->secret)->toBe('abc-secret');
});

it('keeps the saved secret when the field is left empty', function () {
    WecomSetting::query()->create([
        'corp_id' => 'ww123',
        'agent_id' => '1000002',
        'secret' => 'abc-secret',
    ]);

    Livewire::test(SystemSettings::class)
        ->set('data.corp_id', 'ww456')
        ->set('data.secret', null)
        ->call('save')
        ->assertSet('data.secret', 'abc-secret')
        ->assertSee('已写入 10 位');

    $setting = WecomSetting::current();

    expect($setting->corp_id)->toBe('ww456')
        ->and($setting->secret)->toBe('abc-secret');
});

it('adds a sync record as soon as sync is clicked', function () {
    WecomSetting::query()->create([
        'corp_id' => 'ww123',
        'agent_id' => '1000002',
        'secret' => 'abc-secret',
    ]);

    Bus::fake();

    Livewire::test(SystemSettings::class)->call('startSync');

    $log = SyncLog::query()->first();

    expect($log)->not->toBeNull()
        ->and($log->status)->toBe('queued')
        ->and($log->triggered_by)->toBe(auth()->id());

    Bus::assertDispatched(SyncWecomJob::class, fn (SyncWecomJob $job): bool => $job->syncLogId === $log->id);
});
