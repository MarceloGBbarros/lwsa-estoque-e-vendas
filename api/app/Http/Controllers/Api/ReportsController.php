<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\SalesReportRequest;
use App\Services\ReportsService;
use Illuminate\Http\JsonResponse;

class ReportsController extends Controller
{
    public function __construct(
        private readonly ReportsService $reportsService
    ) {}

    public function sales(SalesReportRequest $request): JsonResponse
    {
        $filters = $request->validated();

        $report = $this->reportsService->getSalesReport($filters);

        return response()->json($report);
    }
}