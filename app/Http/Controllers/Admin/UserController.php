<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class UserController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = User::query()
            ->with('profile')
            ->when($request->role, fn ($q) => $q->whereHas('profile', fn ($q) => $q->where('role', $request->role)))
            ->when($request->search, fn ($q) => $q->where('name', 'like', "%{$request->search}%")
                ->orWhere('email', 'like', "%{$request->search}%"))
            ->orderByDesc('created_at');

        $users = $request->boolean('all') ? $query->get() : $query->paginate(20);

        return response()->json($users);
    }

    public function show(User $user): JsonResponse
    {
        $user->load(['profile', 'businesses', 'itineraries']);

        return response()->json($user);
    }

    // Admin creating another admin account
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'email' => ['required', 'email', 'unique:users,email'],
            'password' => ['required', 'min:8'],
        ]);

        $user = DB::transaction(function () use ($data) {
            $user = User::create([
                'name' => $data['name'],
                'email' => $data['email'],
                'password' => Hash::make($data['password']),
            ]);

            $user->profile()->create(['role' => 'admin']);

            return $user;
        });

        return response()->json($user->load('profile'), 201);
    }

    public function update(Request $request, User $user): JsonResponse
    {
        $data = $request->validate([
            'name' => ['sometimes', 'string', 'max:100'],
            'email' => ['sometimes', 'email', 'unique:users,email,'.$user->id],
            'role' => ['sometimes', 'in:admin,business_owner,client'],
            'wilaya' => ['sometimes', 'string'],
            'commune' => ['sometimes', 'string'],
        ]);

        DB::transaction(function () use ($user, $data) {
            $user->update(array_filter([
                'name' => $data['name'] ?? null,
                'email' => $data['email'] ?? null,
            ]));

            if (isset($data['role'])) {
                $user->profile()->update(['role' => $data['role']]);
            }
        });

        return response()->json($user->fresh('profile'));
    }

    public function destroy(User $user): JsonResponse
    {
        $user->delete();

        return response()->json(['message' => 'User deleted successfully.']);
    }
}
