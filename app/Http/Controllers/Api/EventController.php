<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\StoreEventRequest;
use App\Http\Requests\Api\UpdateEventRequest;
use App\Http\Resources\EventResource;
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
            ->paginate(12)
            ->through(fn (Event $event) => EventResource::make($event)->resolve());

        return response()->json($events);
    }

    public function show(Request $request, Event $event): JsonResponse
    {
        $viewer = $request->user('sanctum');
        $canViewUnpublished = $viewer !== null
            && ($viewer->isAdmin() || $viewer->id === $event->created_by);

        abort_unless($event->isPublished() || $canViewUnpublished, 404);

        $event->load([
            'business:id,name',
            'destination:id,name',
            'creator:id,name',
            'creator.profile.media',
            'media',
            'reviews' => fn ($q) => $q->where('is_approved', true)->with(['user:id,name', 'user.profile.media']),
        ]);

        return response()->json(new EventResource($event));
    }

    public function store(StoreEventRequest $request): JsonResponse
    {
        $this->authorize('create', Event::class);
        $this->ensureBusinessIsOwned($request);

        $event = $request->user()->createdEvents()->create($request->validated());

        return response()->json(EventResource::make($event->load('media')), 201);
    }

    public function update(UpdateEventRequest $request, Event $event): JsonResponse
    {
        $this->authorize('update', $event);
        $this->ensureBusinessIsOwned($request);

        $event->update($request->validated());

        return response()->json(EventResource::make($event->load('media')));
    }

    public function destroy(Event $event): JsonResponse
    {
        $this->authorize('delete', $event);

        $event->delete();

        return response()->json(['message' => 'Event deleted successfully.']);
    }

    /**
     * A business owner may only attach an event to one of their own
     * businesses. Admins can attach events directly, matching the platform
     * management behaviour.
     */
    private function ensureBusinessIsOwned(Request $request): void
    {
        if ($request->user()->isAdmin() || ! $request->filled('business_id')) {
            return;
        }

        abort_unless(
            $request->user()->businesses()->whereKey($request->input('business_id'))->exists(),
            403,
        );
    }
}
