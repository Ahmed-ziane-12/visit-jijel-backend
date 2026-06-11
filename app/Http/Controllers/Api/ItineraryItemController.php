<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\StoreItineraryItemRequest;
use App\Http\Requests\Api\UpdateItineraryItemRequest;
use App\Models\Itinerary;
use App\Models\ItineraryDay;
use App\Models\ItineraryItem;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;

class ItineraryItemController extends Controller
{
    use AuthorizesRequests;

    public function index(Itinerary $itinerary, ItineraryDay $day): JsonResponse
    {
        $this->authorize('view', $itinerary);

        return response()->json($day->items()->with(['destination', 'listing', 'event'])->get());
    }

    public function store(StoreItineraryItemRequest $request, Itinerary $itinerary, ItineraryDay $day): JsonResponse
    {
        $this->authorize('update', $itinerary);

        $item = $day->items()->create($request->validated());

        return response()->json($item, 201);
    }

    public function update(UpdateItineraryItemRequest $request, Itinerary $itinerary, ItineraryDay $day, ItineraryItem $item): JsonResponse
    {
        $this->authorize('update', $itinerary);

        $item->update($request->validated());

        return response()->json($item);
    }

    public function destroy(Itinerary $itinerary, ItineraryDay $day, ItineraryItem $item): JsonResponse
    {
        $this->authorize('update', $itinerary);

        $item->delete();

        return response()->json(['message' => 'Item deleted successfully.']);
    }
}
