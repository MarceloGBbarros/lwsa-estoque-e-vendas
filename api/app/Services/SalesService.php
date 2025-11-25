<?php

namespace App\Services;

use App\Jobs\ProcessSaleJob;
use App\Models\Product;
use App\Models\Sale;
use App\Models\SaleItem;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class SalesService
{
    /**
     * Cria a venda com status pending e itens,
     * e dispara o processamento assíncrono.
     */
    public function createSale(array $data): Sale
    {
        return DB::transaction(function () use ($data) {
            // Cria venda com valores zerados, status pending
            $sale = Sale::create([
                'total_value' => 0,
                'total_cost'  => 0,
                'profit'      => 0,
                'status'      => 'pending',
            ]);

            foreach ($data['items'] as $itemData) {
                /** @var Product $product */
                $product = Product::findOrFail($itemData['product_id']);

                $quantity  = (int) $itemData['quantity'];
                $unitPrice = (float) $product->sale_price;
                $unitCost  = (float) $product->cost_price;

                SaleItem::create([
                    'sale_id'     => $sale->id,
                    'product_id'  => $product->id,
                    'quantity'    => $quantity,
                    'unit_price'  => $unitPrice,
                    'unit_cost'   => $unitCost,
                    'total_line'  => 0, // calculado no job
                    'profit_line' => 0,
                ]);
            }

            // Dispara job de processamento
            ProcessSaleJob::dispatch($sale->id);

            return $sale;
        });
    }
}