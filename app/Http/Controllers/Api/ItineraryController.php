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

    /**
     * Relations required to serialize itinerary days and items without
     * lazy-loading: item images come from destination media and the
     * latitude/longitude accessors read the underlying listing/event parent.
     *
     * @var list<string>
     */
    private const DETAIL_RELATIONS = [
        'days.items.destination.media',
        'days.items.listing.business',
        'days.items.event.business',
        'days.items.event.destination',
    ];

    public function index(Request $request): JsonResponse
    {
        $itineraries = $request->user()
            ->itineraries()
            ->with(self::DETAIL_RELATIONS)
            ->orderByDesc('created_at')
            ->paginate(10);

        return response()->json($itineraries);
    }

    public function show(Itinerary $itinerary): JsonResponse
    {
        $this->authorize('view', $itinerary);

        $itinerary->load(self::DETAIL_RELATIONS);

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
