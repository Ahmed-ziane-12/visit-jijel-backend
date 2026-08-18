<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Plan;
use Illuminate\Http\JsonResponse;

class PlanController extends Controller
{
    public function index(): JsonResponse
    {
        $plans = Plan::orderByDesc('created_at')->get();

        return response()->json($plans);
    }

    public function show(Plan $plan): JsonResponse
    {
        return response()->json($plan);
    }
}
