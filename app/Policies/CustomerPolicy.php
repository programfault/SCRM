<?php

namespace App\Policies;

use App\Models\Customer;
use App\Models\User;

class CustomerPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->is_admin;
    }

    public function update(User $user, Customer $customer): bool
    {
        return $user->is_admin;
    }

    public function import(User $user): bool
    {
        return $user->is_admin;
    }

    public function reconcile(User $user): bool
    {
        return $user->is_admin;
    }
}
