<?php

namespace App\Filament\Pages\Concerns;

use App\Models\Sale;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Lógica compartida de los reportes (ventas, caja, productos, ganancias).
 * Cada sección es una página hija que usa este trait.
 */
trait HasReportData
{
    public ?string $from = null;

    public ?string $to = null;

    public ?string $paymentMethod = null;

    public bool $includeAnnulled = false;

    public function mount(): void
    {
        $this->from = now()->subDays(30)->toDateString();
        $this->to = now()->toDateString();
    }

    private function baseQuery(): \Illuminate\Database\Eloquent\Builder
    {
        return Sale::query()
            ->where('restaurant_id', 1)
            ->when($this->from, fn ($q) => $q->whereDate('created_at', '>=', $this->from))
            ->when($this->to, fn ($q) => $q->whereDate('created_at', '<=', $this->to))
            ->when($this->paymentMethod, fn ($q) => $q->where('payment_method', $this->paymentMethod))
            ->when(! $this->includeAnnulled, fn ($q) => $q->where('status', '!=', 'annulled'));
    }

    public function getReportData(): array
    {
        $sales = $this->baseQuery()->get();

        $totalAmount = (float) $sales->where('status', '!=', 'annulled')->sum('total_amount');
        $count = $sales->where('status', '!=', 'annulled')->count();

        return [
            'total_amount' => $totalAmount,
            'count' => $count,
            'average' => $count > 0 ? $totalAmount / $count : 0,
            'by_day' => $sales
                ->groupBy(fn (Sale $sale) => $sale->created_at?->format('Y-m-d') ?? 'Sin fecha')
                ->map(fn ($group) => [
                    'count' => $group->where('status', '!=', 'annulled')->count(),
                    'total' => (float) $group->where('status', '!=', 'annulled')->sum('total_amount'),
                    'label' => Carbon::parse($group->first()->created_at)->format('d/m/Y'),
                ])
                ->sortKeys()
                ->values()
                ->all(),
            'by_method' => $sales
                ->where('status', '!=', 'annulled')
                ->groupBy(fn (Sale $sale) => $sale->payment_method ?? 'sin método')
                ->map(fn ($group) => [
                    'method' => $this->methodLabel($group->first()->payment_method),
                    'count' => $group->count(),
                    'total' => (float) $group->sum('total_amount'),
                ])
                ->values()
                ->all(),
            'top_products' => $this->topProducts($sales),
        ];
    }

    public function getCajaReport(): array
    {
        $cajas = \App\Models\Caja::query()
            ->where('restaurant_id', 1)
            ->when($this->from, fn ($q) => $q->whereDate('opening_date', '>=', $this->from))
            ->when($this->to, fn ($q) => $q->whereDate('opening_date', '<=', $this->to))
            ->withCount(['sales' => fn ($q) => $q->where('status', 'paid')])
            ->get();

        return $cajas->map(fn ($caja) => [
            'id' => $caja->id,
            'opened_at' => optional($caja->opening_date)->format('d/m/Y H:i'),
            'closed_at' => optional($caja->closing_date)->format('d/m/Y H:i'),
            'status' => $caja->status === 'abierta' ? 'Abierta' : 'Cerrada',
            'opening_amount' => (float) (optional($caja)->initial_balance ?? 0),
            'closing_amount' => (float) (optional($caja)->final_balance ?? 0),
            'sales_count' => (int) ($caja->sales_count ?? 0),
            'sales_total' => (float) $caja->computableSalesTotal(),
            'difference' => (float) ((optional($caja)->final_balance ?? 0) - (optional($caja)->initial_balance ?? 0)),
        ])->values()->all();
    }

    public function getProductReport(): array
    {
        $saleIds = Sale::query()
            ->where('restaurant_id', 1)
            ->when($this->from, fn ($q) => $q->whereDate('created_at', '>=', $this->from))
            ->when($this->to, fn ($q) => $q->whereDate('created_at', '<=', $this->to))
            ->where('status', '!=', 'annulled')
            ->pluck('id');

        if ($saleIds->isEmpty()) {
            return ['top' => [], 'bottom' => []];
        }

        $rows = DB::table('order_product as op')
            ->join('orders as o', 'o.id', '=', 'op.order_id')
            ->join('sales as s', 's.order_id', '=', 'o.id')
            ->join('products as p', 'p.id', '=', 'op.product_id')
            ->whereIn('s.id', $saleIds)
            ->groupBy('p.id', 'p.name')
            ->selectRaw('p.name, SUM(op.quantity) as total_quantity, SUM(op.quantity * op.price) as total_amount')
            ->get()
            ->map(fn ($row) => [
                'name' => $row->name,
                'quantity' => (int) $row->total_quantity,
                'total' => (float) $row->total_amount,
            ]);

        return [
            'top' => $rows->sortByDesc('quantity')->take(5)->values()->all(),
            'bottom' => $rows->sortBy('quantity')->take(5)->values()->all(),
        ];
    }

    public function getProfitReport(): array
    {
        $months = collect(range(5, 0))->map(function ($i) {
            $start = now()->startOfMonth()->subMonths($i);
            $end = $start->copy()->endOfMonth();

            return [
                'label' => $start->format('M Y'),
                'total' => $this->salesTotalBetween($start, $end),
            ];
        });

        $weeks = collect(range(7, 0))->map(function ($i) {
            $start = now()->startOfWeek()->subWeeks($i);
            $end = $start->copy()->endOfWeek();

            return [
                'label' => $start->format('d/m').' - '.$end->format('d/m'),
                'total' => $this->salesTotalBetween($start, $end),
            ];
        });

        return [
            'months' => $months->values()->all(),
            'weeks' => $weeks->values()->all(),
        ];
    }

    private function salesTotalBetween(Carbon $from, Carbon $to): float
    {
        return (float) Sale::query()
            ->where('restaurant_id', 1)
            ->whereBetween('created_at', [$from, $to])
            ->where('status', '!=', 'annulled')
            ->sum('total_amount');
    }

    private function topProducts(\Illuminate\Support\Collection $sales): array
    {
        $saleIds = $sales->where('status', '!=', 'annulled')->pluck('id');

        if ($saleIds->isEmpty()) {
            return [];
        }

        return DB::table('order_product as op')
            ->join('orders as o', 'o.id', '=', 'op.order_id')
            ->join('sales as s', 's.order_id', '=', 'o.id')
            ->join('products as p', 'p.id', '=', 'op.product_id')
            ->whereIn('s.id', $saleIds)
            ->groupBy('p.id', 'p.name')
            ->selectRaw('p.name, SUM(op.quantity) as total_quantity, SUM(op.quantity * op.price) as total_amount')
            ->orderByDesc('total_quantity')
            ->limit(10)
            ->get()
            ->map(fn ($row) => [
                'name' => $row->name,
                'quantity' => (int) $row->total_quantity,
                'total' => (float) $row->total_amount,
            ])
            ->all();
    }

    private function methodLabel(?string $method): string
    {
        return match ($method) {
            'cash' => 'Efectivo',
            'card' => 'Tarjeta',
            'transfer' => 'Transferencia',
            default => ucfirst((string) ($method ?? 'Sin método')),
        };
    }

    public array $paymentMethods = [
        'cash' => 'Efectivo',
        'card' => 'Tarjeta',
        'transfer' => 'Transferencia',
    ];

    public function exportCsv(): StreamedResponse
    {
        $sales = $this->baseQuery()
            ->with(['cashier:id,name'])
            ->orderByDesc('created_at')
            ->get();

        $callback = function () use ($sales): void {
            $handle = fopen('php://output', 'w');
            fwrite($handle, "\xEF\xBB\xBF");
            fputcsv($handle, ['ID', 'Fecha', 'Método', 'Estado', 'Total ($)', 'Cajero/a', 'Anulada']);

            foreach ($sales as $sale) {
                fputcsv($handle, [
                    $sale->id,
                    $sale->created_at?->format('d/m/Y H:i'),
                    $this->methodLabel($sale->payment_method),
                    $sale->status,
                    number_format((float) $sale->total_amount, 2, ',', '.'),
                    $sale->cashier?->name ?? 'N/A',
                    $sale->isAnnulled() ? 'Sí' : 'No',
                ]);
            }

            fclose($handle);
        };

        return response()->streamDownload($callback, 'reporte-ventas-'.now()->format('Ymd-His').'.csv');
    }
}