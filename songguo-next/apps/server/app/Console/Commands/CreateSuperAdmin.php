<?php

namespace App\Console\Commands;

use App\Models\SuperAdmin;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

class CreateSuperAdmin extends Command
{
    protected $signature = 'admin:create {username} {--name=} {--email=} {--password-env=SUPER_ADMIN_PASSWORD}';

    protected $description = '创建或更新平台超级管理员账号';

    public function handle(): int
    {
        $username = Str::lower(trim((string) $this->argument('username')));
        if (! preg_match('/^[a-z0-9._-]{3,80}$/', $username)) {
            $this->error('账号只能包含字母、数字、点、下划线和短横线，长度为 3-80。');

            return self::FAILURE;
        }

        $passwordEnv = (string) $this->option('password-env');
        $password = $passwordEnv !== '' ? env($passwordEnv) : null;
        $password ??= $this->secret('请输入至少 12 位的初始密码');
        if (! is_string($password) || strlen($password) < 12) {
            $this->error('密码长度不能少于 12 位。');

            return self::FAILURE;
        }

        $admin = SuperAdmin::query()->updateOrCreate(
            ['username' => $username],
            [
                'name' => (string) ($this->option('name') ?: $username),
                'email' => $this->option('email') ?: null,
                'password' => $password,
                'status' => 'active',
            ],
        );

        $admin->tokens()->delete();
        $this->info("超级管理员 {$admin->username} 已创建，旧会话已失效。");

        return self::SUCCESS;
    }
}
