<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * How schools may pay, and what they are told to pay into.
 *
 * The bank details were written into the Blade template: GTBank, an account
 * number, a sort code. Changing where AkademicNest is paid meant editing a view and
 * deploying, and until somebody did, every school on the subscription page was
 * being asked to transfer money to whatever the template said.
 *
 * The rows seeded here carry those exact values, so nothing changes for a
 * school mid-subscription on the day this ships. They are placeholders and
 * they look it, the Payment Settings page says so until they are replaced.
 *
 * `details` is JSON because a bank transfer needs a bank name, an account name
 * and a number, while a card gateway needs none of those and will want keys
 * instead. A column per field would be a column per field per method.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payment_methods', function (Blueprint $table) {
            $table->id();
            $table->uuid()->unique();

            // Matches App\Enums\PaymentMethod, which is what the wizard stores
            // against a payment. The enum stays the vocabulary; this table
            // holds what the AkademicNest Team can change about each one.
            $table->string('key')->unique();

            $table->string('label');
            $table->string('description')->nullable();

            // The switch in the brief. A disabled method is refused on the
            // server, not merely dropped from the page, see
            // App\Services\AvailablePaymentMethods.
            $table->boolean('is_enabled')->default(false);

            // Whether a human checks a receipt afterwards. Bank transfer does;
            // a card gateway would not. Kept as a property of the method so
            // the receipt step follows the method rather than being assumed.
            $table->boolean('requires_receipt')->default(true);

            $table->text('instructions')->nullable();

            /** @var array<string, string> */
            $table->json('details')->nullable();

            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });

        DB::table('payment_methods')->insert([
            [
                'uuid' => (string) Str::uuid(),
                'key' => 'bank_transfer',
                'label' => 'Bank Transfer',
                'description' => 'Pay into the account below, then upload your receipt',
                'is_enabled' => true,
                'requires_receipt' => true,
                'instructions' => 'Make payment to the account below, then upload your receipt. '
                    .'Use the reference code shown when making payment. '
                    .'Your subscription will be activated after verification.',
                'details' => json_encode([
                    'bank_name' => 'GTBank',
                    'account_name' => 'AkademicNest Technologies Ltd',
                    'account_number' => '0123456789',
                    'sort_code' => '058152052',
                ]),
                'sort_order' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'uuid' => (string) Str::uuid(),
                'key' => 'paystack',
                'label' => 'Paystack (Card, USSD, Transfer)',
                'description' => 'Pay instantly online',

                // Off, because it is not integrated. The page used to print
                // "Soon" beside a radio nobody could choose; now the same fact
                // is a row the AkademicNest Team can see and turn on when it works.
                'is_enabled' => false,
                'requires_receipt' => false,
                'instructions' => null,
                'details' => null,
                'sort_order' => 2,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_methods');
    }
};
