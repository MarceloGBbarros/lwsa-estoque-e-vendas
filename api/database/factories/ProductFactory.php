<?php

namespace Database\Factories;

use App\Models\Product;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;


class ProductFactory extends Factory
{
    protected $model = Product::class;

    public function definition(): array
    {
        $cost = $this->faker->randomFloat(2, 5, 100); // 5 a 100 unidades 
        $marginMultiplier = $this->faker->randomFloat(2, 1.1, 2.0); // 10% a 100% margem de lucro

        return [
            'sku'          => 'SKU-' . strtoupper(Str::random(8)),
            'name'         => $this->faker->words(3, true),
            'cost_price'   => $cost,
            'sale_price'   => round($cost * $marginMultiplier, 2),
            'current_stock'=> 0, 
        ];
    }
}