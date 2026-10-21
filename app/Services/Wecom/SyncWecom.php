<?php

namespace App\Services\Wecom;

use App\Enums\DepartmentType;
use App\Enums\UserStatus;
use App\Models\Department;
use App\Models\SyncLog;
use App\Models\User;
use EasyWeChat\Kernel\Exceptions\HttpException;
use Illuminate\Support\Facades\DB;

class SyncWecom
{
    public function __construct(private readonly WecomClient $wecom) {}

    public function run(?int $triggeredBy = null): SyncLog
    {
        $log = SyncLog::query()->create([
            'triggered_by' => $triggeredBy,
            'status' => 'running',
            'started_at' => now(),
        ]);

        try {
            $counts = DB::transaction(fn (): array => $this->apply(
                $this->wecom->departments(),
                fn (int $departmentId): array => $this->wecom->users($departmentId),
            ));

            $log->update([
                ...$counts,
                'status' => 'succeeded',
                'finished_at' => now(),
            ]);
        } catch (WecomNotConfigured|WecomRequestFailed|HttpException $exception) {
            $log->update([
                'status' => 'failed',
                'message' => $exception->getMessage(),
                'finished_at' => now(),
            ]);
        }

        return $log->refresh();
    }

    /**
     * @param  array<int, array{id: int, name: string, parentid: int}>  $departments
     * @param  callable(int): array<int, array<string, mixed>>  $usersFor
     * @return array{departments_created: int, departments_updated: int, users_created: int, users_updated: int, users_left: int, message: ?string}
     */
    public function apply(array $departments, callable $usersFor): array
    {
        $kept = $this->withoutIgnored($departments);
        $classified = $this->classify($kept);

        $departmentCounts = ['created' => 0, 'updated' => 0];
        $notes = [];
        $seenDepartmentIds = [];

        foreach ($classified as $row) {
            $seenDepartmentIds[] = $row['id'];
            $existing = Department::query()->where('wecom_department_id', $row['id'])->first();
            $record = Department::query()->updateOrCreate(
                ['wecom_department_id' => $row['id']],
                [
                    'name' => $row['name'],
                    'parent_wecom_id' => $row['parentid'] ?: null,
                    'type' => $row['type'],
                    'missing_at' => null,
                ],
            );

            $existing === null ? $departmentCounts['created']++ : $departmentCounts['updated']++;
            $classified[$row['id']]['model_id'] = $record->id;
        }

        foreach ($classified as $id => $row) {
            $parentId = $classified[$row['parentid']]['model_id'] ?? null;
            Department::query()->where('wecom_department_id', $id)->update([
                'parent_id' => $parentId,
            ]);
            $classified[$id]['parent_model_id'] = $parentId;
        }

        Department::query()
            ->whereNotIn('wecom_department_id', $seenDepartmentIds ?: [0])
            ->whereNull('missing_at')
            ->update(['missing_at' => now()]);

        $users = $this->collectUsers($kept, $usersFor);
        $userCounts = ['created' => 0, 'updated' => 0, 'left' => 0];

        foreach ($users as $payload) {
            $result = $this->saveUser($payload, $classified, $notes);
            $userCounts[$result]++;
        }

        return [
            'departments_created' => $departmentCounts['created'],
            'departments_updated' => $departmentCounts['updated'],
            'users_created' => $userCounts['created'],
            'users_updated' => $userCounts['updated'],
            'users_left' => $userCounts['left'],
            'message' => $notes === [] ? null : implode("\n", array_unique($notes)),
        ];
    }

    /**
     * @param  array<int, array{id: int, name: string, parentid: int}>  $departments
     * @return array<int, array{id: int, name: string, parentid: int}>
     */
    private function withoutIgnored(array $departments): array
    {
        $ignored = [];

        do {
            $changed = false;

            foreach ($departments as $department) {
                $id = (int) $department['id'];

                if (isset($ignored[$id])) {
                    continue;
                }

                $name = str_replace(['(', ')'], ['（', '）'], (string) $department['name']);
                $parentId = (int) $department['parentid'];

                if ($name === '其他（待设置部门）' || isset($ignored[$parentId])) {
                    $ignored[$id] = true;
                    $changed = true;
                }
            }
        } while ($changed);

        $kept = [];

        foreach ($departments as $department) {
            $id = (int) $department['id'];

            if (isset($ignored[$id])) {
                continue;
            }

            $kept[$id] = [
                'id' => $id,
                'name' => (string) $department['name'],
                'parentid' => (int) $department['parentid'],
            ];
        }

        return $kept;
    }

    /**
     * @param  array<int, array{id: int, name: string, parentid: int}>  $departments
     * @return array<int, array{id: int, name: string, parentid: int, type: DepartmentType}>
     */
    private function classify(array $departments): array
    {
        $classified = [];

        foreach ($departments as $id => $department) {
            $type = DepartmentType::Counter;
            $parentId = $department['parentid'];

            if (! isset($departments[$parentId])) {
                $type = DepartmentType::Company;
            } elseif (! isset($departments[$departments[$parentId]['parentid']])) {
                $type = DepartmentType::Store;
            }

            $classified[$id] = [
                ...$department,
                'type' => $type,
            ];
        }

        return $classified;
    }

    /**
     * @param  array<int, array{id: int, name: string, parentid: int}>  $departments
     * @param  callable(int): array<int, array<string, mixed>>  $usersFor
     * @return array<string, array<string, mixed>>
     */
    private function collectUsers(array $departments, callable $usersFor): array
    {
        $users = [];

        foreach (array_keys($departments) as $departmentId) {
            foreach ($usersFor($departmentId) as $user) {
                $userId = (string) ($user['userid'] ?? '');

                if ($userId === '') {
                    continue;
                }

                if (! isset($users[$userId])) {
                    $users[$userId] = $user;
                    $users[$userId]['department'] = array_map('intval', $user['department'] ?? []);

                    continue;
                }

                $users[$userId]['department'] = array_values(array_unique([
                    ...$users[$userId]['department'],
                    ...array_map('intval', $user['department'] ?? []),
                ]));

                if (empty($users[$userId]['main_department']) && ! empty($user['main_department'])) {
                    $users[$userId]['main_department'] = $user['main_department'];
                }
            }
        }

        return $users;
    }

    /**
     * @param  array<string, mixed>  $payload
     * @param  array<int, array{id: int, name: string, parentid: int, type: DepartmentType, model_id?: int, parent_model_id?: int}>  $classified
     * @param  array<int, string>  $notes
     */
    private function saveUser(array $payload, array $classified, array &$notes): string
    {
        $userId = (string) $payload['userid'];
        $departmentIds = array_values(array_filter(
            array_map('intval', $payload['department'] ?? []),
            fn (int $id): bool => isset($classified[$id]),
        ));
        $mainId = (int) ($payload['main_department'] ?? 0);

        if (! isset($classified[$mainId])) {
            $mainId = $departmentIds[0] ?? 0;

            if ($mainId !== 0 && count($departmentIds) > 1) {
                $notes[] = "{$payload['name']} 没有主部门，已使用第一个专柜。";
            }
        }

        $current = $classified[$mainId] ?? null;
        $storeId = null;
        $counterId = null;

        if ($current !== null && $current['type'] === DepartmentType::Counter) {
            $counterId = $current['model_id'];
            $storeId = $this->storeId($current, $classified);
        } elseif ($current !== null && $current['type'] === DepartmentType::Store) {
            $storeId = $current['model_id'];
            $notes[] = "{$payload['name']} 直接挂在门店下，没有专柜。";
        }

        $status = UserStatus::fromWecom((int) ($payload['status'] ?? 1));
        $existing = User::query()->where('wecom_userid', $userId)->first();
        $wasLeft = $existing?->status === UserStatus::Left;

        User::query()->updateOrCreate(
            ['wecom_userid' => $userId],
            [
                'name' => (string) ($payload['name'] ?: $userId),
                'email' => filled($payload['email'] ?? null) ? $payload['email'] : null,
                'mobile' => filled($payload['mobile'] ?? null) ? $payload['mobile'] : null,
                'position' => filled($payload['position'] ?? null) ? $payload['position'] : null,
                'avatar' => filled($payload['avatar'] ?? null) ? $payload['avatar'] : null,
                'status' => $status,
                'left_at' => $status === UserStatus::Left ? ($existing?->left_at ?? now()) : null,
                'store_id' => $storeId,
                'counter_id' => $counterId,
            ],
        );

        if ($existing === null) {
            return 'created';
        }

        if ($status === UserStatus::Left && ! $wasLeft) {
            return 'left';
        }

        return 'updated';
    }

    /**
     * @param  array{id: int, parentid: int, type: DepartmentType, model_id?: int, parent_model_id?: int}  $current
     * @param  array<int, array{id: int, parentid: int, type: DepartmentType, model_id?: int, parent_model_id?: int}>  $classified
     */
    private function storeId(array $current, array $classified): ?int
    {
        $parent = $classified[$current['parentid']] ?? null;

        while ($parent !== null && $parent['type'] !== DepartmentType::Store) {
            $parent = $classified[$parent['parentid']] ?? null;
        }

        return $parent['model_id'] ?? null;
    }
}
