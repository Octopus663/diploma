<?php

namespace App\LeakDemo;

/**
 * АНТИПАТЕРН 1: статичний масив, що накопичує дані між запитами.
 *
 * У PHP-FPM статичні властивості живуть один запит. В Octane клас завантажений
 * один раз на воркер, тому масив росте з кожним запитом, доки воркер не перезапуститься.
 */
class StaticCollector
{
    /** @var array<int, array<string, mixed>> */
    public static array $events = [];

    /**
     * @param  array<string, mixed>  $event
     */
    public static function record(array $event): int
    {
        static::$events[] = $event;

        return count(static::$events);
    }
}
