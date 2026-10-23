<?php

namespace App\Services\Customers;

use App\Enums\UserStatus;
use App\Models\Customer;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class ImportLegacyCustomers
{
    /**
     * @return array{created: int, updated: int, assigned: int, unassigned: int, skipped: int}
     */
    public function import(string $csv): array
    {
        $rows = $this->rows($csv);
        $index = $this->ownerIndex();
        $created = 0;
        $updated = 0;
        $assigned = 0;
        $unassigned = 0;
        $skipped = 0;

        DB::transaction(function () use ($rows, $index, &$created, &$updated, &$assigned, &$unassigned, &$skipped): void {
            foreach ($rows as $row) {
                $phone = PhoneNumbers::normalize($row['phone']);

                if ($phone === null || $row['name'] === '') {
                    $skipped++;

                    continue;
                }

                $customer = Customer::query()->where('phone', $phone)->first() ?? new Customer(['phone' => $phone]);
                $existed = $customer->exists;
                $customer->name = $row['name'];

                if ($customer->wecom_external_userid === null) {
                    $customer->imported_owner_name = $row['owner'];
                    $customer->imported_department_name = $row['department'];
                    $this->match($customer, $row['owner'], $row['department'], $index);
                }

                $customer->save();

                $existed ? $updated++ : $created++;
                $customer->owner_id === null ? $unassigned++ : $assigned++;
            }
        });

        if ($created + $updated + $skipped === 0) {
            throw new CustomerImportFailed('文件里没有客户数据。');
        }

        return compact('created', 'updated', 'assigned', 'unassigned', 'skipped');
    }

    /**
     * @return array<int, array{name: string, phone: string, owner: string, department: string}>
     */
    private function rows(string $csv): array
    {
        $csv = $this->utf8(trim($csv));

        if ($csv === '') {
            throw new CustomerImportFailed('文件是空的。');
        }

        $firstLine = preg_split("/\r\n|\n|\r/", $csv, 2)[0] ?: '';
        $delimiter = str_contains($firstLine, "\t")
            ? "\t"
            : (substr_count($firstLine, ';') > substr_count($firstLine, ',') ? ';' : ',');

        $handle = fopen('php://temp', 'r+');
        fwrite($handle, $csv);
        rewind($handle);

        $header = fgetcsv($handle, 0, $delimiter);
        $indexes = $this->indexes(is_array($header) ? $header : []);
        $rows = [];

        while (($line = fgetcsv($handle, 0, $delimiter)) !== false) {
            if ($this->blank($line)) {
                continue;
            }

            $rows[] = [
                'name' => trim((string) ($line[$indexes['name']] ?? '')),
                'phone' => trim((string) ($line[$indexes['phone']] ?? '')),
                'owner' => trim((string) ($line[$indexes['owner']] ?? '')),
                'department' => trim((string) ($line[$indexes['department']] ?? '')),
            ];
        }

        fclose($handle);

        return $rows;
    }

    /**
     * @param  array<int, string|null>  $header
     * @return array{name: int, phone: int, owner: int, department: int}
     */
    private function indexes(array $header): array
    {
        $aliases = [
            'name' => ['姓名', '客户姓名', 'name'],
            'phone' => ['手机号', '手机', '电话', 'phone'],
            'owner' => ['负责人', '以前的负责人', 'owner'],
            'department' => ['部门', '门店', '专柜', 'department'],
        ];
        $normalized = array_map(fn (mixed $cell): string => $this->label((string) $cell), $header);
        $indexes = [];

        foreach ($aliases as $field => $names) {
            foreach ($names as $name) {
                $position = array_search($this->label($name), $normalized, true);

                if ($position !== false) {
                    $indexes[$field] = $position;
                    break;
                }
            }
        }

        foreach (['name', 'phone', 'owner', 'department'] as $field) {
            if (! isset($indexes[$field])) {
                throw new CustomerImportFailed('表头需要包含姓名、手机号、负责人、部门。');
            }
        }

        return $indexes;
    }

    /**
     * @return array<string, array<int, int>>
     */
    private function ownerIndex(): array
    {
        $users = User::query()
            ->where('status', UserStatus::Active)
            ->whereNotNull('wecom_userid')
            ->with(['store:id,name', 'counter:id,name'])
            ->get();
        $index = [];

        foreach ($users as $user) {
            foreach (array_filter([$user->store?->name, $user->counter?->name]) as $department) {
                $index[$this->key($user->name, $department)][$user->id] = $user->id;
            }
        }

        return $index;
    }

    /**
     * @param  array<string, array<int, int>>  $index
     */
    private function match(Customer $customer, string $ownerName, string $department, array $index): void
    {
        if ($ownerName === '' || $department === '') {
            $customer->owner_id = null;
            $customer->assignment_note = $ownerName === '' ? '缺少负责人' : '缺少部门';

            return;
        }

        $ids = array_values($index[$this->key($ownerName, $department)] ?? []);

        if (count($ids) === 1) {
            $customer->owner_id = $ids[0];
            $customer->assignment_note = null;

            return;
        }

        $customer->owner_id = null;
        $customer->assignment_note = $ids === [] ? '未匹配' : '重名';
    }

    private function key(string $name, string $department): string
    {
        return $this->label($name)."\n".$this->label($department);
    }

    private function label(string $value): string
    {
        $value = str_replace(['(', ')'], ['（', '）'], trim($value));

        return preg_replace('/\s+/u', '', $value) ?? $value;
    }

    private function utf8(string $csv): string
    {
        $csv = preg_replace('/^\xEF\xBB\xBF/', '', $csv) ?? $csv;

        if (! mb_check_encoding($csv, 'UTF-8')) {
            $converted = mb_convert_encoding($csv, 'UTF-8', 'GB18030');

            if (is_string($converted)) {
                return $converted;
            }
        }

        return $csv;
    }

    /**
     * @param  array<int, string|null>  $line
     */
    private function blank(array $line): bool
    {
        foreach ($line as $cell) {
            if (trim((string) $cell) !== '') {
                return false;
            }
        }

        return true;
    }
}
