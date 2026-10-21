<?php

namespace App\Console\Commands;

use App\Enums\UserStatus;
use App\Models\User;
use Illuminate\Console\Command;

class CreateAdmin extends Command
{
    protected $signature = 'app:create-admin {email} {password} {--name=管理员}';

    protected $description = '创建一个不来自企业微信的管理员，供密码入口使用';

    public function handle(): int
    {
        User::query()->updateOrCreate(
            ['email' => $this->argument('email')],
            [
                'name' => $this->option('name'),
                'password' => $this->argument('password'),
                'is_admin' => true,
                'status' => UserStatus::Active,
                'wecom_userid' => null,
            ],
        );

        $this->info('管理员已保存。密码入口：'.url('/admin/login'));

        return self::SUCCESS;
    }
}
