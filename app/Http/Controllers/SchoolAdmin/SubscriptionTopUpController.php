<?php

namespace App\Http\Controllers\SchoolAdmin;

use App\Enums\PlanKey;
use App\Enums\SubscriptionTopUpStatus;
use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Http\Requests\Subscriptions\StoreTopUpRequest;
use App\Models\AuditLog;
use App\Models\SubscriptionTopUp;
use App\Models\User;
use App\Notifications\NewSubscriptionTopUpSubmittedNotification;
use App\Services\ReceiptUploadService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\View\View;

class SubscriptionTopUpController extends Controller
{
    public function __construct(private readonly ReceiptUploadService $receiptUploader) {}

    public function create(): View
    {
        $school = auth()->user()->school;
        $subscription = $school->activeSubscription;

        abort_unless($school->hasPlanAccess(PlanKey::Basic), 403, 'Student slot top-ups are only available on the Basic plan.');

        return view('school-admin.subscriptions.top-up', [
            'plan' => $subscription->plan,
            'subscription' => $subscription,
            'activeStudentsCount' => $school->students()->where('is_active', true)->count(),
        ]);
    }

    public function store(StoreTopUpRequest $request): RedirectResponse
    {
        $school = auth()->user()->school;
        $subscription = $school->activeSubscription;

        abort_unless($school->hasPlanAccess(PlanKey::Basic), 403, 'Student slot top-ups are only available on the Basic plan.');

        $validated = $request->validated();

        // Snapshotted rather than read back off the plan at approval time. The
        // Super Admin can change the plan price, and doing so must never
        // retroactively rewrite the arithmetic of a payment already made.
        $pricePerStudent = (float) $subscription->plan->price_per_student_per_term;

        $additionalAmount = (float) $validated['additional_students_count'] * $pricePerStudent;
        $receipt = $this->receiptUploader->store($school, $request->file('receipt'));
        $reference = strtoupper(Str::slug($school->name, '')).'-TOPUP-'.now()->format('dmy').'-'.strtoupper(Str::random(4));

        // Recorded as a REQUEST only. Nothing here touches the school's
        // allocation - that happens solely when a Super Admin approves it.
        $topUp = DB::transaction(fn () => SubscriptionTopUp::create([
            'subscription_id' => $subscription->id,
            'additional_students_count' => $validated['additional_students_count'],
            'additional_amount' => $additionalAmount,
            'price_per_student' => $pricePerStudent,
            'payment_method' => $validated['payment_method'],
            'receipt_path' => $receipt['path'],
            'receipt_original_name' => $receipt['original_name'],
            'status' => SubscriptionTopUpStatus::PendingVerification,
            'reference' => $reference,
        ]));

        AuditLog::record('subscription.topup.submitted', "Requested {$topUp->additional_students_count} additional student slots.", $topUp);

        User::where('role', UserRole::SuperAdmin)->each(
            fn (User $superAdmin) => $superAdmin->notify(new NewSubscriptionTopUpSubmittedNotification($topUp))
        );

        return redirect()->route('students.index')
            ->with('status', "Your request for {$topUp->additional_students_count} additional student slots was submitted and is awaiting Super Admin approval.");
    }
}
