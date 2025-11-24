<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreInventoryMovementRequest;
use App\Services\InventoryService;
use Illuminate\Http\JsonResponse;

class InventoryController extends Controller
{
    public function __construct(
        private readonly InventoryService $inventoryService
    ) {}

    public function index(): JsonResponse
    {
        $data = $this->inventoryService->getInventorySummary();

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