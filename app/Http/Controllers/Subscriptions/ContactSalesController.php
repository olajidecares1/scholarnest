<?php

namespace App\Http\Controllers\Subscriptions;

use App\Http\Controllers\Controller;
use App\Models\Plan;
use Illuminate\View\View;

class ContactSalesController extends Controller
{
    public function show(): View
    {
        return view('subscriptions.contact-sales', [
            'plan' => Plan::where('has_custom_pricing', true)->firstOrFail(),
        ]);
    }
}
