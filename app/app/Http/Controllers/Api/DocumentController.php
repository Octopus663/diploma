<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\DocumentRequest;
use App\Http\Resources\DocumentResource;
use App\Models\Document;
use App\Models\Order;
use App\Models\Template;
use App\Services\DocumentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;

class DocumentController extends Controller
{
    public function __construct(private readonly DocumentService $documents) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        $query = Document::query()->with(['template', 'counterparty'])->orderByDesc('id');

        foreach (['status', 'template_id', 'counterparty_id', 'order_id'] as $filter) {
            if ($value = $request->query($filter)) {
                $query->where($filter, $value);
            }
        }

        return DocumentResource::collection($query->paginate(min((int) $request->query('per_page', 20), 100)));
    }

    /**
     * Сформувати документ з замовлення за шаблоном.
     */
    public function store(DocumentRequest $request): JsonResponse
    {
        $data = $request->validated();

        $document = $this->documents->generate(
            Template::query()->findOrFail($data['template_id']),
            Order::query()->findOrFail($data['order_id']),
            $request->user(),
            $data['title'] ?? null,
        );

        return (new DocumentResource($document->load(['template', 'counterparty'])))->response()->setStatusCode(201);
    }

    public function show(Document $document): DocumentResource
    {
        return new DocumentResource($document->load(['template', 'counterparty']));
    }

    public function update(DocumentRequest $request, Document $document): DocumentResource
    {
        $data = $request->safe()->only(['title', 'status']);

        if (($data['status'] ?? null) === 'issued' && ! $document->issued_at) {
            $data['issued_at'] = now();
        }

        $document->update($data);

        return new DocumentResource($document);
    }

    public function destroy(Document $document): JsonResponse
    {
        Gate::authorize('delete-records');
        $document->delete();

        return response()->json(null, 204);
    }
}
