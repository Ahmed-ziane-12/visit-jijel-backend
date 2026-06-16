<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreDestinationRequest;
use App\Http\Requests\Admin\UpdateDestinationRequest;
use App\Models\Destination;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DestinationController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $destinations = Destination::query()
            ->when($request->category, fn ($q) => $q->where('category', $request->category))
            ->when($request->featured, fn ($q) => $q->where('is_featured', $request->boolean('featured')))
            ->when($request->search, fn ($q) => $q->where('name', 'like', "%{$request->search}%"))
            ->withCount('reviews')
            ->withAvg('reviews', 'rating')
            ->orderByDesc('created_at')
            ->get();

        return response()->json($destinations);
    }

    public function show(Destination $destination): JsonResponse
    {
        $destination->load(['media', 'reviews.user', 'events']);

        return response()->json($destination);
    }

    public function store(StoreDestinationRequest $request): JsonResponse
    {
        $destination = Destination::create($request->validated());

        return response()->json($destination, 201);
    }

    public function update(UpdateDestinationRequest $request, Destination $destination): JsonResponse
    {
        $destination->update($request->validated());

        return response()->json($destination);
    }

    public function destroy(Destination $destination): JsonResponse
    {
        $destination->delete();

        return response()->json(['message' => 'Destination deleted successfully.']);
    }
}
