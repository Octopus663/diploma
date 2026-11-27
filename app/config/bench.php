<?php

return [
    /*
    | Демонстрація витоків пам'яті і стану в Octane (етап 6).
    | Маршрути /api/leak/* реєструються лише коли BENCH_LEAK_DEMO=true.
    | НІКОЛИ не вмикайте на робочому середовищі.
    */
    'leak_demo' => (bool) env('BENCH_LEAK_DEMO', false),

    /*
    | Службовий маршрут /api/_debug/memory (PID і пам'ять воркера).
    | Вмикається BENCH_DEBUG=true або разом з leak_demo.
    */
    'debug' => (bool) env('BENCH_DEBUG', false) || (bool) env('BENCH_LEAK_DEMO', false),
];
