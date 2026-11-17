<?php

namespace Database\Seeders;

use App\Models\Admin;
use Illuminate\Database\Seeder;

/**
 * Bootstrap superadmins. Passwords are set only when the account is created.
 * Running this seeder again does not reset an existing password.
 */
class AdminSeeder extends Seeder
{
    public function run(): void
    {
        $accounts = [
            ['username' => 'manuel', 'name' => 'Manuel', 'email' => 'manuel@wittygekko.local', 'password' => 'manuel@2025!'],
            ['username' => 'athor', 'name' => 'Athor', 'email' => 'athor@wittygekko.local', 'password' => 'athor@2022!'],
            ['username' => 'ruth', 'name' => 'Ruth', 'email' => 'ruth@wittygekko.local', 'password' => 'ruth@2025!'],
        ];

        foreach ($accounts as $account) {
            $admin = Admin::query()->firstOrNew(['username' => $account['username']]);
            $fresh = ! $admin->exists;
            $admin->name = $account['name'];
            $admin->email = $account['email'];
            $admin->access_scope = 'platform';
            $admin->admin_type = 0;
            $admin->status = 1;
            if ($fresh) {
                $admin->password = $account['password'];
                $admin->must_change_password = true;
            }
            $admin->save();
        }
    }
}
