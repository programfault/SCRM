<?php

use App\Filament\Pages\SystemSettings;
use App\Models\User;
use App\Models\WecomSetting;
use Filament\Facades\Filament;
use Livewire\Livewire;

beforeEach(function () {
    $this->actingAs(User::factory()->admin()->create());
    Filament::setCurrentPanel(Filament::getPanel('admin'));
});

it('stores a pasted secret and does not keep it in the form', function () {
    Livewire::test(SystemSettings::class)
        ->set('data.corp_id', ' ww123 ')
        ->set('data.agent_id', ' 1000002 ')
        ->set('data.secret', " abc-secret \n")
        ->call('save')
        ->assertSet('data.secret', null)
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
        ->assertSee('已写入 10 位');

    $setting = WecomSetting::current();

    expect($setting->corp_id)->toBe('ww456')
        ->and($setting->secret)->toBe('abc-secret');
});
