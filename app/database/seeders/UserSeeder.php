<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

/**
 * Демонстраційні користувачі та користувачі для навантажувального тестування.
 * Пароль усіх користувачів: password.
 */
class UserSeeder extends Seeder
{
    public const BENCH_USERS = 20;

    public function run(): void
    {
        $password = Hash::make('password');

        User::query()->updateOrCreate(
            ['email' => 'admin@diploma.test'],
            ['name' => 'Адміністратор', 'role' => User::ROLE_ADMIN, 'password' => $password, 'email_verified_at' => now()],
        );

        User::query()->updateOrCreate(
            ['email' => 'manager@diploma.test'],
            ['name' => 'Менеджер', 'role' => User::ROLE_MANAGER, 'password' => $password, 'email_verified_at' => now()],
        );

        for ($i = 1; $i <= self::BENCH_USERS; $i++) {
            $n = str_pad((string) $i, 2, '0', STR_PAD_LEFT);
            User::query()->updateOrCreate(
                ['email' => "bench{$n}@diploma.test"],
                ['name' => "Bench {$n}", 'role' => User::ROLE_MANAGER, 'password' => $password, 'email_verified_at' => now()],
            );
        }
    }
}
