<?php

namespace App\Services\Customers;

use App\Enums\UserStatus;
use App\Models\Customer;
use App\Models\User;

class AssignCustomerOwner
{
    public function assign(Customer $customer, User $owner): void
    {
        if ($customer->wecom_external_userid !== null) {
            throw new CustomerAssignmentFailed('已经加入企业微信的客户不能改负责人。');
        }

        if ($owner->status !== UserStatus::Active || $owner->wecom_userid === null) {
            throw new CustomerAssignmentFailed('只能分给在职且已同步的员工。');
        }

        $customer->owner()->associate($owner);
        $customer->assignment_note = null;
        $customer->save();
    }
}
