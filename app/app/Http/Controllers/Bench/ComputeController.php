<?php

namespace App\Http\Controllers\Bench;

use App\Http\Controllers\Controller;
use App\Services\Bench\ReportCompute;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Сценарій 4: обчислення і серіалізація великого масиву даних.
 */
class ComputeController extends Controller
{
    public const MAX_ROWS = 50000;

    public function __construct(private readonly ReportCompute $compute) {}

    public function __invoke(Request $request): JsonResponse
    {
        $data = $request->validate([
            'rows' => ['sometimes', 'integer', 'min:1', 'max:'.self::MAX_ROWS],
            'seed' => ['sometimes', 'integer', 'min:0'],
            'include_rows' => ['sometimes', 'boolean'],
        ]);

        return response()->json($this->compute->run(
            (int) ($data['rows'] ?? 10000),
            (int) ($data['seed'] ?? 42),
            (bool) ($data['include_rows'] ?? false),
        ), 200, [], JSON_UNESCAPED_UNICODE);
    }
}
