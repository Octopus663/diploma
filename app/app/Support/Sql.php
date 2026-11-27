<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;

/**
 * Невеликі SQL-вирази, що відрізняються між PostgreSQL (робоча БД) і SQLite (тести).
 */
final class Sql
{
    public static function month(string $column): string
    {
        return DB::getDriverName() === 'pgsql'
            ? "to_char({$column}, 'YYYY-MM')"
            : "strftime('%Y-%m', {$column})";
    }
}
