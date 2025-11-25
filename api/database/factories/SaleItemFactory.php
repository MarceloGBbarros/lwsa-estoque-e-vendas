<?php

namespace Database\Factories;

use App\Models\SaleItem;
use Illuminate\Database\Eloquent\Factories\Factory;

class SaleItemFactory extends Factory
{
    protected $model = SaleItem::class;

    public function definition(): array
    {
        return [
            'quantity'    => $this->faker->numberBetween(1, 3),
            'unit_price'  => 0, 
            'unit_cost'   => 0,
            'total_line'  => 0,
            'profit_line' => 0,
        ];
    }
}