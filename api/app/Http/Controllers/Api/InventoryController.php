<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreInventoryMovementRequest;
use App\Services\InventoryService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class InventoryController extends Controller
{
    public function __construct(
        private readonly InventoryService $inventoryService
    ) {}

    public function index(Request $request): JsonResponse
        {
            $filters = [
                'product_id' => $request->query('product_id'),
                'sku'        => $request->query('sku'),
            ];

            $data = $this->inventoryService->getInventorySummary($filters);

            return response()->json($data);
        }

    public function store(StoreInventoryMovementRequest $request): JsonResponse
    {
        $movement = $this->inventoryService->registerMovement(
            productId: $request->integer('product_id'),
            type: $request->string('type'),
            quantity: $request->integer('quantity'),
            unitCost: $request->has('unit_cost') ? (float) $request->input('unit_cost') : null,
            description: $request->input('description')
        );

        return response()->json([
            'message' => 'Inventory movement created successfully',
            'data'    => $movement,
        ], 201);
    }
}