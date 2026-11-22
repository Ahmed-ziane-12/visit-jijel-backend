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
    /**
     * Maximum number of non-super-admin accounts, mirrored from the
     * super-admin create endpoint so this route cannot bypass the cap.
     */
    private const MAX_ADMINS = 5;

    public function index(Request $request): JsonResponse
    {
        $query = User::query()
            ->with('profile')
            ->when($request->role === 'super_admin', fn ($q) => $q->where('is_super_admin', true))
            ->when($request->role === 'admin', fn ($q) => $q->where('is_admin', true)->where('is_super_admin', false))
            ->when(in_array($request->role, ['business_owner', 'client'], true), fn ($q) => $q->whereHas('profile', fn ($q) => $q->where('role', $request->role)))
            ->when($request->search, fn ($q) => $q->where(function ($q) use ($request) {
                $q->where('name', 'like', "%{$request->search}%")
                    ->orWhere('email', 'like', "%{$request->search}%");
            }))
            ->orderByDesc('created_at');

        $users = $request->boolean('all') ? $query->get() : $query->paginate(20);

        return response()->json($users);
    }

    public function show(User $user): JsonResponse
    {
        $user->load(['profile', 'businesses', 'itineraries']);

        return response()->json($user);
    }

    // Super admin creating another admin account
    public function store(Request $request): JsonResponse
    {
        abort_unless($request->user()->isSuperAdmin(), 403);

        if ($this->adminCount() >= self::MAX_ADMINS) {
            return response()->json(['message' => 'Admin account limit reached (5 max).'], 422);
        }

        $data = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'email' => ['required', 'email', 'unique:users,email'],
            'password' => ['required', 'min:8'],
        ]);

        $user = DB::transaction(function () use ($data, $request) {
            $user = User::create([
                'name' => $data['name'],
                'email' => $data['email'],
                'password' => Hash::make($data['password']),
            ]);

            // Privilege flags are not mass assignable on purpose.
            $user->forceFill([
                'is_admin' => true,
                'is_super_admin' => false,
                'email_verified_at' => now(),
                'created_by' => $request->user()->id,
            ])->save();

            $user->profile()->create();

            return $user;
        });

        activity()
            ->causedBy($request->user())
            ->performedOn($user)
            ->event('created')
            ->withProperties(['email' => $user->email])
            ->log('Created user');

        return response()->json($user->load('profile'), 201);
    }

    public function update(Request $request, User $user): JsonResponse
    {
        $actor = $request->user();

        // Only a super admin may touch another admin account, and no regular
        // admin may rewrite a super admin's identity (the previous code
        // allowed an email swap followed by a password reset).
        if ($user->isSuperAdmin() && ! $actor->isSuperAdmin()) {
            abort(403, 'Only super admins can update a super admin.');
        }

        if ($user->isAdmin() && ! $actor->isSuperAdmin() && $actor->id !== $user->id) {
            abort(403, 'Only super admins can update admin accounts.');
        }

        $data = $request->validate([
            'name' => ['sometimes', 'string', 'max:100'],
            'email' => ['sometimes', 'email', 'unique:users,email,'.$user->id],
            'role' => ['sometimes', 'in:business_owner,client'],
            'wilaya' => ['sometimes', 'string'],
            'commune' => ['sometimes', 'string'],
        ]);

        $original = $user->only('name', 'email');

        DB::transaction(function () use ($user, $data) {
            $attributes = array_filter([
                'name' => $data['name'] ?? null,
                'email' => $data['email'] ?? null,
            ], fn ($value) => $value !== null);

            $emailChanged = isset($attributes['email'])
                && $attributes['email'] !== $user->email;

            if ($emailChanged) {
                // A changed address must be verified again.
                $attributes['email_verified_at'] = null;
            }

            $user->forceFill($attributes)->save();

            $profileData = array_filter([
                'role' => $data['role'] ?? null,
                'wilaya' => $data['wilaya'] ?? null,
                'commune' => $data['commune'] ?? null,
            ], fn ($value) => $value !== null);

            if ($profileData !== []) {
                $user->profile()->updateOrCreate([], $profileData);
            }
        });

        activity()
            ->causedBy($request->user())
            ->performedOn($user)
            ->event('updated')
            ->withProperties([
                'before' => $original,
                'after' => $user->only('name', 'email'),
            ])
            ->log('Updated user');

        return response()->json($user->fresh('profile'));
    }

    public function destroy(Request $request, User $user): JsonResponse
    {
        if ($user->isSuperAdmin()) {
            return response()->json(['message' => 'Cannot delete a super admin.'], 422);
        }

        if ($user->id === $request->user()->id) {
            return response()->json(['message' => 'Cannot delete your own account.'], 422);
        }

        activity()
            ->causedBy($request->user())
            ->performedOn($user)
            ->event('deleted')
            ->withProperties(['email' => $user->email])
            ->log('Deleted user');

        $user->delete();

        return response()->json(['message' => 'User deleted successfully.']);
    }

    private function adminCount(): int
    {
        return User::where('is_admin', true)
            ->where('is_super_admin', false)
            ->count();
    }
}
