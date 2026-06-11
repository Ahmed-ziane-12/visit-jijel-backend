<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
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
            ->where('state', 'active')
            ->get();

        return response()->json($destinations);
    }

    public function show(Destination $destination): JsonResponse
    {
        $destination->load([
            'media',
            'reviews' => fn ($q) => $q->where('is_approved', true)->with('user:id,name'),
            'events' => fn ($q) => $q->published()->orderBy('starts_at')->with('media'),
        ]);

        return response()->json($destination);
    }
}
