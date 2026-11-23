<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\ReviewResource;
use App\Models\Review;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ReviewController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $reviews = Review::query()
            ->with(['user:id,name', 'user.profile.media'])
            ->when(
                $request->has('approved'),
                fn ($q) => $q->where('is_approved', $request->boolean('approved'))
            )
            ->when($request->listing_id, fn ($q) => $q->where('listing_id', $request->listing_id))
            ->when($request->destination_id, fn ($q) => $q->where('destination_id', $request->destination_id))
            ->when($request->event_id, fn ($q) => $q->where('event_id', $request->event_id))
            ->orderByDesc('created_at')
            ->paginate(20)
            ->through(fn (Review $review) => ReviewResource::make($review)->resolve());

        return response()->json($reviews);
    }

    public function show(Review $review): JsonResponse
    {
        return response()->json(new ReviewResource($review->load(['user:id,name', 'user.profile.media'])));
    }

    // Approve or reject
    public function update(Request $request, Review $review): JsonResponse
    {
        $data = $request->validate([
            'is_approved' => ['required', 'boolean'],
        ]);

        $original = $review->only('is_approved');
        $review->update($data);

        activity()
            ->causedBy($request->user())
            ->performedOn($review)
            ->event($data['is_approved'] ? 'approved' : 'rejected')
            ->withProperties([
                'before' => $original,
                'after' => ['is_approved' => $data['is_approved']],
            ])
            ->log('Updated review');

        return response()->json(new ReviewResource($review));
    }

    public function destroy(Review $review): JsonResponse
    {
        activity()
            ->causedBy(request()->user())
            ->performedOn($review)
            ->event('deleted')
            ->withProperties(['id' => $review->id])
            ->log('Deleted review');

        $review->delete();

        return response()->json(['message' => 'Review deleted successfully.']);
    }
}
