<?php

namespace Tests\Feature;

use App\Jobs\ProcessSaleJob;
use App\Models\Product;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Services\SalesService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class SalesServiceTest extends TestCase
{
    use RefreshDatabase;

    
    public function test_creates_pending_sale_and_dispatches_job(): void
    {
        $product1 = Product::factory()->create();
        $product2 = Product::factory()->create();

        $payload = [
            'items' => [
                ['product_id' => $product1->id, 'quantity' => 2],
                ['product_id' => $product2->id, 'quantity' => 3],
            ],
        ];

        Queue::fake();

        $service = new SalesService();

        $sale = $service->createSale($payload);

        $this->assertInstanceOf(Sale::class, $sale);
        $this->assertEquals('pending', $sale->status);

        $this->assertEquals(2, $sale->items()->count());
        $this->assertTrue(
            SaleItem::where('sale_id', $sale->id)->count() === 2
        );

        Queue::assertPushed(ProcessSaleJob::class, function (ProcessSaleJob $job) use ($sale) {
            return $job->saleId === $sale->id;
        });
    }
}