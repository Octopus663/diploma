<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use Illuminate\View\View;

/**
 * Сторінка «Про стенд»: показує, яким сервером обслуговується запит.
 * Лише відображення: логіка застосунку від режиму не залежить.
 */
class AboutController extends Controller
{
    public function __invoke(): View
    {
        $mode = env('SERVER_MODE', 'unknown');

        return view('about', [
            'server' => [
                'Режим (SERVER_MODE)' => $mode,
                'PHP SAPI' => PHP_SAPI,
                'PHP' => PHP_VERSION,
                'Laravel' => app()->version(),
                'PID процесу' => getmypid(),
                'Пам\'ять процесу зараз, МБ' => round(memory_get_usage(true) / 1048576, 1),
                'Пікова пам\'ять процесу, МБ' => round(memory_get_peak_usage(true) / 1048576, 1),
                'OPcache' => function_exists('opcache_get_status') && (opcache_get_status(false)['opcache_enabled'] ?? false) ? 'увімкнено' : 'вимкнено',
                'Розширення swoole' => extension_loaded('swoole') ? 'так' : 'ні',
            ],
            'persistent' => in_array($mode, ['roadrunner', 'swoole', 'frankenphp'], true),
        ]);
    }
}
