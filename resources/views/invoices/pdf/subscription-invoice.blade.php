@php
    $settings = \App\Models\Setting::current();

    // dompdf cannot fetch a URL, enable_remote is off, so the logo is
    // embedded as a data URI. The uploaded one is read from the database copy
    // (see App\Models\BrandingImage): the disk file does not survive a deploy
    // in production, so reading only the disk printed the bundled mark.
    $uploadedLogo = $settings->logo_path ? \App\Models\BrandingImage::contents($settings->logo_path) : null;
    $bundledLogo = public_path('images/logo-mark.png');

    $logo = match (true) {
        $uploadedLogo !== null => 'data:'.\App\Models\BrandingImage::typeFor($settings->logo_path).';base64,'.base64_encode($uploadedLogo),
        is_file($bundledLogo) => 'data:image/png;base64,'.base64_encode(file_get_contents($bundledLogo)),
        default => null,
    };

    $money = fn ($amount) => $invoice->currency.' '.number_format((float) $amount, 2);

    $navy = '#111a35';
    $gold = '#c8a34a';
@endphp
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Invoice {{ $invoice->number }}</title>
    <style>
        @page { margin: 28px 32px; }
        body { font-family: DejaVu Sans, sans-serif; font-size: 11px; color: #1f2937; margin: 0; }
        table { width: 100%; border-collapse: collapse; }
        .muted { color: #6b7280; }
        .right { text-align: right; }
        .head-rule { height: 3px; background: {{ $navy }}; margin: 10px 0 0; }
        .head-rule span { display: inline-block; height: 3px; width: 22%; background: {{ $gold }}; float: right; }
        .title { font-size: 22px; font-weight: bold; color: {{ $navy }}; letter-spacing: 1px; }
        .box { border: 1px solid #e5e7eb; padding: 10px 12px; }
        .label { font-size: 8px; text-transform: uppercase; letter-spacing: .6px; color: #6b7280; }
        .value { font-size: 11px; font-weight: bold; color: #111827; }
        .items th { background: {{ $navy }}; color: #fff; font-size: 9px; text-transform: uppercase; letter-spacing: .5px; padding: 7px 9px; text-align: left; }
        .items td { padding: 9px; border-bottom: 1px solid #e5e7eb; vertical-align: top; }
        .totals td { padding: 5px 9px; }
        .totals .grand td { border-top: 2px solid {{ $navy }}; font-size: 13px; font-weight: bold; color: {{ $navy }}; padding-top: 8px; }
        .status { display: inline-block; padding: 4px 10px; border: 1px solid; font-size: 9px; font-weight: bold; text-transform: uppercase; letter-spacing: .5px; }
        .status-paid { color: #15803d; border-color: #15803d; }
        .status-pending { color: #b45309; border-color: #b45309; }
        .status-rejected { color: #b91c1c; border-color: #b91c1c; }
        .footer { margin-top: 22px; padding-top: 10px; border-top: 1px solid #e5e7eb; font-size: 9px; color: #6b7280; }
    </style>
</head>
<body>
    <table>
        <tr>
            <td style="width: 55%; vertical-align: top;">
                @if ($logo)
                    <img src="{{ $logo }}" style="height: 38px;" alt="">
                @endif
                <div style="margin-top: 6px; font-size: 13px; font-weight: bold; color: {{ $navy }};">
                    {{ $settings->site_name ?: config('app.name') }}
                </div>
                <div class="muted" style="margin-top: 3px; line-height: 1.5;">
                    @if ($settings->support_email)
                        {{ $settings->support_email }}<br>
                    @endif
                    @if ($settings->support_phone)
                        {{ $settings->support_phone }}<br>
                    @endif
                    {{ parse_url(config('app.url'), PHP_URL_HOST) ?: config('app.url') }}
                </div>
            </td>
            <td style="width: 45%; vertical-align: top;" class="right">
                <div class="title">INVOICE</div>
                <div style="margin-top: 8px;">
                    <span class="label">Invoice Number</span><br>
                    <span class="value">{{ $invoice->number }}</span>
                </div>
                <div style="margin-top: 6px;">
                    <span class="label">Invoice Date</span><br>
                    <span class="value">{{ $invoice->issued_at->format('j F Y') }}</span>
                </div>
                <div style="margin-top: 8px;">
                    @php
                        $status = $invoice->paymentStatus();
                        $statusClass = match ($status) {
                            'Paid' => 'status-paid',
                            'Rejected' => 'status-rejected',
                            default => 'status-pending',
                        };
                    @endphp
                    <span class="status {{ $statusClass }}">{{ $status }}</span>
                </div>
            </td>
        </tr>
    </table>

    <div class="head-rule"><span></span></div>

    <table style="margin-top: 18px;">
        <tr>
            <td style="width: 49%; vertical-align: top;">
                <div class="box">
                    <div class="label">Billed To</div>
                    <div class="value" style="margin-top: 4px;">{{ $invoice->billed_to_name }}</div>
                    <div class="muted" style="margin-top: 3px; line-height: 1.5;">
                        @if ($invoice->billed_to_email)
                            {{ $invoice->billed_to_email }}<br>
                        @endif
                        @if ($invoice->billed_to_phone)
                            {{ $invoice->billed_to_phone }}
                        @endif
                    </div>
                </div>
            </td>
            <td style="width: 2%;"></td>
            <td style="width: 49%; vertical-align: top;">
                <div class="box">
                    <div class="label">Subscription</div>
                    <div class="value" style="margin-top: 4px;">{{ $invoice->plan_name }}</div>
                    <div class="muted" style="margin-top: 3px; line-height: 1.5;">
                        @if ($invoice->billing_cycle)
                            Billing cycle: {{ $invoice->billing_cycle }}<br>
                        @endif
                        @if ($invoice->payment_reference)
                            Reference: {{ $invoice->payment_reference }}
                        @endif
                    </div>
                </div>
            </td>
        </tr>
    </table>

    <table class="items" style="margin-top: 18px;">
        <thead>
            <tr>
                <th>Description</th>
                <th class="right" style="width: 90px;">Licences</th>
                <th class="right" style="width: 110px;">Unit Price</th>
                <th class="right" style="width: 120px;">Amount</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td>{{ $invoice->description }}</td>
                <td class="right">{{ $invoice->licences ?? 'N/A' }}</td>
                <td class="right">{{ $invoice->unit_price !== null ? $money($invoice->unit_price) : 'N/A' }}</td>
                <td class="right">{{ $money($invoice->subtotal) }}</td>
            </tr>
        </tbody>
    </table>

    <table style="margin-top: 12px;">
        <tr>
            <td style="width: 58%;"></td>
            <td style="width: 42%;">
                <table class="totals">
                    <tr>
                        <td class="muted">Subtotal</td>
                        <td class="right">{{ $money($invoice->subtotal) }}</td>
                    </tr>
                    @if ((float) $invoice->discount > 0)
                        <tr>
                            <td class="muted">Discount</td>
                            <td class="right">- {{ $money($invoice->discount) }}</td>
                        </tr>
                    @endif
                    @if ((float) $invoice->fees > 0)
                        <tr>
                            <td class="muted">Fees</td>
                            <td class="right">{{ $money($invoice->fees) }}</td>
                        </tr>
                    @endif
                    <tr class="grand">
                        <td>{{ $invoice->isPaid() ? 'Total Paid' : 'Total Payable' }}</td>
                        <td class="right">{{ $money($invoice->total) }}</td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>

    @if (! $invoice->isPaid())
        <div class="box" style="margin-top: 18px; border-color: {{ $gold }};">
            <strong>Awaiting approval.</strong>
            This subscription becomes active once a {{ $settings->site_name ?: config('app.name') }}
            administrator has reviewed and approved the payment. You will be emailed as soon as that happens.
        </div>
    @elseif ($invoice->approvedAt())
        <div class="box" style="margin-top: 18px;">
            <span class="label">Approved</span>
            <div style="margin-top: 3px;">
                {{ $invoice->approvedAt()->format('j F Y') }}
                @if ($invoice->approvedBy())
                    by {{ $invoice->approvedBy()->name }}
                @endif
            </div>
        </div>
    @endif

    <div class="footer">
        {{ $settings->site_name ?: config('app.name') }} | Invoice {{ $invoice->number }} |
        Generated {{ now()->format('j F Y') }}.
        This document is a record of a subscription to the {{ $settings->site_name ?: config('app.name') }} school management platform.
    </div>
</body>
</html>
