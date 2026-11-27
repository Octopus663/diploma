<?php

namespace App\LeakDemo;

/**
 * ВИПРАВЛЕННЯ 3: мемоізація з обмеженням розміру (найстаріші ключі витісняються).
 * Для даних, що мають жити довше за запит, краще використовувати Cache (Redis).
 */
class BoundedReportMemo
{
    public const LIMIT = 100;

    /** @var array<string, string> */
    private array $memo = [];

    public function get(string $key): string
    {
        if (isset($this->memo[$key])) {
            return $this->memo[$key];
        }

        if (count($this->memo) >= self::LIMIT) {
            array_shift($this->memo);
        }

        return $this->memo[$key] = str_repeat(md5($key), 256);
    }

    public function size(): int
    {
        return count($this->memo);
    }
}
