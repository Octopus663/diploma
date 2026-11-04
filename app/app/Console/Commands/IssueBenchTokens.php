<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;

/**
 * Видати API-токени тестовим користувачам bench01..bench20 для k6.
 * Токени видаються один раз перед серією прогонів, щоб не впиратися в обмеження частоти входу.
 */
class IssueBenchTokens extends Command
{
    protected $signature = 'bench:tokens {--output= : Файл для JSON (типово stdout)}';

    protected $description = 'Видати токени Sanctum користувачам для навантажувального тестування';

    public function handle(): int
    {
        $tokens = [];

        User::query()->where('email', 'like', 'bench%@diploma.test')->orderBy('email')->each(function (User $user) use (&$tokens) {
            $user->tokens()->where('name', 'k6')->delete();
            $tokens[] = $user->createToken('k6')->plainTextToken;
        });

        if ($tokens === []) {
            $this->error('Немає користувачів bench*. Запустіть сидер.');

            return self::FAILURE;
        }

        $json = json_encode(['tokens' => $tokens], JSON_PRETTY_PRINT);

        if ($path = $this->option('output')) {
            file_put_contents($path, $json);
            $this->info('Токенів: '.count($tokens).' -> '.$path);
        } else {
            $this->line($json);
        }

        return self::SUCCESS;
    }
}
