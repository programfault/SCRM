<?php

namespace App\Services\Customers;

use App\Models\Customer;
use App\Services\Wecom\WecomClient;

class ReconcileWecomCustomers
{
    public function __construct(private readonly WecomClient $wecom) {}

    /**
     * @return array{linked: int, conflicts: int}
     */
    public function reconcile(): array
    {
        $linked = 0;
        $conflicts = 0;

        foreach ($this->wecom->externalFollowUserIds() as $userId) {
            foreach ($this->wecom->externalContactIds($userId) as $externalUserId) {
                $phones = $this->remarkPhones($this->wecom->externalContact($externalUserId), $userId);

                foreach ($phones as $phone) {
                    $customer = Customer::query()->with('owner')->where('phone', $phone)->first();

                    if ($customer === null) {
                        continue;
                    }

                    if ($customer->owner?->wecom_userid === $userId) {
                        $wasLinked = $customer->wecom_external_userid !== null;
                        $customer->wecom_external_userid = $externalUserId;
                        $customer->wecom_added_at ??= now();
                        $customer->conflict_wecom_userid = null;
                        $customer->conflict_external_userid = null;
                        $customer->save();

                        if (! $wasLinked) {
                            $linked++;
                        }

                        continue;
                    }

                    if ($customer->wecom_external_userid !== null) {
                        continue;
                    }

                    $alreadyNoted = $customer->conflict_wecom_userid === $userId
                        && $customer->conflict_external_userid === $externalUserId;
                    $customer->conflict_wecom_userid = $userId;
                    $customer->conflict_external_userid = $externalUserId;
                    $customer->save();

                    if (! $alreadyNoted) {
                        $conflicts++;
                    }
                }
            }
        }

        return compact('linked', 'conflicts');
    }

    /**
     * @param  array<string, mixed>  $detail
     * @return array<int, string>
     */
    private function remarkPhones(array $detail, string $userId): array
    {
        foreach ($detail['follow_user'] ?? [] as $follow) {
            if (! is_array($follow) || ($follow['userid'] ?? null) !== $userId) {
                continue;
            }

            $phones = [];

            foreach ($follow['remark_mobiles'] ?? [] as $mobile) {
                $phone = PhoneNumbers::normalize((string) $mobile);

                if ($phone !== null) {
                    $phones[$phone] = $phone;
                }
            }

            return array_values($phones);
        }

        return [];
    }
}
