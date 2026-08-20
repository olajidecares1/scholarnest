<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('teacher_assignments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->foreignId('staff_id')->constrained('staff')->cascadeOnDelete();
            $table->string('type');
            $table->string('class_name');
            $table->string('subject')->nullable();
            $table->timestamps();

            $table->index(['school_id', 'staff_id']);
            $table->index(['school_id', 'class_name']);
        });

        // Carry forward any existing single-class assignment made before this
        // table existed, so no school loses a Class Teacher assignment they
        // already relied on.
        $now = now();
        DB::table('staff')
            ->whereNotNull('class_teacher_of')
            ->get(['id', 'school_id', 'class_teacher_of'])
            ->each(function ($staff) use ($now) {
                DB::table('teacher_assignments')->insert([
                    'school_id' => $staff->school_id,
                    'staff_id' => $staff->id,
                    'type' => 'class_teacher',
                    'class_name' => $staff->class_teacher_of,
                    'subject' => null,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            });

        Schema::table('staff', function (Blueprint $table) {
            $table->dropUnique(['school_id', 'class_teacher_of']);
            $table->dropColumn('class_teacher_of');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('staff', function (Blueprint $table) {
            $table->string('class_teacher_of')->nullable()->after('department');
            $table->unique(['school_id', 'class_teacher_of']);
        });

        DB::table('teacher_assignments')
            ->where('type', 'class_teacher')
            ->orderBy('id')
            ->get(['staff_id', 'class_name'])
            ->each(function ($assignment) {
                DB::table('staff')->where('id', $assignment->staff_id)->update(['class_teacher_of' => $assignment->class_name]);
            });

        Schema::dropIfExists('teacher_assignments');
    }
};
