<?php

namespace App\Filament\Pages;

use App\Models\Sale;
use Filament\Pages\Page;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\StreamedResponse;

class Reports extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-chart-bar-square';

    protected static string $view = 'filament.pages.reports';

    protected static ?string $title = 'Reportes';

    protected static ?string $navigationLabel = 'Reportes';

    protected static ?string $navigationGroup = 'Reportes';

    protected static ?int $navigationSort = 1;

    public static function canAccess(): bool
    {
        // Reportes es una página administrativa: solo super_admin.
        $user = auth()->user();

        return $user !== null && $user->hasRole('super_admin');
    }

    // ─────────────────────────────────────────────────────────────
    // Filtros del reporte
    // ─────────────────────────────────────────────────────────────

    public ?string $from = null;

    public ?string $to = null;

    public ?string $paymentMethod = null;

    public bool $includeAnnulled = false;

    public function mount(): void
    {
        // Default: últimos 30 días.
        $this->from = now()->subDays(30)->toDateString();
        $this->to = now()->toDateString();
    }

    // ─────────────────────────────────────────────────────────────
    // Datos del reporte (recalculados con cada cambio de filtro)
    // ─────────────────────────────────────────────────────────────

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

    // ─────────────────────────────────────────────────────────────
    // Exportación CSV (nativa, sin dependencias externas)
    // ─────────────────────────────────────────────────────────────

    public function exportCsv(): StreamedResponse
    {
        $sales = $this->baseQuery()
            ->with(['cashier:id,name'])
            ->orderByDesc('created_at')
            ->get();

        $callback = function () use ($sales): void {
            $handle = fopen('php://output', 'w');

            // BOM para Excel (UTF-8 con acentos correctos).
            fwrite($handle, "\xEF\xBB\xBF");

            fputcsv($handle, [
                'ID', 'Fecha', 'Método', 'Estado', 'Total ($)', 'Cajero/a', 'Anulada',
            ]);

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