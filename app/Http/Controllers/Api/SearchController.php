<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Business;
use App\Models\Destination;
use App\Models\Event;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SearchController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $request->validate([
            'q' => ['required', 'string', 'min:2', 'max:100'],
            'types' => ['sometimes', 'array'],
            'types.*' => ['in:destinations,businesses,events'],
            'wilaya' => ['sometimes', 'string'],
            'category' => ['sometimes', 'string'],
        ]);

        $q = $request->string('q');
        $types = $request->input('types', ['destinations', 'businesses', 'events']);

        $results = [];

        if (in_array('destinations', $types)) {
            $results['destinations'] = Destination::query()
                ->where(fn ($query) => $query
                    ->where('name', 'ilike', "%{$q}%")
                    ->orWhere('description', 'ilike', "%{$q}%")
                )
                ->when($request->category, fn ($query) => $query->where('category', $request->category))
                ->with('media')
                ->limit(10)
                ->get();
        }

        if (in_array('businesses', $types)) {
            $results['businesses'] = Business::query()
                ->where('is_active', true)
                ->where('is_verified', true)
                ->where(fn ($query) => $query
                    ->where('name', 'ilike', "%{$q}%")
                    ->orWhere('description', 'ilike', "%{$q}%")
                )
                ->when($request->wilaya, fn ($query) => $query->where('wilaya', $request->wilaya))
                ->with('media')
                ->limit(10)
                ->get();
        }

        if (in_array('events', $types)) {
            $results['events'] = Event::query()
                ->published()
                ->where(fn ($query) => $query
                    ->where('title', 'ilike', "%{$q}%")
                    ->orWhere('description', 'ilike', "%{$q}%")
                )
                ->where('starts_at', '>=', now())
                ->with('media')
                ->limit(10)
                ->get();
        }

        return response()->json($results);
    }
}
