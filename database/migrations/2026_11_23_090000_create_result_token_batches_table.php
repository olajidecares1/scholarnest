<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * One run of the bulk exam-token generator: the students a School Admin
 * picked, across however many classes, issued together.
 *
 * Kept so the batch can be printed again, whole, by class, or for chosen
 * students, and the printout is exactly the tokens that run created rather
 * than whatever happens to match a class and term later.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('result_token_batches', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->foreignId('generated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('session', 20);
            $table->string('term', 20);
            $table->unsignedInteger('token_count')->default(0);
            $table->timestamps();

            $table->index(['school_id', 'created_at']);
        });

        Schema::table('result_checking_pins', function (Blueprint $table) {
            $table->foreignId('batch_id')->nullable()->after('examination_id')
                ->constrained('result_token_batches')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('result_checking_pins', function (Blueprint $table) {
            $table->dropConstrainedForeignId('batch_id');
        });

        Schema::dropIfExists('result_token_batches');
    }
};
