<?php

use App\Models\SyncLog;
use App\Services\Wecom\SyncWecom;
use App\Services\Wecom\WecomClient;
use Illuminate\Foundation\Testing\RefreshDatabase;

pest()->extend(Tests\TestCase::class)
    ->use(RefreshDatabase::class)
    ->in('Feature');

function sampleDepartments(): array
{
    return [
        ['id' => 1, 'name' => '大连天辰表行有限公司', 'parentid' => 0],
        ['id' => 2, 'name' => '其他（待设置部门）', 'parentid' => 1],
        ['id' => 3, 'name' => '麦凯乐', 'parentid' => 1],
        ['id' => 4, 'name' => '欧米茄', 'parentid' => 3],
        ['id' => 5, 'name' => '浪琴', 'parentid' => 3],
        ['id' => 6, 'name' => '罗斯福店', 'parentid' => 1],
        ['id' => 7, 'name' => '劳力士', 'parentid' => 6],
        ['id' => 8, 'name' => '时代广场', 'parentid' => 1],
    ];
}

function sampleUsers(): array
{
    return [
        4 => [[
            'userid' => 'zhao',
            'name' => '赵亚晶',
            'department' => [4],
            'main_department' => 4,
            'status' => 1,
            'position' => '销售',
            'mobile' => '',
            'email' => '',
        ]],
        7 => [[
            'userid' => 'jin',
            'name' => '金丹',
            'department' => [7],
            'main_department' => 7,
            'status' => 1,
        ]],
    ];
}

function bindWecom(array $departments, array $users, ?string $codeUserId = null, ?Throwable $failure = null): void
{
    test()->instance(WecomClient::class, new class($departments, $users, $codeUserId, $failure) implements WecomClient
    {
        public function __construct(
            private array $departments,
            private array $users,
            private ?string $codeUserId,
            private ?Throwable $failure,
        ) {}

        public function authorizationUrl(): string
        {
            return 'https://wecom.test/oauth';
        }

        public function userIdFromCode(string $code): string
        {
            return $this->codeUserId ?? $code;
        }

        public function departments(): array
        {
            if ($this->failure) {
                throw $this->failure;
            }

            return $this->departments;
        }

        public function users(int $departmentId): array
        {
            return $this->users[$departmentId] ?? [];
        }

        public function externalFollowUserIds(): array
        {
            return [];
        }

        public function externalContactIds(string $userId): array
        {
            return [];
        }

        public function externalContact(string $externalUserId): array
        {
            return [];
        }
    });
}

function sync(): SyncLog
{
    return app(SyncWecom::class)->run();
}
