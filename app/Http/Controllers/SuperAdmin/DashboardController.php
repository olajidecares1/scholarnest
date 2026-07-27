<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Enums\SubscriptionStatus;
use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Models\School;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function index(): View
    {
        return view('super-admin.dashboard', [
            'stats' => [
                'totalSchools' => School::count(),
                'activeSchools' => School::where('is_active', true)->count(),
                'pendingApprovals' => Subscription::where('status', SubscriptionStatus::PendingVerification)->count(),
                'activeSubscriptions' => Subscription::where('status', SubscriptionStatus::Active)->count(),
                'totalUsers' => User::where('role', UserRole::SchoolAdmin)->count(),
                'expiringSoon' => Subscription::where('status', SubscriptionStatus::Active)
                    ->whereNotNull('ends_at')
                    ->whereBetween('ends_at', [now(), now()->addDays(30)])
                    ->count(),
            ],
            'recentSubscriptions' => Subscription::with(['school', 'plan'])->latest()->limit(5)->get(),
        ]);
    }
}
