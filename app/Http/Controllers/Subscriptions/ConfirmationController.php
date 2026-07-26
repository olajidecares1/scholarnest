<?php

namespace App\Http\Controllers\Subscriptions;

use App\Http\Controllers\Controller;
use App\Models\Subscription;
use Illuminate\View\View;

class ConfirmationController extends Controller
{
    public function show(Subscription $subscription): View
    {
        $this->authorize('view', $subscription);

        $subscription->load(['plan', 'latestPayment']);

        return view('subscriptions.confirmation', [
            'subscription' => $subscription,
        ]);
    }
}
