<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Document;
use App\Models\Template;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class DocumentController extends Controller
{
    public function index(Request $request): View
    {
        $query = Document::query()->with(['template', 'counterparty'])->latest('id');

        foreach (['status', 'template_id'] as $filter) {
            if ($value = $request->query($filter)) {
                $query->where($filter, $value);
            }
        }

        return view('documents.index', [
            'documents' => $query->paginate(25)->withQueryString(),
            'statuses' => Document::STATUSES,
        ]);
    }

    public function show(Document $document): View
    {
        return view('documents.show', ['document' => $document->load(['template', 'counterparty', 'order', 'user'])]);
    }

    public function exportJson(Document $document): JsonResponse
    {
        return response()->json($document->payload, 200, [
            'Content-Disposition' => "attachment; filename=\"{$document->number}.json\"",
        ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    }

    /**
     * CSV з рядками документа (UTF-8 з BOM, щоб коректно відкривався в Excel).
     */
    public function exportCsv(Document $document): StreamedResponse
    {
        $payload = $document->payload;

        return response()->streamDownload(function () use ($payload) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, ['sku', 'name', 'unit', 'quantity', 'price', 'total'], ';');

            foreach ($payload['lines'] ?? [] as $line) {
                fputcsv($out, [$line['sku'] ?? '', $line['name'] ?? '', $line['unit'] ?? '', $line['quantity'] ?? '', $line['price'] ?? '', $line['total'] ?? ''], ';');
            }

            fputcsv($out, ['', 'Разом', '', '', '', $payload['total'] ?? ''], ';');
            fputcsv($out, ['', 'ПДВ', '', '', '', $payload['vat'] ?? ''], ';');
            fclose($out);
        }, "{$document->number}.csv", ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    public function templates(): View
    {
        return view('templates.index', [
            'templates' => Template::query()->withCount('documents')->orderBy('type')->orderBy('id')->paginate(50),
        ]);
    }
}
