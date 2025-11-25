<?php

namespace Tests\Feature;

use App\Models\InventoryMovement;
use App\Models\Product;
use App\Services\InventoryService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use RuntimeException;

class InventoryServiceTest extends TestCase
{
    use RefreshDatabase;

    
    public function test_registers_incoming_stock_and_updates_product(): void
    {
        $product = Product::factory()->create([
            'current_stock' => 0,
            'cost_price'    => 10,
            'sale_price'    => 20,
        ]);

        $service = new InventoryService();

        $movement = $service->registerMovement(
            productId: $product->id,
            type: 'in',
            quantity: 5,
            unitCost: 12.5,
            description: 'Teste entrada'
        );

        $this->assertInstanceOf(InventoryMovement::class, $movement);

        $this->assertDatabaseHas('inventory_movements', [
            'id'         => $movement->id,
            'product_id' => $product->id,
            'type'       => 'in',
            'quantity'   => 5,
        ]);

        $this->assertEquals(5, $product->fresh()->current_stock);
    }

   
    public function it_registers_outgoing_stock_when_there_is_enough()
    {
        $product = Product::factory()->create([
            'current_stock' => 10,
            'cost_price'    => 10,
            'sale_price'    => 20,
        ]);

        $service = new InventoryService();

        $service->registerMovement(
            productId: $product->id,
            type: 'out',
            quantity: 4,
            unitCost: null,
            description: 'Teste saída'
        );

        $this->assertEquals(6, $product->fresh()->current_stock);

        $this->assertDatabaseHas('inventory_movements', [
            'product_id' => $product->id,
            'type'       => 'out',
            'quantity'   => 4,
        ]);
    }

    
    public function it_throws_exception_when_outgoing_stock_is_greater_than_available()
    {
        $product = Product::factory()->create([
            'current_stock' => 3,
            'cost_price'    => 10,
            'sale_price'    => 20,
        ]);

        $service = new InventoryService();

        $this->expectException(RuntimeException::class);

        try {
            $service->registerMovement(
                productId: $product->id,
                type: 'out',
                quantity: 5,
                unitCost: null,
                description: 'Teste falha'
            );
        } finally {
            $this->assertEquals(3, $product->fresh()->current_stock);
            $this->assertDatabaseMissing('inventory_movements', [
                'product_id' => $product->id,
                'type'       => 'out',
                'quantity'   => 5,
            ]);
        }
    }
}