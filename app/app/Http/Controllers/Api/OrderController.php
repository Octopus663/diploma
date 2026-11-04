<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\OrderRequest;
use App\Http\Resources\OrderResource;
use App\Models\Order;
use App\Services\OrderService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;

class OrderController extends Controller
{
    public function __construct(private readonly OrderService $orders) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        $query = Order::query()->with('counterparty')->withCount('items')->orderByDesc('ordered_at')->orderByDesc('id');

        foreach (['status', 'counterparty_id'] as $filter) {
            if ($value = $request->query($filter)) {
                $query->where($filter, $value);
            }
        }

        if ($from = $request->query('from')) {
            $query->where('ordered_at', '>=', $from);
        }

        if ($to = $request->query('to')) {
            $query->where('ordered_at', '<=', $to);
        }

        return OrderResource::collection($query->paginate(min((int) $request->query('per_page', 20), 100)));
    }

    public function store(OrderRequest $request): JsonResponse
    {
        $order = $this->orders->create($request->validated(), $request->user());

        return (new OrderResource($order->load(['counterparty', 'items.product'])))->response()->setStatusCode(201);
    }

    public function show(Order $order): OrderResource
    {
        return new OrderResource($order->load(['counterparty', 'items.product', 'documents']));
    }

    public function update(OrderRequest $request, Order $order): OrderResource
    {
        $order = $this->orders->update($order, $request->validated());

        return new OrderResource($order->load(['counterparty', 'items.product']));
    }

    public function destroy(Order $order): JsonResponse
    {
        Gate::authorize('delete-records');
        $order->delete();

        return response()->json(null, 204);
    }
}
