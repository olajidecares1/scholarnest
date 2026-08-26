<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Platform-wide defaults for newly issued result tokens.
 *
 * These are defaults, not a cage. A school still issues its own tokens and the
 * issuer accepts an explicit override; what these give the Super Admin is a way
 * to move the baseline for everyone - tightening the number of views a token
 * allows, or giving tokens a lifetime - without editing code or touching each
 * school.
 *
 * They deliberately apply at issue rather than at redemption, so changing them
 * never alters a token a parent is already holding.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('settings', function (Blueprint $table) {
            // How many times a new token may be redeemed.
            $table->unsignedSmallInteger('result_token_max_uses')->default(5);

            // How long a new token stays valid. Null means it does not expire
            // on its own, which is the existing behaviour and stays the
            // default - a token that dies on a date nobody was told about
            // looks like a broken system to a parent.
            $table->unsignedSmallInteger('result_token_expiry_days')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('settings', function (Blueprint $table) {
            $table->dropColumn(['result_token_max_uses', 'result_token_expiry_days']);
        });
    }
};
