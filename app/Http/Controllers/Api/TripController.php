<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\StoreTripRequest;
use App\Models\Itinerary;
use App\Services\ItineraryGenerator;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TripController extends Controller
{
    use AuthorizesRequests;

    public function __construct(private readonly ItineraryGenerator $generator) {}

    /**
     * Validate the trip wizard and generate the itinerary (7 phases).
     */
    public function store(StoreTripRequest $request): JsonResponse
    {
        $itinerary = $this->generator->generate($request->user(), $request->validated());

        return response()->json($itinerary, 201);
    }

    public function show(Itinerary $itinerary): JsonResponse
    {
        $this->authorize('view', $itinerary);

        $itinerary->load([
            'days.items.destination',
            'days.items.listing.business',
            'days.items.event.business',
            'days.items.event.destination',
        ]);

        return response()->json($itinerary);
    }

    /**
     * Best remaining candidates for this trip — powers the "add activity" panel.
     */
    public function recommendations(Request $request, Itinerary $itinerary): JsonResponse
    {
        $this->authorize('view', $itinerary);

        $limit = min(24, max(1, (int) $request->query('limit', 12)));

        return response()->json([
            'data' => $this->generator->recommend($itinerary, $limit),
        ]);
    }
}
