<?php

namespace App\Http\Controllers\Subscriptions;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Models\Subscription;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class ConfirmationController extends Controller
{
    public function show(Subscription $subscription): View|RedirectResponse
    {
        $this->authorize('view', $subscription);

        // This is the last step of the SCHOOL's signup wizard - a progress bar
        // and "Thank You! Your Payment Has Been Received". A Super Admin is
        // permitted to open any subscription, but this is not the page for it:
        // it addresses them as the school that just paid and drops them out of
        // their own workflow. Send them to the review screen instead, which is
        // where the decision actually gets made.
        if (auth()->user()?->role === UserRole::SuperAdmin) {
            return redirect()->route('super-admin.subscriptions.show', $subscription);
        }

        $subscription->load(['plan', 'latestPayment']);

        return view('subscriptions.confirmation', [
            'subscription' => $subscription,
        ]);
    }
}
