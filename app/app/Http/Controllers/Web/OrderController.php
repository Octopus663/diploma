<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\Template;
use App\Services\DocumentService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class OrderController extends Controller
{
    public function index(Request $request): View
    {
        $query = Order::query()->with('counterparty')->withCount('items')->latest('ordered_at')->latest('id');

        if ($status = $request->query('status')) {
            $query->where('status', $status);
        }

        if ($search = $request->string('q')->trim()->value()) {
            $query->whereLike('number', "%{$search}%");
        }

        if ($from = $request->query('from')) {
            $query->where('ordered_at', '>=', $from);
        }

        if ($to = $request->query('to')) {
            $query->where('ordered_at', '<=', $to);
        }

        return view('orders.index', [
            'orders' => $query->paginate(25)->withQueryString(),
            'statuses' => Order::STATUSES,
        ]);
    }

    public function show(Order $order): View
    {
        return view('orders.show', [
            'order' => $order->load(['counterparty', 'items.product', 'documents.template']),
            'templates' => Template::query()->where('is_active', true)->orderBy('type')->orderBy('id')->get(),
        ]);
    }

    /**
     * Сформувати документ (звіт) з замовлення за обраним шаблоном.
     */
    public function generateDocument(Request $request, Order $order, DocumentService $documents): RedirectResponse
    {
        $data = $request->validate(['template_id' => ['required', 'integer', 'exists:templates,id']]);

        $document = $documents->generate(Template::query()->findOrFail($data['template_id']), $order, $request->user());

        return redirect()->route('documents.show', $document)->with('status', 'Документ сформовано.');
    }
}
