<?php

namespace App\Http\Controllers\Admin\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;

class AuthController extends Controller
{
    // ── Login ─────────────────────────────────────────────────
    public function login(Request $request): JsonResponse
    {
        $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        if (! Auth::attempt($request->only('email', 'password'))) {
            return response()->json(['message' => 'Invalid credentials.'], 401);
        }

        $user = Auth::user();

        if (! $user->isAdmin()) {
            Auth::logout();

            return response()->json(['message' => 'Unauthorized.'], 403);
        }

        $token = $user->createToken('admin-panel')->plainTextToken;

        activity()
            ->causedBy($user)
            ->event('login')
            ->log('Logged in');

        return response()->json([
            'user' => $user,
            'is_super_admin' => $user->isSuperAdmin(),
            'must_reset_password' => $user->mustResetPassword(),
            'token' => $token,
        ]);
    }

    // ── Logout ────────────────────────────────────────────────
    public function logout(Request $request): JsonResponse
    {
        $user = $request->user();

        if ($token = $user?->currentAccessToken()) {
            $token->delete();
        }

        activity()
            ->causedBy($user)
            ->event('logout')
            ->log('Logged out');

        Auth::guard('web')->logout();

        return response()->json(['message' => 'Logged out successfully.']);
    }

    // ── Create admin — super admin only ───────────────────────
    public function createAdmin(Request $request): JsonResponse
    {
        if (! $request->user()->isSuperAdmin()) {
            return response()->json(['message' => 'Only super admins can create admin accounts.'], 403);
        }

        $data = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'email' => ['required', 'email', 'unique:users,email'],
        ]);

        // Check the 5 admin cap (excluding super admin)
        $adminCount = User::where('is_admin', '=', true, 'and')
            ->where('is_super_admin', '=', false, 'and')
            ->count('*');

        if ($adminCount >= 5) {
            return response()->json(['message' => 'Admin account limit reached (5 max).'], 422);
        }

        $temporaryPassword = 'Admin@'.rand(1000, 9999);

        $admin = User::create([
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => Hash::make($temporaryPassword),
            'is_admin' => true,
            'is_super_admin' => false,
            'must_reset_password' => true,
            'email_verified_at' => now(),
            'created_by' => $request->user()->id,
        ]);

        activity()
            ->causedBy($request->user())
            ->performedOn($admin)
            ->event('created')
            ->withProperties(['email' => $admin->email])
            ->log('Created admin account');

        return response()->json([
            'message' => 'Admin account created.',
            'admin' => $admin,
            'temporary_password' => $temporaryPassword, // show once, then discard
        ], 201);
    }

    // ── Force password reset on first login ───────────────────
    public function resetPassword(Request $request): JsonResponse
    {
        $request->validate([
            'password' => ['required', 'confirmed', Password::min(8)->letters()->mixedCase()->numbers()],
        ]);

        $user = $request->user();

        $user->update([
            'password' => Hash::make($request->password),
            'must_reset_password' => false,
        ]);

        activity()
            ->causedBy($user)
            ->event('password_reset')
            ->log('Reset password');

        return response()->json(['message' => 'Password updated successfully.']);
    }

    // ── List admins — super admin only ────────────────────────
    public function listAdmins(Request $request): JsonResponse
    {
        if (! $request->user()->isSuperAdmin()) {
            return response()->json(['message' => 'Unauthorized.'], 403);
        }

        $admins = User::where('is_admin', '=', true, 'and')
            ->with('creator:id,name')
            ->get(['id', 'name', 'email', 'is_super_admin', 'created_by', 'created_at']);

        return response()->json($admins);
    }

    // ── Delete admin — super admin only ───────────────────────
    public function deleteAdmin(Request $request, User $user): JsonResponse
    {
        if (! $request->user()->isSuperAdmin()) {
            return response()->json(['message' => 'Unauthorized.'], 403);
        }

        if ($user->isSuperAdmin()) {
            return response()->json(['message' => 'Cannot delete a super admin.'], 422);
        }

        activity()
            ->causedBy($request->user())
            ->performedOn($user)
            ->event('deleted')
            ->withProperties(['email' => $user->email])
            ->log('Deleted admin account');

        $user->delete();

        return response()->json(['message' => 'Admin account deleted.']);
    }
}
