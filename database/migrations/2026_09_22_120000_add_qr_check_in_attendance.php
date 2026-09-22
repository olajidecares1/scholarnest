<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * QR check-in: one poster per school, scanned on the way in and on the way out.
 *
 * Four things have to exist for that to work.
 *
 * 1. The school has to own a poster. `check_in_token` is what the printed QR
 *    encodes, so it is a secret with a rotate button behind it: a poster
 *    photographed and shared in a class group chat is retired by issuing a new
 *    token and printing again.
 *
 * 2. The school has to have a location. A fixed QR that anybody can photograph
 *    is not proof of attendance on its own, so a scan is only accepted from
 *    inside `check_in_radius_metres` of the school's coordinates. Coordinates
 *    are therefore not optional decoration, see the controller that refuses to
 *    switch check-in on without them.
 *
 * 3. The register has to be able to hold a TIME, not just a day. The student
 *    register has always been one row per pupil per day carrying a status;
 *    arrival and departure are added beside it rather than replacing it,
 *    because a school still needs to mark a pupil absent by hand, and because
 *    every existing row, screen and report keeps working untouched.
 *
 * 4. Staff need a register at all. There has never been one.
 *
 * `attendance_scans` is the fifth thing, and the one that makes offline work:
 * every scan is written there with the DEVICE's clock and its own
 * `client_uuid`, so a phone that was out of signal at 7:40am can send the scan
 * at 11am and still be recorded as 7:40am, and so a retried send is recognised
 * as the same scan rather than a second one.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('schools', function (Blueprint $table) {
            $table->boolean('check_in_enabled')->default(false)->after('automatic_grading');

            // 'manual' | 'qr'. The school admin's choice for PUPILS, which is
            // the part of this that not every school wants: a primary school
            // whose pupils carry no phone stays on 'manual' and nothing about
            // its register changes. Staff self check-in follows the master
            // switch above on its own, because a member of staff has a phone.
            $table->string('student_attendance_mode', 20)->default('manual')->after('check_in_enabled');

            $table->string('check_in_token', 64)->nullable()->unique()->after('student_attendance_mode');

            // 7 decimal places is ~1cm, far finer than any phone reports, and
            // the column is what the radius is measured from.
            $table->decimal('latitude', 10, 7)->nullable()->after('check_in_token');
            $table->decimal('longitude', 10, 7)->nullable()->after('latitude');
            $table->unsignedSmallInteger('check_in_radius_metres')->default(150)->after('longitude');
        });

        Schema::table('attendance_records', function (Blueprint $table) {
            $table->dateTime('arrived_at')->nullable()->after('status');
            $table->dateTime('departed_at')->nullable()->after('arrived_at');

            // 'manual' | 'qr'. Which hand wrote the row, so a register showing
            // both can say so, and so a QR arrival is never silently
            // attributed to the teacher who last opened the page.
            $table->string('source', 10)->default('manual')->after('departed_at');
        });

        Schema::create('staff_attendance_records', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->foreignId('staff_id')->constrained()->cascadeOnDelete();
            $table->date('date');
            $table->dateTime('arrived_at')->nullable();
            $table->dateTime('departed_at')->nullable();
            $table->string('status');
            $table->string('source', 10)->default('qr');
            $table->text('notes')->nullable();
            $table->timestamps();

            // One row per person per day, the same shape the student register
            // has, which is what makes a second scan a departure rather than a
            // second arrival.
            $table->unique(['staff_id', 'date']);
            $table->index(['school_id', 'date']);
        });

        Schema::create('attendance_scans', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();

            // Exactly one of these is set. Two nullable columns rather than a
            // polymorphic pair because both sides are real foreign keys this
            // way, and a deleted pupil takes their scans with them.
            $table->foreignId('student_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('staff_id')->nullable()->constrained()->cascadeOnDelete();

            // The device's own id for this scan. A phone that sends the same
            // queued scan twice, because the first reply never arrived, gets
            // the first result back instead of a duplicate row.
            $table->uuid('client_uuid');

            $table->dateTime('scanned_at');
            $table->dateTime('received_at');
            $table->string('kind', 10);
            $table->string('outcome', 20);

            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->unsignedInteger('accuracy_metres')->nullable();
            $table->unsignedInteger('distance_metres')->nullable();

            // True when the scan sat on the device before it could be sent.
            $table->boolean('was_queued')->default(false);

            $table->timestamps();

            $table->unique(['school_id', 'client_uuid']);
            $table->index(['school_id', 'scanned_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('attendance_scans');
        Schema::dropIfExists('staff_attendance_records');

        Schema::table('attendance_records', function (Blueprint $table) {
            $table->dropColumn(['arrived_at', 'departed_at', 'source']);
        });

        Schema::table('schools', function (Blueprint $table) {
            $table->dropColumn([
                'check_in_enabled',
                'student_attendance_mode',
                'check_in_token',
                'latitude',
                'longitude',
                'check_in_radius_metres',
            ]);
        });
    }
};
