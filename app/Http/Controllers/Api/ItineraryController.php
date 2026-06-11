<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\StoreItineraryRequest;
use App\Http\Requests\Api\UpdateItineraryRequest;
use App\Models\Itinerary;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ItineraryController extends Controller
{
    use AuthorizesRequests;

    public function index(Request $request): JsonResponse
    {
        $itineraries = $request->user()
            ->itineraries()
            ->with('days')
            ->orderByDesc('created_at')
            ->paginate(10);

        return response()->json($itineraries);
    }

    public function show(Itinerary $itinerary): JsonResponse
    {
        $this->authorize('view', $itinerary);

        $itinerary->load([
            'days.items.destination',
            'days.items.listing',
            'days.items.event',
        ]);

        return response()->json($itinerary);
    }

    public function store(StoreItineraryRequest $request): JsonResponse
    {
        $itinerary = $request->user()->itineraries()->create($request->validated());

        return response()->json($itinerary, 201);
    }

    public function update(UpdateItineraryRequest $request, Itinerary $itinerary): JsonResponse
    {
        $this->authorize('update', $itinerary);

        $itinerary->update($request->validated());

        return response()->json($itinerary);
    }

    public function destroy(Itinerary $itinerary): JsonResponse
    {
        $this->authorize('delete', $itinerary);

        $itinerary->delete();

        return response()->json(['message' => 'Itinerary deleted successfully.']);
    }
}
