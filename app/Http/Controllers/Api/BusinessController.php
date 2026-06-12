<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\StoreBusinessRequest;
use App\Http\Requests\Api\UpdateBusinessRequest;
use App\Models\Business;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BusinessController extends Controller
{
    use AuthorizesRequests;

    public function myBusinesses(Request $request): JsonResponse
    {
        $businesses = $request->user()->businesses()
            ->with('media')
            ->withCount('listings')
            ->latest()
            ->get();

        return response()->json($businesses);
    }

    public function index(Request $request): JsonResponse
    {
        $businesses = Business::query()
            ->where('is_active', true)
            ->where('is_verified', true)
            ->when($request->type, fn ($q) => $q->where('type', $request->type))
            ->when($request->wilaya, fn ($q) => $q->where('wilaya', $request->wilaya))
            ->when(
                $request->filled(['latitude', 'longitude', 'radius']),
                fn ($q) => $q->nearby($request->latitude, $request->longitude, $request->radius)
            )
            ->with([
                'owner:id,name',
                'media',
                'listings' => fn ($q) => $q->published(),
            ])
            ->paginate(12);

        return response()->json($businesses);
    }

    public function show(Business $business): JsonResponse
    {
        $business->load([
            'owner:id,name',
            'media',
            'listings' => fn ($q) => $q->published()->with('media'),
            'events' => fn ($q) => $q->published()->orderBy('starts_at')->with('media'),
        ]);

        return response()->json($business);
    }

    public function store(StoreBusinessRequest $request): JsonResponse
    {
        $business = $request->user()->businesses()->create($request->validated());

        return response()->json($business->load('media'), 201);
    }

    public function update(UpdateBusinessRequest $request, Business $business): JsonResponse
    {
        $this->authorize('update', $business);

        $business->update($request->validated());

        return response()->json($business->load('media'));
    }

    public function destroy(Business $business): JsonResponse
    {
        $this->authorize('delete', $business);

        $business->delete();

        return response()->json(['message' => 'Business deleted successfully.']);
    }
}
