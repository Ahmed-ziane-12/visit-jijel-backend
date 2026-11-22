<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\StoreListingRequest;
use App\Http\Requests\Api\UpdateListingRequest;
use App\Models\Business;
use App\Models\Listing;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ListingController extends Controller
{
    use AuthorizesRequests;

    // Owner view: every status, not only published listings.
    public function mine(Business $business): JsonResponse
    {
        $this->authorize('update', $business);

        $listings = $business->listings()
            ->with('media')
            ->latest()
            ->get();

        return response()->json($listings);
    }

    // Nested route: /businesses/{business}/listings
    public function index(Request $request, Business $business): JsonResponse
    {
        $this->ensureBusinessIsPublic($business);

        $listings = $business->listings()
            ->published()
            ->when($request->min_price, fn ($q) => $q->where('price', '>=', $request->min_price))
            ->when($request->max_price, fn ($q) => $q->where('price', '<=', $request->max_price))
            ->with('media')
            ->paginate(12);

        return response()->json($listings);
    }

    public function show(Request $request, Business $business, Listing $listing): JsonResponse
    {
        $this->ensureListingBelongsToBusiness($business, $listing);

        $viewer = $request->user('sanctum');
        $canViewDraft = $viewer !== null
            && ($viewer->isAdmin() || $viewer->id === $business->owner_id);

        abort_unless($listing->isPublished() || $canViewDraft, 404);

        $listing->load([
            'media',
            'reviews' => fn ($q) => $q->where('is_approved', true)->with('user:id,name'),
        ]);

        return response()->json($listing);
    }

    public function store(StoreListingRequest $request, Business $business): JsonResponse
    {
        $this->authorize('create', [Listing::class, $business]);

        $listing = $business->listings()->create($request->validated());

        return response()->json($listing->load('media'), 201);
    }

    public function update(UpdateListingRequest $request, Business $business, Listing $listing): JsonResponse
    {
        $this->ensureListingBelongsToBusiness($business, $listing);
        $this->authorize('update', $listing);

        $listing->update($request->validated());

        return response()->json($listing->load('media'));
    }

    public function destroy(Business $business, Listing $listing): JsonResponse
    {
        $this->ensureListingBelongsToBusiness($business, $listing);
        $this->authorize('delete', $listing);

        $listing->delete();

        return response()->json(['message' => 'Listing deleted successfully.']);
    }

    private function ensureListingBelongsToBusiness(Business $business, Listing $listing): void
    {
        abort_unless($listing->business_id === $business->id, 404);
    }

    private function ensureBusinessIsPublic(Business $business): void
    {
        abort_unless($business->is_active && $business->is_verified, 404);
    }
}
