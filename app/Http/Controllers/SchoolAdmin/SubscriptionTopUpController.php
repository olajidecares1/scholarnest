<?php

namespace App\Http\Controllers\SchoolAdmin;

use App\Enums\PlanKey;
use App\Enums\SubscriptionTopUpStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Subscriptions\StoreTopUpRequest;
use App\Models\AuditLog;
use App\Models\SubscriptionTopUp;
use App\Notifications\NewSubscriptionTopUpSubmittedNotification;
use App\Services\PaymentReceiptScreening;
use App\Services\ReceiptUploadService;
use App\Services\StudentLicenceAllocation;
use App\Services\TeamNotifier;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\View\View;

class SubscriptionTopUpController extends Controller
{
    public function __construct(
        private readonly ReceiptUploadService $receiptUploader,
        private readonly StudentLicenceAllocation $licences,
        private readonly PaymentReceiptScreening $screening,
    ) {}

    public function create(): View
    {
        $school = auth()->user()->school;
        $subscription = $school->activeSubscription;

        abort_unless($school->hasPlanAccess(PlanKey::Basic), 403, 'Student slot top-ups are only available on the Basic plan.');

        return view('school-admin.subscriptions.top-up', [
            'plan' => $subscription->plan,
            'subscription' => $subscription,

            // Both read through the service that enforces the limit, so this
            // page cannot show the school a capacity the system would not
            // honour. The history is the audit trail behind the single
            // cumulative figure - not a set of separate allowances.
            'capacity' => $this->licences->summary($school),
            'history' => $history = $this->licences->requestHistory($school),

            // What the school paid at signup: the running total less every
            // approved addition since. Derived from the amounts actually
            // charged rather than from today's price per student, which may
            // have been changed by the Super Admin in the meantime.
            'initialAmount' => (float) $subscription->amount - (float) $history
                ->where('status', SubscriptionTopUpStatus::Approved)
                ->sum('additional_amount'),
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

        // The same screening the registration flow runs, against the top-up's
        // own amount. Without it this route would be the way round it: pay for
        // one slot at registration, then top up with anything at all.
        $screening = $this->screening->screen(
            $request->file('receipt'),
            $additionalAmount,
            (string) $school->name,
        );

        if (! $screening['passed']) {
            return back()->withErrors(['receipt' => $screening['reason']])->withInput();
        }

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

        // One request, one notification, claimed against the top-up itself.
        app(TeamNotifier::class)->once(
            'subscription.top-up.submitted:'.$topUp->uuid,
            new NewSubscriptionTopUpSubmittedNotification($topUp),
        );

        return redirect()->route('students.index')
            ->with('status', "Your request for {$topUp->additional_students_count} additional student slots was submitted and is awaiting EduNest Team approval.");
    }
}
