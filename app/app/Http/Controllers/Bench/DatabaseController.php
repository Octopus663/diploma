<?php

namespace App\Http\Controllers\Bench;

use App\Http\Controllers\Controller;
use App\Services\Bench\ReportQuery;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Сценарій 2: робота з реляційною БД через Eloquent ORM.
 */
class DatabaseController extends Controller
{
    public function __construct(private readonly ReportQuery $query) {}

    public function orderReport(int $order): JsonResponse
    {
        return response()->json($this->query->orderReport($order));
    }

    public function counterpartyOrders(Request $request, int $counterparty): JsonResponse
    {
        $limit = max(1, min((int) $request->query('limit', 20), 100));

        return response()->json($this->query->counterpartyOrders($counterparty, $limit));
    }
}
