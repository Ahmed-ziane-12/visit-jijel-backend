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

    /**
     * Relations needed by the appended image and coordinate accessors.
     *
     * @var list<string>
     */
    private const ITEM_RELATIONS = [
        'destination.media',
        'listing.business',
        'event.business',
        'event.destination',
    ];

    public function index(Itinerary $itinerary, ItineraryDay $day): JsonResponse
    {
        $this->authorize('view', $itinerary);
        $this->ensureDayBelongsToItinerary($itinerary, $day);

        return response()->json($day->items()->with(self::ITEM_RELATIONS)->get());
    }

    public function show(Itinerary $itinerary, ItineraryDay $day, ItineraryItem $item): JsonResponse
    {
        $this->authorize('view', $itinerary);
        $this->ensureItemBelongsToDay($itinerary, $day, $item);

        return response()->json($item->load(self::ITEM_RELATIONS));
    }

    public function store(StoreItineraryItemRequest $request, Itinerary $itinerary, ItineraryDay $day): JsonResponse
    {
        $this->authorize('update', $itinerary);
        $this->ensureDayBelongsToItinerary($itinerary, $day);

        $item = $day->items()->create($request->validated());

        return response()->json($item, 201);
    }

    public function update(UpdateItineraryItemRequest $request, Itinerary $itinerary, ItineraryDay $day, ItineraryItem $item): JsonResponse
    {
        $this->authorize('update', $itinerary);
        $this->ensureItemBelongsToDay($itinerary, $day, $item);

        $item->update($request->validated());

        return response()->json($item);
    }

    public function destroy(Itinerary $itinerary, ItineraryDay $day, ItineraryItem $item): JsonResponse
    {
        $this->authorize('update', $itinerary);
        $this->ensureItemBelongsToDay($itinerary, $day, $item);

        $item->delete();

        return response()->json(['message' => 'Item deleted successfully.']);
    }

    private function ensureDayBelongsToItinerary(Itinerary $itinerary, ItineraryDay $day): void
    {
        abort_unless($day->itinerary_id === $itinerary->id, 404);
    }

    private function ensureItemBelongsToDay(Itinerary $itinerary, ItineraryDay $day, ItineraryItem $item): void
    {
        $this->ensureDayBelongsToItinerary($itinerary, $day);
        abort_unless($item->itinerary_day_id === $day->id, 404);
    }
}
