<?php

namespace App\Services;
use App\Exceptions\InsufficientStockException;
use App\Models\InventoryMovement;
use App\Models\Product;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class InventoryService
{
    /**
     * Lista o estoque atual com alguns agregados.
     */
    public function getInventorySummary(array $filters = []): array
{
    $productId = $filters['product_id'] ?? null;
    $sku       = $filters['sku'] ?? null;

    $cacheKey = sprintf(
        'inventory:summary:%s:%s',
        $productId ?? 'all',
        $sku       ?? 'all'
    );

    return Cache::remember($cacheKey, now()->addSeconds(60), function () use ($productId, $sku) {
        $query = Product::query()
            ->select([
                'id',
                'sku',
                'name',
                'cost_price',
                'sale_price',
                'current_stock',
            ]);

        if ($productId !== null) {
            $query->whereKey($productId);
        }

        if ($sku !== null) {
            $query->where('sku', $sku);
        }

        $products = $query->get();

        $totalStockValue = 0;
        $totalPotentialProfit = 0;

        $items = $products->map(function (Product $product) use (&$totalStockValue, &$totalPotentialProfit) {
            $stockValue      = $product->current_stock * $product->sale_price;
            $potentialProfit = $product->current_stock * ($product->sale_price - $product->cost_price);

            $totalStockValue      += $stockValue;
            $totalPotentialProfit += $potentialProfit;

            return [
                'id'               => $product->id,
                'sku'              => $product->sku,
                'name'             => $product->name,
                'cost_price'       => (float) $product->cost_price,
                'sale_price'       => (float) $product->sale_price,
                'current_stock'    => $product->current_stock,
                'stock_value'      => $stockValue,
                'potential_profit' => $potentialProfit,
            ];
        });

        return [
            'items'                   => $items,
            'total_stock_value'       => $totalStockValue,
            'total_potential_profit'  => $totalPotentialProfit,
        ];
    });
}

    /**
     * Registra um movimento de estoque com segurança (transação + lock).
     */
    public function registerMovement(
        int $productId,
        string $type,
        int $quantity,
        ?float $unitCost,
        ?string $description = null,
    ): InventoryMovement {
        return DB::transaction(function () use ($productId, $type, $quantity, $unitCost, $description) {
            /** @var Product $product */
            $product = Product::whereKey($productId)
                ->lockForUpdate()
                ->firstOrFail();

            if ($type === 'out' && $product->current_stock < $quantity) {
                throw new InsufficientStockException('Estoque insuficiente para esta operação.');
            }

            $unitCostToUse = $unitCost ?? (float) $product->cost_price;

            $movement = InventoryMovement::create([
                'product_id' => $product->id,
                'type'       => $type,
                'quantity'   => $quantity,
                'unit_cost'  => $unitCostToUse,
                'description'=> $description,
            ]);

            if ($type === 'in') {
                $product->current_stock += $quantity;
            } else {
                $product->current_stock -= $quantity;
            }

            $product->save();

            Cache::forget('inventory:summary');

            return $movement;
        });
    }
}