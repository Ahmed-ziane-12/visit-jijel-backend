<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\StoreReviewRequest;
use App\Models\Review;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ReviewController extends Controller
{
    use AuthorizesRequests;

    public function index(Request $request): JsonResponse
    {
        $reviews = Review::query()
            ->where('is_approved', true)
            ->when($request->listing_id, fn ($q) => $q->where('listing_id', $request->listing_id))
            ->when($request->destination_id, fn ($q) => $q->where('destination_id', $request->destination_id))
            ->when($request->event_id, fn ($q) => $q->where('event_id', $request->event_id))
            ->with('user:id,name')
            ->orderByDesc('created_at')
            ->paginate(10);

        return response()->json($reviews);
    }

    public function store(StoreReviewRequest $request): JsonResponse
    {
        $review = $request->user()->reviews()->create($request->validated());

        return response()->json($review, 201);
    }

    public function destroy(Review $review): JsonResponse
    {
        $this->authorize('delete', $review);

        $review->delete();

        return response()->json(['message' => 'Review deleted successfully.']);
    }
}
