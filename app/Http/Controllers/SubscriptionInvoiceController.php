<?php

namespace App\Http\Controllers;

use App\Enums\UserRole;
use App\Models\SubscriptionInvoice;
use App\Services\SubscriptionInvoiceDocument;
use Illuminate\Http\Response;
use Illuminate\View\View;

/**
 * Getting hold of an invoice.
 *
 * Two doors, because two kinds of visitor need one and they cannot be checked
 * the same way:
 *
 *   DOWNLOAD is for somebody signed in, the school's own administrator, or a
 *   Super Admin, and is authorised against the account. A school admin may
 *   fetch their own school's invoices and nobody else's; the check is against
 *   the session's school_id, never an id in the request.
 *
 *   VIEW is the link in the invoice email, and carries a signature instead of
 *   a session. It has to: the person who opens a billing email is often the
 *   bursar or the proprietor, who may have no account here at all, and an
 *   invoice they cannot open is not an invoice they have been sent.
 */
class SubscriptionInvoiceController extends Controller
{
    public function __construct(private readonly SubscriptionInvoiceDocument $document) {}

    /**
     * A school's own billing history.
     *
     * The school comes from the session, so there is no school id in the
     * request to point somewhere else.
     */
    public function index(): View
    {
        $school = auth()->user()->school;

        abort_unless($school !== null, 404);

        return view('school-admin.invoices.index', [
            'invoices' => $school->subscriptionInvoices()
                ->with(['subscription.latestPayment.verifiedBy', 'topUp.verifiedBy'])
                ->latest('issued_at')
                ->get(),
        ]);
    }

    /**
     * For a signed-in School Admin or Super Admin.
     */
    public function download(SubscriptionInvoice $invoice): Response
    {
        $user = auth()->user();

        // The school comes from the ACCOUNT, so a school admin cannot reach
        // another school's invoice by putting its uuid in the address bar.
        abort_unless(
            $user->role === UserRole::SuperAdmin || $user->school_id === $invoice->school_id,
            403,
        );

        return $this->pdf($invoice);
    }

    /**
     * For the link in the invoice email. The `signed` middleware on the route
     * is the access control.
     */
    public function view(SubscriptionInvoice $invoice): Response
    {
        return $this->pdf($invoice);
    }

    private function pdf(SubscriptionInvoice $invoice): Response
    {
        return response($this->document->render($invoice), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="'.$this->document->filename($invoice).'"',

            // An invoice carries a school's billing details. No shared cache
            // should be holding a copy of it.
            'Cache-Control' => 'private, no-store',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
