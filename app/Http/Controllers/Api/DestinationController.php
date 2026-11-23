<?php

namespace App\Http\Controllers\Api;

use App\Enums\DestinationState;
use App\Http\Controllers\Controller;
use App\Http\Resources\DestinationResource;
use App\Models\Destination;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DestinationController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $destinations = Destination::query()
            ->when($request->category, fn ($q) => $q->where('category', $request->category))
            ->when($request->featured, fn ($q) => $q->where('is_featured', true))
            ->when(
                $request->filled(['latitude', 'longitude', 'radius']),
                fn ($q) => $q->nearby($request->latitude, $request->longitude, $request->radius)
            )
            ->with('media')
            ->active()
            ->get();

        return response()->json(DestinationResource::collection($destinations));
    }

    public function show(Request $request, Destination $destination): JsonResponse
    {
        $viewer = $request->user('sanctum');

        abort_unless(
            $destination->state === DestinationState::Active || $viewer?->isAdmin(),
            404,
        );

        $destination->load([
            'media',
            'reviews' => fn ($q) => $q->where('is_approved', true)->with([
                'user' => fn ($q) => $q->select('id', 'name')->with('profile.media'),
            ]),
            'events' => fn ($q) => $q->published()->orderBy('starts_at')->with('media'),
        ]);

        return response()->json(new DestinationResource($destination));
    }
}
