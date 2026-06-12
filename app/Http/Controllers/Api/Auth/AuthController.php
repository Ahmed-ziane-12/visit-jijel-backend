<?php

namespace App\Http\Controllers\Api\Auth;

use App\Actions\RegisterUser;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\ForgotPasswordRequest;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Requests\Auth\RegisterRequest;
use App\Http\Requests\Auth\ResetPasswordRequest;
use App\Http\Requests\Auth\UpdatePasswordRequest;
use App\Http\Requests\Auth\UpdateProfileRequest;
use App\Models\User;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Auth\Events\Verified;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;

class AuthController extends Controller
{
    // ──────────────────────────────────────────────────────────
    //  REGISTER
    // ──────────────────────────────────────────────────────────

    public function register(RegisterRequest $request, RegisterUser $action): JsonResponse
    {
        $user = $action->handle($request->validated());

        $token = null;

        // Clients get a session immediately so they can use the app;
        // business owners must verify first, so they are not auto-logged in.
        if ($user->profile->role === 'client') {
            Auth::login($user);
            $request->session()->regenerate();

            // Issue a Sanctum token so the frontend can skip cookie-based auth
            $token = $user->createToken('auth-token')->plainTextToken;
        }

        return response()->json([
            'message' => 'Registration successful. Please verify your email.',
            'user' => $user->load('profile'),
            'token' => $token,
        ], 201);
    }

    // ──────────────────────────────────────────────────────────
    //  LOGIN
    // ──────────────────────────────────────────────────────────

    public function login(LoginRequest $request): JsonResponse
    {
        if (! Auth::attempt($request->only('email', 'password'))) {
            return response()->json([
                'message' => 'Invalid credentials.',
            ], 401);
        }

        $user = Auth::user()->load('profile');
        $role = $user->profile->role;

        // Business owners must verify before they can do anything
        if ($role === 'business_owner' && ! $user->hasVerifiedEmail()) {
            Auth::logout();

            return response()->json([
                'message' => 'You must verify your email before logging in.',
                'email_unverified' => true,
            ], 403);
        }

        // Issue a Sanctum token for mobile / API-token clients
        $token = $user->createToken('mobile-app')->plainTextToken;

        // Clients and admins log in freely
        // Next.js uses 'email_verified' to decide whether to show the alert
        return response()->json([
            'user' => $user,
            'role' => $role,
            'email_verified' => $user->hasVerifiedEmail(),
            'token' => $token,
        ]);
    }

    // ──────────────────────────────────────────────────────────
    //  LOGOUT
    // ──────────────────────────────────────────────────────────

    public function logout(Request $request): JsonResponse
    {
        // Revoke the current API token if the request was authenticated via Bearer token
        if ($token = $request->user()?->currentAccessToken()) {
            $token->delete();
        }

        // Clean up any lingering web session (used by cookie-based auth)
        Auth::guard('web')->logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return response()->json([
            'message' => 'Logged out successfully.',
        ]);
    }

    // ──────────────────────────────────────────────────────────
    //  EMAIL VERIFICATION
    // ──────────────────────────────────────────────────────────

    public function sendVerificationEmail(Request $request): JsonResponse
    {
        if ($request->user()->hasVerifiedEmail()) {
            return response()->json([
                'message' => 'Email already verified.',
            ]);
        }

        $request->user()->sendEmailVerificationNotification();

        return response()->json([
            'message' => 'Verification link sent.',
        ]);
    }

    public function verify(Request $request)
    {
        $user = User::findOrFail($request->id);

        if (! hash_equals((string) $request->hash, sha1($user->getEmailForVerification()))) {
            return response()->json([
                'status' => 'error',
                'message' => 'Invalid verification link.',
            ], 400);
        }

        if ($user->hasVerifiedEmail()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Email already verified.',
            ], 400);
        }

        if ($user->markEmailAsVerified()) {
            event(new Verified($user));
        }

        return response()->json([
            'status' => 'success',
            'message' => 'Email verified successfully!',
        ]);
    }

    public function verifyEmail(Request $request, int $id, string $hash): JsonResponse
    {
        $user = User::findOrFail($id);

        // Make sure the hash matches the user's email
        if (! hash_equals(sha1($user->getEmailForVerification()), $hash)) {
            return response()->json([
                'message' => 'Invalid verification link.',
            ], 403);
        }

        if ($user->hasVerifiedEmail()) {
            return response()->json([
                'message' => 'Email already verified.',
            ]);
        }

        $user->markEmailAsVerified();

        event(new Verified($user));

        return response()->json([
            'message' => 'Email verified successfully.',
        ]);
    }

    // ──────────────────────────────────────────────────────────
    //  PASSWORD RESET
    // ──────────────────────────────────────────────────────────

    public function forgotPassword(ForgotPasswordRequest $request): JsonResponse
    {
        $status = Password::sendResetLink(
            $request->only('email')
        );

        return $status === Password::RESET_LINK_SENT
            ? response()->json(['message' => __($status)])
            : response()->json(['message' => __($status)], 422);
    }

    public function resetPassword(ResetPasswordRequest $request): JsonResponse
    {
        $status = Password::reset(
            $request->only('email', 'password', 'password_confirmation', 'token'),
            function (User $user, string $password) {
                $user->forceFill([
                    'password' => Hash::make($password),
                ])->setRememberToken(Str::random(60));

                $user->save();

                event(new PasswordReset($user));
            }
        );

        return $status === Password::PASSWORD_RESET
            ? response()->json(['message' => __($status)])
            : response()->json(['message' => __($status)], 422);
    }

    // ──────────────────────────────────────────────────────────
    //  UPDATE PASSWORD
    // ──────────────────────────────────────────────────────────

    public function updatePassword(UpdatePasswordRequest $request): JsonResponse
    {
        if (! Hash::check($request->current_password, $request->user()->password)) {
            return response()->json([
                'message' => 'The provided password does not match your current password.',
                'errors' => ['current_password' => ['Incorrect current password.']],
            ], 422);
        }

        $request->user()->update([
            'password' => Hash::make($request->password),
        ]);

        return response()->json([
            'message' => 'Password updated successfully.',
        ]);
    }

    // ──────────────────────────────────────────────────────────
    //  UPDATE PROFILE
    // ──────────────────────────────────────────────────────────

    public function updateProfile(UpdateProfileRequest $request): JsonResponse
    {
        $user = $request->user();
        $profile = $user->profile;

        $user->update($request->only('name', 'email'));
        $profile->update($request->only('phone', 'bio', 'wilaya', 'commune'));

        return response()->json([
            'message' => 'Profile updated successfully.',
            'user' => $user->fresh(['profile', 'profile.media']),
        ]);
    }
    // ──────────────────────────────────────────────────────────
    //  ME (current authenticated user)
    // ──────────────────────────────────────────────────────────

    public function me(Request $request): JsonResponse
    {
        return response()->json(
            $request->user()->load(['profile', 'profile.media'])
        );
    }
}
