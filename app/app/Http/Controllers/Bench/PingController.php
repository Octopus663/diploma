<?php

namespace App\Http\Controllers\Bench;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;

/**
 * Сценарій 1: легкий JSON без БД і кешу.
 * Вимірює накладні витрати самого фреймворку на обробку запиту.
 * Відповідь не залежить від режиму сервера, тому однакова в усіх режимах.
 */
class PingController extends Controller
{
    public function __invoke(): JsonResponse
    {
        return response()->json([
            'status' => 'ok',
            'app' => config('app.name'),
            'laravel' => app()->version(),
            'php' => PHP_VERSION,
        ]);
    }
}
