<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\StoreItineraryDayRequest;
use App\Http\Requests\Api\UpdateItineraryDayRequest;
use App\Models\Itinerary;
use App\Models\ItineraryDay;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;

class ItineraryDayController extends Controller
{
    use AuthorizesRequests;

    public function index(Itinerary $itinerary): JsonResponse
    {
        $this->authorize('view', $itinerary);

        return response()->json($itinerary->days()->with('items')->get());
    }

    public function show(Itinerary $itinerary, ItineraryDay $day): JsonResponse
    {
        $this->authorize('view', $itinerary);

        return response()->json($day->load('items'));
    }

    public function store(StoreItineraryDayRequest $request, Itinerary $itinerary): JsonResponse
    {
        $this->authorize('update', $itinerary);

        $day = $itinerary->days()->create($request->validated());

        return response()->json($day, 201);
    }

    public function update(UpdateItineraryDayRequest $request, Itinerary $itinerary, ItineraryDay $day): JsonResponse
    {
        $this->authorize('update', $itinerary);

        $day->update($request->validated());

        return response()->json($day);
    }

    public function destroy(Itinerary $itinerary, ItineraryDay $day): JsonResponse
    {
        $this->authorize('update', $itinerary);

        $day->delete();

        return response()->json(['message' => 'Day deleted successfully.']);
    }
}
