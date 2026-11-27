<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Counterparty;
use App\Models\Document;
use App\Models\Order;
use App\Support\Sql;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __invoke(): View
    {
        $byStatus = Order::query()
            ->select('status', DB::raw('count(*) as cnt'), DB::raw('sum(total) as amount'))
            ->groupBy('status')
            ->orderBy('status')
            ->get();

        $month = Sql::month('ordered_at');
        $monthly = Order::query()
            ->where('status', '!=', 'cancelled')
            ->where('ordered_at', '>=', now()->subMonths(24)->startOfMonth())
            ->selectRaw("{$month} as month, sum(total) as amount, count(*) as cnt")
            ->groupByRaw($month)
            ->orderByRaw($month)
            ->get();

        return view('dashboard', [
            'stats' => [
                'counterparties' => Counterparty::query()->count(),
                'orders' => $byStatus->sum('cnt'),
                'documents' => Document::query()->count(),
                'revenue' => (float) $byStatus->where('status', '!=', 'cancelled')->sum('amount'),
            ],
            'byStatus' => $byStatus,
            'monthlyChart' => [
                'type' => 'bar',
                'data' => [
                    'labels' => $monthly->pluck('month'),
                    'datasets' => [[
                        'label' => 'Оборот, грн',
                        'data' => $monthly->map(fn ($m) => round((float) $m->amount, 2)),
                        'backgroundColor' => '#2563eb',
                    ]],
                ],
                'options' => ['plugins' => ['legend' => ['display' => false]]],
            ],
            'latest' => Order::query()->with('counterparty')->latest('id')->limit(10)->get(),
        ]);
    }
}
