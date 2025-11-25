<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreSaleRequest;
use App\Models\Sale;
use App\Services\SalesService;
use Illuminate\Http\JsonResponse;

class SalesController extends Controller
{
    public function __construct(
        private readonly SalesService $salesService
    ) {}

    public function store(StoreSaleRequest $request): JsonResponse
    {
        $sale = $this->salesService->createSale($request->validated());

        return response()->json([
            'message' => 'Pedido criado com sucesso',
            'data'    => [
                'id'     => $sale->id,
                'status' => $sale->status,
            ],
        ], 202);
    }

    public function show(Sale $sale): JsonResponse
    {
        $sale->load(['items.product']);

        return response()->json($sale);
    }
}