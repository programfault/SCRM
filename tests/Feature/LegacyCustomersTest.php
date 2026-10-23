<?php

use App\Enums\DepartmentType;
use App\Enums\UserStatus;
use App\Filament\Pages\LegacyCustomers;
use App\Models\Customer;
use App\Models\Department;
use App\Models\User;
use App\Services\Customers\ImportLegacyCustomers;
use App\Services\Customers\ReconcileWecomCustomers;
use App\Services\Wecom\WecomClient;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Illuminate\Http\UploadedFile;
use Livewire\Livewire;

beforeEach(function () {
    $this->store = Department::query()->create([
        'wecom_department_id' => 3,
        'name' => '麦凯乐',
        'type' => DepartmentType::Store,
    ]);
    $this->counter = Department::query()->create([
        'wecom_department_id' => 4,
        'name' => '欧米茄',
        'parent_id' => $this->store->id,
        'type' => DepartmentType::Counter,
    ]);
    $this->owner = User::factory()->create([
        'name' => '孙斌',
        'wecom_userid' => 'etionsun',
        'status' => UserStatus::Active,
        'store_id' => $this->store->id,
        'counter_id' => $this->counter->id,
    ]);
});

it('assigns a customer when the name and store match one active employee', function () {
    $result = app(ImportLegacyCustomers::class)->import(customerCsv([
        ['克洛伊', '155-5522-5522', '孙斌', '麦凯乐'],
    ]));

    $customer = Customer::query()->first();

    expect($result['created'])->toBe(1)
        ->and($result['assigned'])->toBe(1)
        ->and(Customer::query()->count())->toBe(1)
        ->and($customer->phone)->toBe('15555225522')
        ->and($customer->owner_id)->toBe($this->owner->id)
        ->and($customer->progressLabel())->toBe('待添加');
});

it('assigns a customer when the name and counter match', function () {
    app(ImportLegacyCustomers::class)->import(customerCsv([
        ['克洛伊', '15555225522', '孙斌', '欧米茄'],
    ]));

    expect(Customer::query()->first()->owner_id)->toBe($this->owner->id);
});

it('matches a department name written with halfwidth parentheses', function () {
    $this->store->update(['name' => '麦凯乐（一店）']);

    app(ImportLegacyCustomers::class)->import(customerCsv([
        ['克洛伊', '15555225522', '孙斌', '麦凯乐(一店)'],
    ]));

    expect(Customer::query()->first()->owner_id)->toBe($this->owner->id);
});

it('leaves a customer unassigned when two active employees share the name and department', function () {
    User::factory()->create([
        'name' => '孙斌',
        'wecom_userid' => 'sun2',
        'status' => UserStatus::Active,
        'store_id' => $this->store->id,
        'counter_id' => $this->counter->id,
    ]);

    app(ImportLegacyCustomers::class)->import(customerCsv([
        ['克洛伊', '15555225522', '孙斌', '麦凯乐'],
    ]));

    $customer = Customer::query()->first();

    expect($customer->owner_id)->toBeNull()
        ->and($customer->assignment_note)->toBe('重名')
        ->and($customer->progressLabel())->toBe('未分配');
});

it('does not assign a customer to someone who has left', function () {
    $this->owner->update(['status' => UserStatus::Left]);

    app(ImportLegacyCustomers::class)->import(customerCsv([
        ['克洛伊', '15555225522', '孙斌', '麦凯乐'],
    ]));

    expect(Customer::query()->first()->assignment_note)->toBe('未匹配');
});

it('does not assign a customer when the department is missing', function () {
    app(ImportLegacyCustomers::class)->import(customerCsv([
        ['克洛伊', '15555225522', '孙斌', ''],
    ]));

    expect(Customer::query()->first()->assignment_note)->toBe('缺少部门');
});

it('updates the same phone instead of creating a second customer', function () {
    app(ImportLegacyCustomers::class)->import(customerCsv([
        ['克洛伊', '+86 15555225522', '孙斌', '麦凯乐'],
    ]));
    app(ImportLegacyCustomers::class)->import(customerCsv([
        ['克小姐', '15555225522', '孙斌', '麦凯乐'],
    ]));

    expect(Customer::query()->count())->toBe(1)
        ->and(Customer::query()->first()->name)->toBe('克小姐');
});

it('keeps the owner after the customer is already in wecom', function () {
    app(ImportLegacyCustomers::class)->import(customerCsv([
        ['克洛伊', '15555225522', '孙斌', '麦凯乐'],
    ]));
    Customer::query()->first()->update([
        'wecom_external_userid' => 'ext-1',
        'wecom_added_at' => now(),
    ]);
    $other = User::factory()->create([
        'name' => '王五',
        'wecom_userid' => 'wang',
        'status' => UserStatus::Active,
        'store_id' => $this->store->id,
        'counter_id' => $this->counter->id,
    ]);

    app(ImportLegacyCustomers::class)->import(customerCsv([
        ['克小姐', '15555225522', '王五', '麦凯乐'],
    ]));

    $customer = Customer::query()->first();

    expect($customer->name)->toBe('克小姐')
        ->and($customer->owner_id)->toBe($this->owner->id)
        ->and($customer->owner_id)->not->toBe($other->id);
});

it('skips a row whose phone cannot be a mobile number', function () {
    $result = app(ImportLegacyCustomers::class)->import(customerCsv([
        ['克洛伊', '12345', '孙斌', '麦凯乐'],
        ['张先生', '13800001111', '孙斌', '麦凯乐'],
    ]));

    expect($result['skipped'])->toBe(1)
        ->and(Customer::query()->count())->toBe(1)
        ->and(Customer::query()->first()->phone)->toBe('13800001111');
});

it('marks a customer added when the remark mobile and owner userid match', function () {
    Customer::factory()->create([
        'name' => '克洛伊',
        'phone' => '15555225522',
        'owner_id' => $this->owner->id,
    ]);
    bindExternalWecom([
        'etionsun' => [
            'ext-1' => contactDetail('etionsun', ['15555225522']),
        ],
    ]);

    $result = app(ReconcileWecomCustomers::class)->reconcile();
    $customer = Customer::query()->first();

    expect($result['linked'])->toBe(1)
        ->and($customer->wecom_external_userid)->toBe('ext-1')
        ->and($customer->wecom_added_at)->not->toBeNull()
        ->and($customer->progressLabel())->toBe('已加入')
        ->and(Customer::progress()['added'])->toBe(1)
        ->and(Customer::progress()['pending'])->toBe(0);
});

it('does not change the added time on a second reconcile', function () {
    Customer::factory()->create([
        'name' => '克洛伊',
        'phone' => '15555225522',
        'owner_id' => $this->owner->id,
    ]);
    bindExternalWecom([
        'etionsun' => [
            'ext-1' => contactDetail('etionsun', ['+86 155-5522-5522']),
        ],
    ]);

    $this->travelTo('2026-10-23 10:00:00');
    app(ReconcileWecomCustomers::class)->reconcile();
    $addedAt = Customer::query()->first()->wecom_added_at;

    $this->travelTo('2026-10-23 12:00:00');
    $result = app(ReconcileWecomCustomers::class)->reconcile();

    expect($result['linked'])->toBe(0)
        ->and(Customer::query()->first()->wecom_added_at->eq($addedAt))->toBeTrue()
        ->and(Customer::query()->count())->toBe(1);
});

it('records another employee remark without marking the customer added', function () {
    Customer::factory()->create([
        'name' => '克洛伊',
        'phone' => '15555225522',
        'owner_id' => $this->owner->id,
    ]);
    bindExternalWecom([
        'other' => [
            'ext-9' => contactDetail('other', ['15555225522']),
        ],
    ]);

    $result = app(ReconcileWecomCustomers::class)->reconcile();
    $customer = Customer::query()->first();

    expect($result['conflicts'])->toBe(1)
        ->and($result['linked'])->toBe(0)
        ->and($customer->wecom_external_userid)->toBeNull()
        ->and($customer->conflict_wecom_userid)->toBe('other')
        ->and($customer->progressLabel())->toBe('他人已加')
        ->and(Customer::progress()['pending'])->toBe(1)
        ->and(Customer::progress()['added_by_other'])->toBe(1);
});

it('lets the owner match clear an earlier conflict', function () {
    Customer::factory()->create([
        'name' => '克洛伊',
        'phone' => '15555225522',
        'owner_id' => $this->owner->id,
    ]);
    $detail = [
        'external_contact' => ['name' => '克洛伊'],
        'follow_user' => [
            ['userid' => 'other', 'remark_mobiles' => ['15555225522']],
            ['userid' => 'etionsun', 'remark_mobiles' => ['15555225522']],
        ],
    ];
    bindExternalWecom([
        'other' => ['ext-1' => $detail],
        'etionsun' => ['ext-1' => $detail],
    ]);

    app(ReconcileWecomCustomers::class)->reconcile();
    $customer = Customer::query()->first();

    expect($customer->wecom_external_userid)->toBe('ext-1')
        ->and($customer->conflict_wecom_userid)->toBeNull()
        ->and($customer->progressLabel())->toBe('已加入');
});

it('ignores a wecom contact whose remark has no mobile', function () {
    Customer::factory()->create([
        'name' => '克洛伊',
        'phone' => '15555225522',
        'owner_id' => $this->owner->id,
    ]);
    bindExternalWecom([
        'etionsun' => [
            'ext-1' => contactDetail('etionsun', []),
        ],
    ]);

    app(ReconcileWecomCustomers::class)->reconcile();

    expect(Customer::query()->first()->progressLabel())->toBe('待添加');
});

it('shows an employee only their own customers and phones', function () {
    Customer::factory()->create([
        'name' => '克洛伊',
        'phone' => '15555225522',
        'owner_id' => $this->owner->id,
    ]);
    $other = User::factory()->create([
        'name' => '王五',
        'wecom_userid' => 'wang',
        'status' => UserStatus::Active,
    ]);
    Customer::factory()->create([
        'name' => '张先生',
        'phone' => '13800001111',
        'owner_id' => $other->id,
        'wecom_external_userid' => 'ext-2',
        'wecom_added_at' => now(),
    ]);

    $this->actingAs($this->owner)
        ->get('/me')
        ->assertOk()
        ->assertSee('待添加 1，已加入 0')
        ->assertSee('克洛伊')
        ->assertSee('15555225522')
        ->assertSee('待添加')
        ->assertDontSee('13800001111')
        ->assertDontSee('张先生');
});

it('shows an added customer as finished on the employee page', function () {
    Customer::factory()->create([
        'name' => '克洛伊',
        'phone' => '15555225522',
        'owner_id' => $this->owner->id,
        'wecom_external_userid' => 'ext-1',
        'wecom_added_at' => now(),
    ]);

    $this->actingAs($this->owner)
        ->get('/me')
        ->assertOk()
        ->assertSee('待添加 0，已加入 1')
        ->assertSee('已加入');
});

it('lets an admin import, assign, and reconcile from the page', function () {
    $admin = User::factory()->admin()->create();
    $unassigned = Customer::factory()->create([
        'name' => '未分配客户',
        'phone' => '13900002222',
    ]);
    Customer::factory()->create([
        'name' => '克洛伊',
        'phone' => '15555225522',
        'owner_id' => $this->owner->id,
    ]);
    bindExternalWecom([
        'etionsun' => [
            'ext-1' => contactDetail('etionsun', ['15555225522']),
        ],
    ]);

    $this->actingAs($admin);
    Filament::setCurrentPanel(Filament::getPanel('admin'));

    Livewire::test(LegacyCustomers::class)
        ->assertSee('已导入 2')
        ->assertSee('未分配 1')
        ->assertSee('待添加 1')
        ->callAction('reconcile')
        ->assertSee('已加入 1')
        ->callAction(TestAction::make('assign')->table($unassigned), [
            'owner_id' => $this->owner->id,
        ]);

    expect($unassigned->refresh()->owner_id)->toBe($this->owner->id)
        ->and(Customer::query()->where('phone', '15555225522')->first()->progressLabel())->toBe('已加入');

    expect($this->owner->can('import', Customer::class))->toBeFalse()
        ->and($admin->can('reconcile', Customer::class))->toBeTrue();
});

it('imports a csv upload from the admin page', function () {
    $admin = User::factory()->admin()->create();
    $this->actingAs($admin);
    Filament::setCurrentPanel(Filament::getPanel('admin'));

    $file = UploadedFile::fake()->createWithContent(
        'customers.csv',
        customerCsv([['克洛伊', '15555225522', '孙斌', '麦凯乐']]),
    );

    Livewire::test(LegacyCustomers::class)
        ->callAction('import', [
            'file' => $file,
        ])
        ->assertSee('已导入 1');

    expect(Customer::query()->first()->owner_id)->toBe($this->owner->id);
});

function customerCsv(array $rows): string
{
    $lines = ['姓名,手机号,负责人,部门'];

    foreach ($rows as $row) {
        $lines[] = implode(',', $row);
    }

    return implode("\n", $lines);
}

function contactDetail(string $userId, array $mobiles): array
{
    return [
        'external_contact' => ['name' => '克洛伊'],
        'follow_user' => [[
            'userid' => $userId,
            'remark_mobiles' => $mobiles,
        ]],
    ];
}

function bindExternalWecom(array $contactsByUser): void
{
    test()->instance(WecomClient::class, new class($contactsByUser) implements WecomClient
    {
        public function __construct(private array $contactsByUser) {}

        public function authorizationUrl(): string
        {
            return 'https://wecom.test/oauth';
        }

        public function userIdFromCode(string $code): string
        {
            return $code;
        }

        public function departments(): array
        {
            return [];
        }

        public function users(int $departmentId): array
        {
            return [];
        }

        public function externalFollowUserIds(): array
        {
            return array_keys($this->contactsByUser);
        }

        public function externalContactIds(string $userId): array
        {
            return array_map(strval(...), array_keys($this->contactsByUser[$userId] ?? []));
        }

        public function externalContact(string $externalUserId): array
        {
            foreach ($this->contactsByUser as $contacts) {
                if (isset($contacts[$externalUserId])) {
                    return $contacts[$externalUserId];
                }
            }

            return [];
        }
    });
}
