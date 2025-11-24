<?php

namespace Database\Seeders;

use App\Models\Product;
use App\Models\Sale;
use App\Models\SaleItem;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class SalesSeeder extends Seeder
{
    public function run(): void
    {
        $products = Product::all();

        if ($products->isEmpty()) {
            return;
        }

        $totalSales = 10_000;
        $batchSize  = 500; // vamos criar em lotes para não pesar tanto

        for ($i = 0; $i < $totalSales; $i += $batchSize) {
            $this->seedSalesBatch($products, min($batchSize, $totalSales - $i));
        }
    }

    protected function seedSalesBatch($products, int $count): void
    {
        DB::transaction(function () use ($products, $count) {
            for ($i = 0; $i < $count; $i++) {
                /** @var \App\Models\Sale $sale */
                $sale = Sale::factory()->create();

                $itemsCount = rand(2, 5);
                $chosenProducts = $products->random($itemsCount);

                $totalValue = 0;
                $totalCost  = 0;

                foreach ($chosenProducts as $product) {
                    // Quantidade por item
                    $quantity   = rand(1, 3);
                    $unitPrice  = $product->sale_price;
                    $unitCost   = $product->cost_price;
                    $lineTotal  = $unitPrice * $quantity;
                    $lineCost   = $unitCost * $quantity;
                    $lineProfit = $lineTotal - $lineCost;

                    // Não vamos mexer no estoque aqui
                    // (a lógica de estoque será tratada na aplicação / jobs)
                    SaleItem::create([
                        'sale_id'     => $sale->id,
                        'product_id'  => $product->id,
                        'quantity'    => $quantity,
                        'unit_price'  => $unitPrice,
                        'unit_cost'   => $unitCost,
                        'total_line'  => $lineTotal,
                        'profit_line' => $lineProfit,
                    ]);

                    $totalValue += $lineTotal;
                    $totalCost  += $lineCost;
                }

                $sale->update([
                    'total_value' => $totalValue,
                    'total_cost'  => $totalCost,
                    'profit'      => $totalValue - $totalCost,
                ]);
            }
        });
    }
}