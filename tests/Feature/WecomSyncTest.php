<?php

use App\Enums\DepartmentType;
use App\Enums\UserStatus;
use App\Models\Department;
use App\Models\User;
use App\Services\Wecom\WecomRequestFailed;
use EasyWeChat\Kernel\Exceptions\HttpException;

it('maps counters to their parent store and skips the unsorted department', function () {
    bindWecom(sampleDepartments(), sampleUsers());

    sync();

    expect(Department::query()->where('name', '其他（待设置部门）')->exists())->toBeFalse();

    $omega = Department::query()->where('name', '欧米茄')->first();
    $store = Department::query()->where('name', '麦凯乐')->first();

    expect($omega->type)->toBe(DepartmentType::Counter)
        ->and($store->type)->toBe(DepartmentType::Store)
        ->and($omega->parent_id)->toBe($store->id);

    $person = User::query()->where('wecom_userid', 'zhao')->first();

    expect($person->store_id)->toBe($store->id)
        ->and($person->counter_id)->toBe($omega->id)
        ->and($person->mobile)->toBeNull();
});

it('does not duplicate departments or users on a second sync', function () {
    bindWecom(sampleDepartments(), sampleUsers());

    sync();
    sync();

    expect(Department::query()->count())->toBe(7)
        ->and(User::query()->whereNotNull('wecom_userid')->count())->toBe(2);
});

it('moves a person when their counter changes', function () {
    bindWecom(sampleDepartments(), sampleUsers());
    sync();

    $users = sampleUsers();
    $users[4] = [];
    $users[7][] = [
        'userid' => 'zhao',
        'name' => '赵亚晶',
        'department' => [7],
        'main_department' => 7,
        'status' => 1,
    ];

    bindWecom(sampleDepartments(), $users);
    sync();

    $person = User::query()->where('wecom_userid', 'zhao')->first();
    $roosevelt = Department::query()->where('name', '罗斯福店')->first();
    $rolex = Department::query()->where('name', '劳力士')->first();

    expect($person->store_id)->toBe($roosevelt->id)
        ->and($person->counter_id)->toBe($rolex->id)
        ->and(User::query()->where('wecom_userid', 'zhao')->count())->toBe(1);
});

it('renames a department without creating another row', function () {
    bindWecom(sampleDepartments(), sampleUsers());
    sync();

    $departments = sampleDepartments();
    $departments[2]['name'] = '麦凯乐总店';

    bindWecom($departments, sampleUsers());
    sync();

    expect(Department::query()->where('wecom_department_id', 3)->value('name'))->toBe('麦凯乐总店')
        ->and(Department::query()->where('wecom_department_id', 3)->count())->toBe(1);
});

it('refuses login for a person who has left', function () {
    $users = sampleUsers();
    $users[7][0]['status'] = 5;
    bindWecom(sampleDepartments(), $users, 'jin');
    sync();

    $this->get('/wecom/callback?code=jin')
        ->assertRedirect(route('wecom.denied'));

    $this->assertGuest();
    expect(User::query()->where('wecom_userid', 'jin')->first()->status)->toBe(UserStatus::Left);
});

it('refuses login for a disabled person', function () {
    $users = sampleUsers();
    $users[4][0]['status'] = 2;
    bindWecom(sampleDepartments(), $users, 'zhao');
    sync();

    $this->get('/wecom/callback?code=zhao')->assertRedirect(route('wecom.denied'));
    $this->assertGuest();
    expect(User::query()->where('wecom_userid', 'zhao')->first()->status)->toBe(UserStatus::Disabled);
});

it('records an access token failure without marking people as left', function () {
    bindWecom(sampleDepartments(), sampleUsers());
    sync();

    bindWecom(sampleDepartments(), sampleUsers(), failure: new HttpException('invalid credential'));
    $log = sync();

    expect($log->status)->toBe('failed')
        ->and($log->message)->toBe('invalid credential')
        ->and(User::query()->where('wecom_userid', 'zhao')->first()->status)->toBe(UserStatus::Active);
});

it('does not mark active people as left when the request fails', function () {
    bindWecom(sampleDepartments(), sampleUsers());
    sync();

    bindWecom(sampleDepartments(), sampleUsers(), failure: new WecomRequestFailed('timeout'));
    $log = sync();

    expect($log->status)->toBe('failed')
        ->and(User::query()->where('wecom_userid', 'zhao')->first()->status)->toBe(UserStatus::Active);
});

it('keeps a local admin through sync', function () {
    $admin = User::factory()->admin()->create([
        'name' => '管理员',
        'wecom_userid' => null,
    ]);

    bindWecom(sampleDepartments(), sampleUsers());
    sync();

    $admin->refresh();

    expect($admin->is_admin)->toBeTrue()
        ->and($admin->wecom_userid)->toBeNull()
        ->and($admin->status)->toBe(UserStatus::Active);
});

it('does not create a user who has not been synced', function () {
    bindWecom(sampleDepartments(), sampleUsers(), 'nobody');

    $this->get('/wecom/callback?code=nobody')->assertRedirect(route('wecom.denied'));

    expect(User::query()->where('wecom_userid', 'nobody')->exists())->toBeFalse();
    $this->assertGuest();
});

it('sends the WeCom browser to passwordless entry', function () {
    $this->withHeader('User-Agent', 'Mozilla/5.0 wxwork/4.1.0 MicroMessenger')
        ->get('/')
        ->assertRedirect(route('wecom.start'));

    $this->withHeader('User-Agent', 'Mozilla/5.0 wxwork/4.1.0 MicroMessenger')
        ->get('/admin/login')
        ->assertRedirect(route('wecom.start'));
});

it('keeps the password login for a desktop browser', function () {
    $this->get('/')->assertRedirect('/admin/login');
    $this->get('/admin/login')->assertOk();
});

it('logs a synced active person in without a login page', function () {
    bindWecom(sampleDepartments(), sampleUsers(), 'zhao');
    sync();

    $this->get('/wecom/callback?code=zhao')
        ->assertRedirect(route('me'));

    $this->get('/me')
        ->assertOk()
        ->assertSee('已进入，赵亚晶')
        ->assertSee('麦凯乐')
        ->assertSee('欧米茄')
        ->assertDontSee('密码');
});
