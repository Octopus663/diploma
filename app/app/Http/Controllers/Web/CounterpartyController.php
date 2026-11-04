<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Counterparty;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CounterpartyController extends Controller
{
    public function index(Request $request): View
    {
        $query = Counterparty::query()->withCount('orders')->orderBy('name');

        if ($search = $request->string('q')->trim()->value()) {
            $query->where(fn ($q) => $q->whereLike('name', "%{$search}%")->orWhereLike('code', "{$search}%"));
        }

        if ($type = $request->query('type')) {
            $query->where('type', $type);
        }

        return view('counterparties.index', [
            'counterparties' => $query->paginate(25)->withQueryString(),
        ]);
    }

    public function show(Counterparty $counterparty): View
    {
        return view('counterparties.show', [
            'counterparty' => $counterparty->loadCount(['orders', 'documents']),
            'orders' => $counterparty->orders()->withCount('items')->latest('ordered_at')->paginate(15),
            'documents' => $counterparty->documents()->with('template')->latest('id')->limit(10)->get(),
            'turnover' => (float) $counterparty->orders()->where('status', '!=', 'cancelled')->sum('total'),
        ]);
    }
}
