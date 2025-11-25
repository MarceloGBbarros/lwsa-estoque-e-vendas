<?php

namespace App\Jobs;

use App\Models\InventoryMovement;
use App\Models\Product;
use App\Models\Sale;
use App\Models\SaleItem;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class ProcessSaleJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        public int $saleId
    ) {}

    public function handle(): void
    {
        DB::transaction(function () {
            /** @var Sale $sale */
            $sale = Sale::lockForUpdate()->with(['items'])->findOrFail($this->saleId);

            if ($sale->status !== 'pending') {
                // Já processada ou falhada, não faz nada
                return;
            }

            $totalValue = 0;
            $totalCost  = 0;

            // Primeiro passamos verificando se há estoque suficiente para todos os itens
            foreach ($sale->items as $item) {
                /** @var SaleItem $item */
                $product = Product::whereKey($item->product_id)
                    ->lockForUpdate()
                    ->firstOrFail();

                if ($product->current_stock < $item->quantity) {
                    // Marca venda como falhada e aborta totalmente (sem debitar estoque)
                    $sale->status = 'failed';
                    $sale->save();

                    throw new RuntimeException("Insufficient stock for product ID {$product->id}");
                }
            }

            // Se passou na validação de estoque, agora debitamos e criamos movements
            foreach ($sale->items as $item) {
                $product = Product::whereKey($item->product_id)
                    ->lockForUpdate()
                    ->firstOrFail();

                $quantity   = $item->quantity;
                $unitPrice  = $item->unit_price;
                $unitCost   = $item->unit_cost;
                $lineTotal  = $unitPrice * $quantity;
                $lineCost   = $unitCost * $quantity;
                $lineProfit = $lineTotal - $lineCost;

                // Debita estoque
                $product->current_stock -= $quantity;
                $product->save();

                // Registra movimento de saída
                InventoryMovement::create([
                    'product_id' => $product->id,
                    'type'       => 'out',
                    'quantity'   => $quantity,
                    'unit_cost'  => $unitCost,
                    'description'=> 'Venda #' . $sale->id,
                ]);

                // Atualiza item
                $item->total_line  = $lineTotal;
                $item->profit_line = $lineProfit;
                $item->save();

                $totalValue += $lineTotal;
                $totalCost  += $lineCost;
            }

            // Atualiza venda
            $sale->total_value = $totalValue;
            $sale->total_cost  = $totalCost;
            $sale->profit      = $totalValue - $totalCost;
            $sale->status      = 'processed';
            $sale->save();
        });
    }
}