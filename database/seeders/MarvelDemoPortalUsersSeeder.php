<?php

namespace Database\Seeders;

use App\Enums\FeePaymentMethod;
use App\Enums\Gender;
use App\Enums\SubmissionStatus;
use App\Models\Assignment;
use App\Models\AttendanceRecord;
use App\Models\Examination;
use App\Models\ExaminationScore;
use App\Models\FeeStructure;
use App\Models\Guardian;
use App\Models\Invoice;
use App\Models\School;
use App\Models\Staff;
use App\Models\Student;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * Adds one fully-populated, cross-linked demo user per portal (Staff,
 * Parent/Guardian, Student) to the existing Marvel Int' School data, with
 * fixed known credentials for manual portal testing. Deliberately does NOT
 * touch/reseed the rest of MarvelIntSchoolSeeder's data - that would wipe
 * out real interactions already recorded against that school.
 *
 * Run with: php artisan db:seed --class=MarvelDemoPortalUsersSeeder
 */
class MarvelDemoPortalUsersSeeder extends Seeder
{
    private const CLASS_NAME = 'JSS 1';

    private const PASSWORD = 'password123';

    public function run(): void
    {
        $school = School::where('slug', 'marvel-int-school')->first();

        if (! $school) {
            $this->command?->error('Marvel Int\' School not found (expected slug "marvel-int-school"). Run MarvelIntSchoolSeeder first.');

            return;
        }

        $teacher = $this->ensureDemoStaff($school);
        $student = $this->ensureDemoStudent($school);
        $guardian = $this->ensureDemoGuardian($school, $student);

        $this->seedResults($school, $student);
        $this->seedFees($school, $student);
        $this->seedAttendance($school, $student);
        $this->seedAssignmentSubmission($school, $student);

        $this->printCredentials($school, $teacher, $guardian, $student);
    }

    private function ensureDemoStaff(School $school): Staff
    {
        // The existing MarvelIntSchoolSeeder demo teacher already covers every
        // requirement (staff profile, staff ID, department, assigned classes
        // via timetable, login, Staff Portal access) - reused as-is rather
        // than duplicated.
        return Staff::where('school_id', $school->id)->where('email', 'teacher@edunest.com')->firstOrFail();
    }

    private function ensureDemoStudent(School $school): Student
    {
        return Student::updateOrCreate(
            ['school_id' => $school->id, 'admission_number' => 'DEMO-STU-001'],
            [
                'first_name' => 'Amara',
                'last_name' => 'Johnson',
                'gender' => Gender::Female,
                'date_of_birth' => now()->subYears(11),
                'class_name' => self::CLASS_NAME,
                'guardian_name' => 'Mrs. Blessing Adeyemi',
                'guardian_phone' => '08029876543',
                'guardian_email' => 'parent@edunest.com',
                'address' => '15 Adeola Odeku Street, Victoria Island, Lagos',
                'phone' => '08041234567',
                'email' => 'student@edunest.com',
                'password' => Hash::make(self::PASSWORD),
                'is_active' => true,
                'notes' => 'Demo account for testing the Student Portal.',
            ]
        );
    }

    private function ensureDemoGuardian(School $school, Student $student): Guardian
    {
        $guardian = Guardian::updateOrCreate(
            ['school_id' => $school->id, 'email' => 'parent@edunest.com'],
            [
                'name' => 'Mrs. Blessing Adeyemi',
                'phone' => '08029876543',
                'password' => Hash::make(self::PASSWORD),
                'is_active' => true,
            ]
        );

        $guardian->students()->syncWithoutDetaching([$student->id => ['relationship' => 'Mother']]);

        return $guardian;
    }

    private function seedResults(School $school, Student $student): void
    {
        $examinations = Examination::where('school_id', $school->id)->where('class_name', self::CLASS_NAME)->get();

        foreach ($examinations as $examination) {
            foreach ($examination->subjects as $subject) {
                ExaminationScore::updateOrCreate(
                    ['examination_subject_id' => $subject->id, 'student_id' => $student->id],
                    ['score' => fake()->numberBetween(68, 96)]
                );
            }
        }
    }

    private function seedFees(School $school, Student $student): void
    {
        $structure = FeeStructure::where('school_id', $school->id)->where('name', 'like', 'JSS%')->first();

        if (! $structure || Invoice::where('student_id', $student->id)->where('fee_structure_id', $structure->id)->exists()) {
            return;
        }

        $admin = $school->users()->where('role', 'school_admin')->first();

        $invoice = Invoice::create([
            'school_id' => $school->id,
            'student_id' => $student->id,
            'fee_structure_id' => $structure->id,
            'title' => $structure->name,
            'amount' => $structure->amount,
            'due_date' => now()->addDays(14),
            'notes' => 'Demo invoice for testing Parent Portal fee visibility (Student Portal must never show this).',
        ]);

        // Deliberately a PARTIAL payment, not fully paid or fully unpaid, so
        // the Parent Portal has a genuinely non-zero outstanding balance to
        // demonstrate - the whole point of this being parent-only data.
        $invoice->payments()->create([
            'amount' => round(((float) $structure->amount) * 0.5, 2),
            'paid_at' => now()->subDays(10),
            'method' => FeePaymentMethod::BankTransfer,
            'reference' => 'DEMO-'.Str::upper(Str::random(8)),
            'recorded_by' => $admin?->id,
        ]);
    }

    private function seedAttendance(School $school, Student $student): void
    {
        if (AttendanceRecord::where('student_id', $student->id)->exists()) {
            return;
        }

        $admin = $school->users()->where('role', 'school_admin')->first();
        $rows = [];
        $now = now();

        $cursor = today()->subDays(13);
        while ($cursor->lessThanOrEqualTo(today())) {
            if (! $cursor->isWeekend()) {
                $rows[] = [
                    'uuid' => (string) Str::uuid(),
                    'school_id' => $school->id,
                    'student_id' => $student->id,
                    'class_name' => self::CLASS_NAME,
                    'date' => $cursor->toDateString(),
                    'status' => $cursor->isFriday() ? 'late' : 'present',
                    'marked_by' => $admin?->id,
                    'notes' => null,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }
            $cursor = $cursor->copy()->addDay();
        }

        AttendanceRecord::insert($rows);
    }

    private function seedAssignmentSubmission(School $school, Student $student): void
    {
        $assignment = Assignment::where('school_id', $school->id)->where('class_name', self::CLASS_NAME)->first();

        if (! $assignment) {
            return;
        }

        $assignment->submissions()->updateOrCreate(
            ['student_id' => $student->id],
            [
                'status' => SubmissionStatus::Graded,
                'score' => 18,
                'feedback' => 'Great work, Amara - keep it up!',
            ]
        );
    }

    private function printCredentials(School $school, Staff $teacher, Guardian $guardian, Student $student): void
    {
        $this->command?->info("Demo portal accounts ready for {$school->name}:");

        $this->command?->table(
            ['Portal', 'Login', 'Password', 'URL'],
            [
                ['Staff', $teacher->email, self::PASSWORD, $school->publicUrl('staff.login', ['token' => $school->portal_staff_token])],
                ['Parent / Guardian', $guardian->email, self::PASSWORD, $school->publicUrl('guardian.login', ['token' => $school->portal_guardian_token])],
                ['Student', $student->email.' (or admission no. '.$student->admission_number.')', self::PASSWORD, $school->publicUrl('student.login', ['token' => $school->portal_student_token])],
            ]
        );
    }
}
