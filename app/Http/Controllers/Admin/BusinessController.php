<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Business;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BusinessController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $businesses = Business::query()
            ->with('owner:id,name')
            ->when($request->type, fn ($q) => $q->where('type', $request->type))
            ->when($request->verified, fn ($q) => $q->where('is_verified', $request->boolean('verified')))
            ->when($request->search, fn ($q) => $q->where('name', 'like', "%{$request->search}%"))
            ->orderByDesc('created_at')
            ->paginate(20);

        return response()->json($businesses);
    }

    public function show(Business $business): JsonResponse
    {
        $business->load([
            'owner:id,name',
            'media',
            'listings' => fn ($q) => $q->published(),
        ]);

        return response()->json($business);
    }

    public function update(Request $request, Business $business): JsonResponse
    {
        $data = $request->validate([
            'is_verified' => ['sometimes', 'boolean'],
            'is_active' => ['sometimes', 'boolean'],
            'name' => ['sometimes', 'string', 'max:150'],
            'type' => ['sometimes', 'in:restaurant,touristic_agency,real_estate_agency,hotel'],
        ]);

        $changes = collect();
        $originalVerified = $business->is_verified;
        $originalActive = $business->is_active;

        if (array_key_exists('is_verified', $data) && $data['is_verified'] !== $originalVerified) {
            $changes->push($data['is_verified'] ? 'verified' : 'unverified');
        }

        if (array_key_exists('is_active', $data) && $data['is_active'] !== $originalActive) {
            $changes->push($data['is_active'] ? 'activated' : 'deactivated');
        }

        $business->update($data);

        if ($changes->isNotEmpty()) {
            activity()
                ->causedBy($request->user())
                ->performedOn($business)
                ->event($changes->first())
                ->withProperties([
                    'changes' => $data,
                    'prev_is_verified' => $originalVerified,
                    'prev_is_active' => $originalActive,
                ])
                ->log('Business '.$changes->implode(' and '));
        }

        return response()->json($business);
    }

    public function destroy(Request $request, Business $business): JsonResponse
    {
        activity()
            ->causedBy($request->user())
            ->performedOn($business)
            ->event('deleted')
            ->withProperties(['name' => $business->name])
            ->log('Deleted business');

        $business->delete();

        return response()->json(['message' => 'Business deleted successfully.']);
    }
}
