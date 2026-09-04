<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A teacher's own signature, for the results they sign.
 *
 * Every report card has a class teacher's line on it, and until now that line
 * was always blank - the school could store a principal's signature but a
 * teacher had nowhere to put one, so somebody signed every card by hand or
 * nobody signed them at all.
 *
 * It belongs to the member of staff rather than to the school because it is
 * theirs: it follows them across classes and terms, and no two teachers share
 * one. The school still cannot invent it - only the staff member can upload
 * their own.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('staff', function (Blueprint $table) {
            $table->string('signature_path')->nullable()->after('photo_path');
        });
    }

    public function down(): void
    {
        Schema::table('staff', function (Blueprint $table) {
            $table->dropColumn('signature_path');
        });
    }
};
