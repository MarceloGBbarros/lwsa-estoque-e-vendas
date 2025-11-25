<?php

namespace Tests\Feature;

use App\Jobs\ProcessSaleJob;
use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class SalesApiTest extends TestCase
{
    use RefreshDatabase;

    
    public function test_creates_sale_and_returns_accepted_status(): void
    {
        $product = Product::factory()->create();

        Queue::fake();

        $response = $this->postJson('/api/sales', [
            'items' => [
                ['product_id' => $product->id, 'quantity' => 2],
            ],
        ]);

        $response->assertStatus(202)
            ->assertJsonStructure([
                'message',
                'data' => ['id', 'status'],
            ]);

        Queue::assertPushed(ProcessSaleJob::class);
    }
}