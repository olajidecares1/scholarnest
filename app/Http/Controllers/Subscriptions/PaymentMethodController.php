<?php

namespace App\Http\Controllers\Subscriptions;

use App\Http\Controllers\Controller;
use App\Http\Requests\Subscriptions\PaymentMethodRequest;
use App\Models\Plan;
use App\Services\SubscriptionWizardService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\View\View;

class PaymentMethodController extends Controller
{
    public function __construct(private readonly SubscriptionWizardService $wizard) {}

    public function create(): View|RedirectResponse
    {
        if (! $this->wizard->hasBillingDetails()) {
            return redirect()->route('subscriptions.billing-details');
        }

        $school = auth()->user()->school;

        return view('subscriptions.payment-method', [
            'plan' => Plan::findOrFail($this->wizard->get('plan_id')),
            'reference' => $this->wizard->reference($school),
        ]);
    }

    public function store(PaymentMethodRequest $request): RedirectResponse
    {
        if (! $this->wizard->hasBillingDetails()) {
            return redirect()->route('subscriptions.billing-details');
        }

        $school = auth()->user()->school;
        $receipt = $request->file('receipt');

        $filename = (string) Str::uuid().'.'.$receipt->getClientOriginalExtension();
        $path = "receipts/{$school->id}/{$filename}";

        if (str_starts_with((string) $receipt->getMimeType(), 'image/')) {
            $stripped = $this->stripExifData($receipt->getRealPath(), $receipt->getMimeType());
            Storage::disk('local')->put($path, $stripped);
        } else {
            $receipt->storeAs('receipts/'.$school->id, $filename, 'local');
        }

        $this->wizard->put([
            'payment_method' => $request->string('payment_method')->value(),
            'receipt_path' => $path,
            'receipt_original_name' => $receipt->getClientOriginalName(),
        ]);

        return redirect()->route('subscriptions.review');
    }

    private function stripExifData(string $path, ?string $mimeType): string
    {
        $image = match ($mimeType) {
            'image/jpeg' => @imagecreatefromjpeg($path),
            'image/png' => @imagecreatefrompng($path),
            default => null,
        };

        if (! $image) {
            return file_get_contents($path);
        }

        ob_start();

        if ($mimeType === 'image/png') {
            imagepng($image);
        } else {
            imagejpeg($image, null, 90);
        }

        $contents = ob_get_clean();
        imagedestroy($image);

        return $contents;
    }
}
