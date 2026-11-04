<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\CounterpartyRequest;
use App\Http\Resources\CounterpartyResource;
use App\Models\Counterparty;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;

class CounterpartyController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $query = Counterparty::query()->withCount('orders')->orderBy('id');

        if ($search = $request->string('q')->trim()->value()) {
            $query->where(fn ($q) => $q->whereLike('name', "%{$search}%")->orWhereLike('code', "{$search}%"));
        }

        if ($type = $request->query('type')) {
            $query->where('type', $type);
        }

        return CounterpartyResource::collection($query->paginate(min((int) $request->query('per_page', 20), 100)));
    }

    public function store(CounterpartyRequest $request): JsonResponse
    {
        $counterparty = Counterparty::query()->create($request->validated());

        return (new CounterpartyResource($counterparty))->response()->setStatusCode(201);
    }

    public function show(Counterparty $counterparty): CounterpartyResource
    {
        return new CounterpartyResource($counterparty->loadCount('orders'));
    }

    public function update(CounterpartyRequest $request, Counterparty $counterparty): CounterpartyResource
    {
        $counterparty->update($request->validated());

        return new CounterpartyResource($counterparty);
    }

    public function destroy(Counterparty $counterparty): JsonResponse
    {
        Gate::authorize('delete-records');
        $counterparty->delete();

        return response()->json(null, 204);
    }
}
