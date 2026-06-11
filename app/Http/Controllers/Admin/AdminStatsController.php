<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Business;
use App\Models\Subscription;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;

class AdminStatsController extends Controller
{
    public function index(): JsonResponse
    {
        $now = Carbon::now();
        $startOfMonth = $now->copy()->startOfMonth();
        $startOfLastMonth = $now->copy()->subMonth()->startOfMonth();
        $endOfLastMonth = $now->copy()->subMonth()->endOfMonth();
        $monthsBack = 12;

        $userGrowth = collect(range($monthsBack - 1, 0))->map(function ($i) use ($now) {
            $month = $now->copy()->subMonths($i);

            return [
                'month' => $month->format('M'),
                'year' => $month->format('Y'),
                'timestamp' => $month->format('Y-m'),
                'count' => User::where('created_at', '<=', $month->copy()->endOfMonth())->count(),
            ];
        });

        $totalUsers = User::count();
        $totalBusinesses = Business::count();
        $activeBusinesses = Business::where('is_active', true)->count();
        $pendingBusinesses = Business::whereNull('is_verified')->orWhere('is_verified', false)->count();
        $activeSubscriptions = Subscription::where('status', 'active')
            ->where('expires_at', '>=', $now)
            ->count();
        $monthlyNewUsers = User::where('created_at', '>=', $startOfMonth)->count();
        $lastMonthNewUsers = User::where('created_at', '>=', $startOfLastMonth)
            ->where('created_at', '<', $startOfMonth)
            ->count();
        $newBusinessesThisMonth = Business::where('created_at', '>=', $startOfMonth)->count();
        $lastMonthNewBusinesses = Business::where('created_at', '>=', $startOfLastMonth)
            ->where('created_at', '<', $startOfMonth)
            ->count();

        $pendingList = Business::whereNull('is_verified')
            ->orWhere('is_verified', false)
            ->with('user:id,name,email')
            ->orderBy('created_at', 'desc')
            ->take(10)
            ->get()
            ->map(fn ($b) => [
                'id' => $b->id,
                'name' => $b->name,
                'type' => $b->type,
                'owner' => $b->user?->name ?? 'N/A',
                'owner_email' => $b->user?->email ?? '',
                'created_at' => $b->created_at?->diffForHumans(),
            ]);

        return response()->json([
            'stats' => [
                'total_users' => $totalUsers,
                'total_businesses' => $totalBusinesses,
                'active_businesses' => $activeBusinesses,
                'pending_businesses' => $pendingBusinesses,
                'active_subscriptions' => $activeSubscriptions,
                'monthly_new_users' => $monthlyNewUsers,
                'last_month_new_users' => $lastMonthNewUsers,
                'new_businesses_this_month' => $newBusinessesThisMonth,
                'last_month_new_businesses' => $lastMonthNewBusinesses,
            ],
            'user_growth' => $userGrowth,
            'pending_businesses' => $pendingList,
        ]);
    }
}
