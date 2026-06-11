<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\StoreCalendarEventRequest;
use App\Http\Requests\Api\UpdateCalendarEventRequest;
use App\Models\CalendarEvent;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CalendarEventController extends Controller
{
    use AuthorizesRequests;

    public function index(Request $request): JsonResponse
    {
        $events = $request->user()
            ->calendarEvents()
            ->when($request->from, fn ($q) => $q->where('starts_at', '>=', $request->from))
            ->when($request->to, fn ($q) => $q->where('ends_at', '<=', $request->to))
            ->with(['itinerary:id,title', 'event:id,title'])
            ->orderBy('starts_at')
            ->get();

        return response()->json($events);
    }

    public function show(CalendarEvent $calendarEvent): JsonResponse
    {
        $this->authorize('view', $calendarEvent);

        return response()->json($calendarEvent->load(['itinerary', 'event']));
    }

    public function store(StoreCalendarEventRequest $request): JsonResponse
    {
        $calendarEvent = $request->user()->calendarEvents()->create($request->validated());

        return response()->json($calendarEvent, 201);
    }

    public function update(UpdateCalendarEventRequest $request, CalendarEvent $calendarEvent): JsonResponse
    {
        $this->authorize('update', $calendarEvent);

        $calendarEvent->update($request->validated());

        return response()->json($calendarEvent);
    }

    public function destroy(CalendarEvent $calendarEvent): JsonResponse
    {
        $this->authorize('delete', $calendarEvent);

        $calendarEvent->delete();

        return response()->json(['message' => 'Calendar event deleted successfully.']);
    }
}
