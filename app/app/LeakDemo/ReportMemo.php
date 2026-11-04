<?php

namespace App\LeakDemo;

/**
 * АНТИПАТЕРН 3: «кеш» у властивості сервісу-синглтона без обмеження розміру.
 *
 * Мемоізація в масиві виглядає безпечно, але в Octane синглтон живе весь час воркера,
 * і масив росте з кожним новим ключем.
 */
class ReportMemo
{
    /** @var array<string, string> */
    private array $memo = [];

    public function get(string $key): string
    {
        return $this->memo[$key] ??= str_repeat(md5($key), 256); // ~8 КБ на ключ
    }

    public function size(): int
    {
        return count($this->memo);
    }
}
