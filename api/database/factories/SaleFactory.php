<?php

namespace Database\Factories;

use App\Models\Sale;
use Illuminate\Database\Eloquent\Factories\Factory;
use Carbon\Carbon;

/**
 * @extends Factory<Sale>
 */
class SaleFactory extends Factory
{
    protected $model = Sale::class;

    public function definition(): array
    {
        // criar datas aleatórias nos últimos 12 meses
        $createdAt = Carbon::now()->subDays(rand(0, 365));

        return [
            'total_value' => 0,  
            'total_cost'  => 0,
            'profit'      => 0,
            'status'      => 'processed', // para dados históricos
            'created_at'  => $createdAt,
            'updated_at'  => $createdAt,
        ];
    }
}