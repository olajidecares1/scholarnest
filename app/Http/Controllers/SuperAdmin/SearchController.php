<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Models\School;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SearchController extends Controller
{
    public function search(Request $request): JsonResponse
    {
        $query = trim((string) $request->query('q', ''));

        if (mb_strlen($query) < 2) {
            return response()->json(['schools' => [], 'users' => [], 'subscriptions' => []]);
        }

        $schools = School::where('name', 'like', "%{$query}%")
            ->limit(5)
            ->get()
            ->map(fn (School $school) => [
                'title' => $school->name,
                'subtitle' => $school->is_active ? 'Active' : 'Inactive',
                'url' => route('super-admin.schools.show', $school),
            ]);

        $users = User::where(fn ($q) => $q->where('name', 'like', "%{$query}%")->orWhere('email', 'like', "%{$query}%"))
            ->limit(5)
            ->get()
            ->map(fn (User $user) => [
                'title' => $user->name,
                'subtitle' => $user->email,
                'url' => route('super-admin.users.index', ['search' => $user->email]),
            ]);

        $subscriptions = Subscription::with('school')
            ->where('reference', 'like', "%{$query}%")
            ->orWhereHas('school', fn ($q) => $q->where('name', 'like', "%{$query}%"))
            ->limit(5)
            ->get()
            ->map(fn (Subscription $subscription) => [
                'title' => $subscription->reference,
                'subtitle' => $subscription->school->name,
                'url' => route('super-admin.subscriptions.show', $subscription),
            ]);

        return response()->json([
            'schools' => $schools,
            'users' => $users,
            'subscriptions' => $subscriptions,
        ]);
    }
}
