<?php

namespace App\Services;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class ReportsService
{
    public function getSalesReport(array $filters): array
    {
        $startDate = Carbon::parse($filters['start_date'])->startOfDay();
        $endDate   = Carbon::parse($filters['end_date'])->endOfDay();
        $sku       = $filters['sku'] ?? null;

        // chave de cache específica por combinação de filtros
        $cacheKey = $this->buildCacheKey($startDate, $endDate, $sku);

        return Cache::remember($cacheKey, now()->addSeconds(120), function () use ($startDate, $endDate, $sku) {
            // Query base com join: sales + sale_items + products
            $query = DB::table('sales')
                ->join('sale_items', 'sales.id', '=', 'sale_items.sale_id')
                ->join('products', 'sale_items.product_id', '=', 'products.id')
                ->whereBetween('sales.created_at', [$startDate, $endDate])
                ->where('sales.status', 'processed');

            if ($sku) {
                $query->where('products.sku', $sku);
            }

            // Agregados globais
            $totals = (clone $query)
                ->selectRaw('
                    COALESCE(SUM(sale_items.total_line), 0) as total_sales,
                    COALESCE(SUM(sale_items.total_line - sale_items.unit_cost * sale_items.quantity), 0) as total_profit,
                    COALESCE(SUM(sale_items.quantity), 0) as total_quantity
                ')
                ->first();

            // Opcional: breakdown por produto (útil pra análise)
            $byProduct = (clone $query)
                ->selectRaw('
                    products.id,
                    products.sku,
                    products.name,
                    SUM(sale_items.quantity) as quantity_sold,
                    SUM(sale_items.total_line) as sales_value,
                    SUM(sale_items.total_line - sale_items.unit_cost * sale_items.quantity) as profit_value
                ')
                ->groupBy('products.id', 'products.sku', 'products.name')
                ->orderByDesc('sales_value')
                ->get();

            return [
                'filters' => [
                    'start_date' => $startDate->toDateString(),
                    'end_date'   => $endDate->toDateString(),
                    'sku'        => $sku,
                ],
                'totals' => [
                    'total_sales'    => (float) $totals->total_sales,
                    'total_profit'   => (float) $totals->total_profit,
                    'total_quantity' => (int) $totals->total_quantity,
                ],
                'by_product' => $byProduct,
            ];
        });
    }

    private function buildCacheKey(Carbon $startDate, Carbon $endDate, ?string $sku): string
    {
        return sprintf(
            'reports:sales:%s:%s:%s',
            $startDate->format('Y-m-d'),
            $endDate->format('Y-m-d'),
            $sku ?? 'all'
        );
    }
}