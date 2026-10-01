<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * One row per write the server has accepted with an offline key.
 *
 * A form filled in with no connection is held on the device and sent when the
 * connection returns. Phones drop out half way through a request, two tabs can
 * both notice the connection is back, and Background Sync can fire while a tab
 * is also sending, so the same queued write can arrive more than once. The key
 * travels with it, and this table is how the second arrival is recognised and
 * answered without creating a second record.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('offline_sync_receipts', function (Blueprint $table) {
            $table->id();
            // sha256 of the client key and the route it was sent to.
            $table->string('key_hash', 64)->unique();
            $table->string('status', 16)->default('processing');
            $table->unsignedSmallInteger('response_status')->nullable();
            $table->string('redirect_to', 2048)->nullable();
            $table->string('message', 500)->nullable();
            $table->timestamps();

            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('offline_sync_receipts');
    }
};
