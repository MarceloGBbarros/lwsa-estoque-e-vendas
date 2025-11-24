<?php

namespace Database\Seeders;

use App\Models\InventoryMovement;
use App\Models\Product;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class InventorySeeder extends Seeder
{
    public function run(): void
    {
        // Vamos garantir um estoque inicial razoável pra não faltar
        $products = Product::all();

        DB::transaction(function () use ($products) {
            foreach ($products as $product) {
                $initialQuantity = rand(500, 1500); // bem alto, pensando em 10k vendas

                InventoryMovement::create([
                    'product_id' => $product->id,
                    'type'       => 'in',
                    'quantity'   => $initialQuantity,
                    'unit_cost'  => $product->cost_price,
                    'description'=> 'Estoque inicial',
                ]);

                $product->update([
                    'current_stock' => $initialQuantity,
                ]);
            }
        });
    }
}