<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Business;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BusinessController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $businesses = Business::query()
            ->with('owner:id,name')
            ->when($request->type, fn ($q) => $q->where('type', $request->type))
            ->when($request->verified, fn ($q) => $q->where('is_verified', $request->boolean('verified')))
            ->when($request->search, fn ($q) => $q->where('name', 'like', "%{$request->search}%"))
            ->orderByDesc('created_at')
            ->paginate(20);

        return response()->json($businesses);
    }

    public function show(Business $business): JsonResponse
    {
        $business->load(['owner:id,name', 'listings', 'events']);

        return response()->json($business);
    }

    public function update(Request $request, Business $business): JsonResponse
    {
        $data = $request->validate([
            'is_verified' => ['sometimes', 'boolean'],
            'is_active' => ['sometimes', 'boolean'],
            'name' => ['sometimes', 'string', 'max:150'],
            'type' => ['sometimes', 'in:restaurant,touristic_agency,real_estate_agency,hotel'],
        ]);

        $business->update($data);

        return response()->json($business);
    }

    public function destroy(Business $business): JsonResponse
    {
        $business->delete();

        return response()->json(['message' => 'Business deleted successfully.']);
    }
}
