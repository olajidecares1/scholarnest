<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Models\Payment;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PaymentController extends Controller
{
    public function index(Request $request): View
    {
        $query = Payment::query()->with(['subscription.school', 'subscription.plan']);

        if ($search = $request->query('search')) {
            $query->whereHas('subscription.school', fn ($q) => $q->where('name', 'like', "%{$search}%"));
        }

        if ($status = $request->query('status')) {
            $query->where('status', $status);
        }

        if ($method = $request->query('method')) {
            $query->where('method', $method);
        }

        $payments = $query->latest()->paginate(10)->withQueryString();

        return view('super-admin.payments.index', [
            'payments' => $payments,
        ]);
    }
}
