<?php

namespace App\Services;

use App\Models\SubscriptionInvoice;
use Barryvdh\DomPDF\Facade\Pdf;

/**
 * The printable invoice.
 *
 * One renderer, used by the download route and by the email that attaches it,
 * so a school's copy and AkademicNest's copy are the same document rather than
 * two views that drift apart.
 */
class SubscriptionInvoiceDocument
{
    /**
     * The PDF bytes.
     */
    public function render(SubscriptionInvoice $invoice): string
    {
        return Pdf::loadView('invoices.pdf.subscription-invoice', ['invoice' => $invoice])
            ->setPaper('a4')
            ->output();
    }

    /**
     * What the file is called when it lands in somebody's downloads.
     *
     * The invoice number, so a folder of them sorts and searches sensibly.
     */
    public function filename(SubscriptionInvoice $invoice): string
    {
        return $invoice->number.'.pdf';
    }
}
