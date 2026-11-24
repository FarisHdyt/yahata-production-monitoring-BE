<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\ParsesFilters;
use App\Services\DashboardService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    use ParsesFilters;

    public function index(Request $request, DashboardService $service): JsonResponse
    {
        $f = $this->filters($request);

        return response()->json([
            'data' => $service->build(
                $f['date'] ?? now()->toDateString(),
                $f['shift'],
                $f['machine'],
                $f['partNumber'],
            ),
        ]);
    }
}