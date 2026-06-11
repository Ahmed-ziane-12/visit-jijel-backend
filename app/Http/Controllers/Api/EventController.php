<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\StoreEventRequest;
use App\Http\Requests\Api\UpdateEventRequest;
use App\Models\Event;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class EventController extends Controller
{
    use AuthorizesRequests;

    public function index(Request $request): JsonResponse
    {
        $events = Event::query()
            ->published()
            ->when($request->destination_id, fn ($q) => $q->where('destination_id', $request->destination_id))
            ->when($request->business_id, fn ($q) => $q->where('business_id', $request->business_id))
            ->when($request->from, fn ($q) => $q->where('starts_at', '>=', $request->from))
            ->when($request->to, fn ($q) => $q->where('ends_at', '<=', $request->to))
            ->with(['business:id,name', 'destination:id,name', 'media'])
            ->orderBy('starts_at')
            ->paginate(12);

        return response()->json($events);
    }

    public function show(Event $event): JsonResponse
    {
        $event->load([
            'business:id,name',
            'destination:id,name',
            'creator:id,name',
            'media',
            'reviews' => fn ($q) => $q->where('is_approved', true)->with('user:id,name'),
        ]);

        return response()->json($event);
    }

    public function store(StoreEventRequest $request): JsonResponse
    {
        $event = $request->user()->createdEvents()->create($request->validated());

        return response()->json($event->load('media'), 201);
    }

    public function update(UpdateEventRequest $request, Event $event): JsonResponse
    {
        $this->authorize('update', $event);

        $event->update($request->validated());

        return response()->json($event->load('media'));
    }

    public function destroy(Event $event): JsonResponse
    {
        $this->authorize('delete', $event);

        $event->delete();

        return response()->json(['message' => 'Event deleted successfully.']);
    }
}
