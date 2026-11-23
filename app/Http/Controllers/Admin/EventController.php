<?php

namespace App\Http\Controllers\Admin;

use App\Enums\EventStatus;
use App\Http\Controllers\Controller;
use App\Http\Resources\EventResource;
use App\Models\Event;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class EventController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $events = Event::query()
            ->with(['business:id,name', 'destination:id,name', 'creator:id,name', 'creator.profile.media'])
            ->when($request->status, fn ($q) => $q->where('status', $request->status))
            ->when($request->search, fn ($q) => $q->where('title', 'like', "%{$request->search}%"))
            ->orderByDesc('starts_at')
            ->paginate(20)
            ->through(fn (Event $event) => EventResource::make($event)->resolve());

        return response()->json($events);
    }

    public function show(Event $event): JsonResponse
    {
        $event->load(['business', 'destination', 'creator:id,name', 'creator.profile.media', 'reviews']);

        return response()->json(new EventResource($event));
    }

    public function update(Request $request, Event $event): JsonResponse
    {
        $data = $request->validate([
            'status' => ['required', Rule::enum(EventStatus::class)],
        ]);

        $original = $event->only('status');
        $event->update($data);

        activity()
            ->causedBy($request->user())
            ->performedOn($event)
            ->event($data['status'])
            ->withProperties([
                'before' => $original,
                'after' => ['status' => $data['status']],
            ])
            ->log('Updated event');

        return response()->json(new EventResource($event));
    }

    public function destroy(Event $event): JsonResponse
    {
        activity()
            ->causedBy(request()->user())
            ->performedOn($event)
            ->event('deleted')
            ->withProperties(['title' => $event->title])
            ->log('Deleted event');

        $event->delete();

        return response()->json(['message' => 'Event deleted successfully.']);
    }
}
