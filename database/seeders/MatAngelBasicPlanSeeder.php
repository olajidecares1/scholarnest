<?php

namespace Database\Seeders;

use App\Enums\ExamTerm;
use App\Enums\Gender;
use App\Enums\TeacherAssignmentType;
use App\Models\AcademicTerm;
use App\Models\Examination;
use App\Models\School;
use App\Models\Student;
use App\Models\SubjectOffering;
use App\Models\TeacherAssignment;
use App\Services\IdentifierGenerator;
use App\Services\StudentLicenceAllocation;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * A full class for MatAngel Private School, ready to have results compiled.
 *
 * Seeds thirty students into whichever class Olajide Samson is already the
 * class teacher of, plus the surrounding records a report card actually reads:
 * term dates, and an examination carrying the subjects that class is offered.
 *
 * Two deliberate choices:
 *
 * The class is read from the teacher's existing ClassTeacher assignment rather
 * than named here. Hard-coding it would let the seeder and the assignment drift
 * apart, and a class of thirty students whose teacher is someone else is worse
 * than no seed data at all.
 *
 * Students are created through StudentLicenceAllocation and IdentifierGenerator
 * - the same services the School Admin screen uses - so the licence count stays
 * honest and the admission numbers are indistinguishable from ones typed in by
 * hand. Inserting rows directly would produce data that looks right and behaves
 * differently from anything the application creates.
 */
class MatAngelBasicPlanSeeder extends Seeder
{
    private const SCHOOL_NAME = 'MatAngel Private School';

    private const TEACHER_EMAIL = 'jidephp@gmail.com';

    /**
     * Shared portal password. Hashed on the way in by the model's cast - the
     * plain value never reaches the database.
     */
    private const PORTAL_PASSWORD = 'MatAngel@2027';

    public function run(): void
    {
        $school = School::where('name', self::SCHOOL_NAME)->first();

        if (! $school) {
            throw new RuntimeException(self::SCHOOL_NAME.' was not found. Seed or register the school first.');
        }

        $assignment = TeacherAssignment::with('staff')
            ->where('school_id', $school->id)
            ->where('type', TeacherAssignmentType::ClassTeacher)
            ->whereHas('staff', fn ($query) => $query->where('email', self::TEACHER_EMAIL))
            ->first();

        if (! $assignment) {
            throw new RuntimeException(
                'No class-teacher assignment found for '.self::TEACHER_EMAIL.' at '.self::SCHOOL_NAME.
                '. Assign them to a class first, so the students land in the class they actually teach.'
            );
        }

        $className = $assignment->class_name;
        $teacher = $assignment->staff;

        $this->command?->info("Class teacher: {$teacher->fullName()} — {$className}");

        $this->seedAcademicTerms($school);
        $created = $this->seedStudents($school, $className);
        $this->seedExamination($school, $className);

        $this->command?->info("Seeded {$created} student(s) into {$className}.");
        $this->command?->info('Portal password for every seeded student: '.self::PORTAL_PASSWORD);
    }

    /**
     * Term dates for the school's current session.
     *
     * A report card reads these twice: to bound the attendance figures it
     * prints, and to work out the "next term begins" date. Without them a card
     * renders with a blank attendance summary and no resumption date, which
     * looks like a bug in the report rather than missing setup.
     */
    private function seedAcademicTerms(School $school): void
    {
        $session = $school->current_session ?: '2026/2027';
        [$startYear] = explode('/', $session);
        $startYear = (int) $startYear;

        $terms = [
            [ExamTerm::First, "{$startYear}-09-14", "{$startYear}-12-18"],
            [ExamTerm::Second, ($startYear + 1).'-01-11', ($startYear + 1).'-04-02'],
            [ExamTerm::Third, ($startYear + 1).'-04-26', ($startYear + 1).'-07-23'],
        ];

        foreach ($terms as [$term, $startsOn, $endsOn]) {
            AcademicTerm::updateOrCreate(
                ['school_id' => $school->id, 'session' => $session, 'term' => $term],
                ['starts_on' => $startsOn, 'ends_on' => $endsOn],
            );
        }
    }

    /**
     * The thirty students.
     *
     * Idempotent by admission-number-free identity: a student is matched on
     * their name within the class, so re-running tops the class up rather than
     * creating a second Chidinma Okonkwo. Re-running is normal - this is test
     * data that gets reset and re-seeded.
     */
    private function seedStudents(School $school, string $className): int
    {
        $licences = app(StudentLicenceAllocation::class);
        $identifiers = app(IdentifierGenerator::class);

        $created = 0;

        foreach ($this->roster() as $index => $row) {
            $exists = Student::where('school_id', $school->id)
                ->where('first_name', $row['first_name'])
                ->where('last_name', $row['last_name'])
                ->where('class_name', $className)
                ->exists();

            if ($exists) {
                continue;
            }

            // withCapacity() takes the lock and re-counts inside it, so the
            // seeder cannot push the school past its licensed allocation any
            // more than the Add Student form can.
            $student = $licences->withCapacity($school, function () use ($school, $className, $row, $identifiers) {
                return $school->students()->create([
                    'admission_number' => $identifiers->nextAdmissionNumber($school, $className),
                    'first_name' => $row['first_name'],
                    'last_name' => $row['last_name'],
                    'gender' => $row['gender'],
                    'date_of_birth' => $row['date_of_birth'],
                    'class_name' => $className,
                    'guardian_name' => $row['guardian_name'],
                    'guardian_phone' => $row['guardian_phone'],
                    'guardian_email' => $row['guardian_email'],
                    'address' => $row['address'],
                    'phone' => $row['phone'],
                    'email' => $row['email'],
                    'blood_group' => $row['blood_group'],
                    'house' => $row['house'],
                    'admission_date' => $row['admission_date'],
                    'is_active' => true,
                    'password' => self::PORTAL_PASSWORD,
                    'must_change_password' => false,
                ]);
            });

            if ($student === null) {
                $this->command?->warn(
                    'Stopped at '.($index + 1).' student(s): '.$licences->limitReachedMessage($school)
                );

                break;
            }

            $created++;
        }

        return $created;
    }

    /**
     * An examination for the current term, carrying the class's own subjects.
     *
     * Scores cannot be entered against a class - they are entered against an
     * examination's subjects - so without this the seeded students have nowhere
     * for a mark to go. The subjects are taken from what the class is actually
     * offered rather than invented, so the paper matches the timetable.
     */
    private function seedExamination(School $school, string $className): void
    {
        $session = $school->current_session ?: '2026/2027';

        $subjects = SubjectOffering::with('subject')
            ->where('school_id', $school->id)
            ->where('class_name', $className)
            ->get()
            ->pluck('subject.name')
            ->filter()
            ->values();

        if ($subjects->isEmpty()) {
            $this->command?->warn("No subjects are offered to {$className}; skipping the examination.");

            return;
        }

        DB::transaction(function () use ($school, $className, $session, $subjects) {
            $examination = Examination::firstOrCreate(
                [
                    'school_id' => $school->id,
                    'class_name' => $className,
                    'session' => $session,
                    'term' => ExamTerm::First,
                ],
                [
                    'name' => 'First Term Examination',
                    'exam_date' => now()->startOfMonth()->toDateString(),
                ],
            );

            foreach ($subjects as $name) {
                $examination->subjects()->firstOrCreate(
                    ['name' => $name],
                    // 100 total, which the score screen splits into a test out
                    // of 40 and an examination out of 60.
                    ['max_score' => 100],
                );
            }

            $this->command?->info(
                "Examination \"{$examination->name}\" ready with {$subjects->count()} subject(s)."
            );
        });
    }

    /**
     * Thirty distinct students.
     *
     * Names, houses, blood groups, guardians and dates of birth all vary, so
     * report cards, class lists and sorting can be judged on data that looks
     * like a real register rather than thirty near-identical rows. Ages sit
     * where an SS 3 cohort's would.
     *
     * Contact details use the reserved example.com domain and are synthetic;
     * nothing here reaches a real inbox or phone.
     *
     * @return list<array<string, mixed>>
     */
    private function roster(): array
    {
        $rows = [
            ['Chidinma', 'Okonkwo', Gender::Female, '2009-03-14', 'B+', 'Awolowo', 'Ngozi Okonkwo'],
            ['Adebayo', 'Ogunlesi', Gender::Male, '2008-11-02', 'O+', 'Azikiwe', 'Folake Ogunlesi'],
            ['Fatima', 'Abubakar', Gender::Female, '2009-07-21', 'A+', 'Bello', 'Hauwa Abubakar'],
            ['Emeka', 'Nwachukwu', Gender::Male, '2009-01-09', 'O-', 'Macaulay', 'Ifeanyi Nwachukwu'],
            ['Aisha', 'Mohammed', Gender::Female, '2008-09-30', 'AB+', 'Awolowo', 'Zainab Mohammed'],
            ['Tunde', 'Bakare', Gender::Male, '2009-05-17', 'B+', 'Azikiwe', 'Yemisi Bakare'],
            ['Blessing', 'Eze', Gender::Female, '2009-02-26', 'O+', 'Bello', 'Chioma Eze'],
            ['Ibrahim', 'Sanusi', Gender::Male, '2008-12-11', 'A-', 'Macaulay', 'Maryam Sanusi'],
            ['Oluwaseun', 'Adeyemi', Gender::Female, '2009-06-08', 'O+', 'Awolowo', 'Bimbo Adeyemi'],
            ['Chukwuemeka', 'Obi', Gender::Male, '2009-04-23', 'B-', 'Azikiwe', 'Nkechi Obi'],
            ['Halima', 'Yusuf', Gender::Female, '2008-10-05', 'A+', 'Bello', 'Amina Yusuf'],
            ['Damilola', 'Adewale', Gender::Male, '2009-08-19', 'O+', 'Macaulay', 'Titilayo Adewale'],
            ['Ngozi', 'Chukwu', Gender::Female, '2009-01-31', 'AB-', 'Awolowo', 'Uchenna Chukwu'],
            ['Yusuf', 'Danladi', Gender::Male, '2008-11-27', 'O+', 'Azikiwe', 'Rakiya Danladi'],
            ['Temitope', 'Ojo', Gender::Female, '2009-03-03', 'B+', 'Bello', 'Kehinde Ojo'],
            ['Chinedu', 'Anyanwu', Gender::Male, '2009-07-14', 'A+', 'Macaulay', 'Adaeze Anyanwu'],
            ['Zainab', 'Aliyu', Gender::Female, '2008-12-22', 'O-', 'Awolowo', 'Fatima Aliyu'],
            ['Olamide', 'Balogun', Gender::Male, '2009-05-06', 'B+', 'Azikiwe', 'Sade Balogun'],
            ['Precious', 'Udoh', Gender::Female, '2009-09-12', 'A+', 'Bello', 'Grace Udoh'],
            ['Abdullahi', 'Garba', Gender::Male, '2008-10-18', 'O+', 'Macaulay', 'Hadiza Garba'],
            ['Funmilayo', 'Akinyemi', Gender::Female, '2009-02-07', 'AB+', 'Awolowo', 'Ronke Akinyemi'],
            ['Kelechi', 'Onyeka', Gender::Male, '2009-06-29', 'B-', 'Azikiwe', 'Chinwe Onyeka'],
            ['Maryam', 'Suleiman', Gender::Female, '2008-11-15', 'O+', 'Bello', 'Aisha Suleiman'],
            ['Babatunde', 'Fashola', Gender::Male, '2009-04-01', 'A+', 'Macaulay', 'Modupe Fashola'],
            ['Amarachi', 'Ibekwe', Gender::Female, '2009-08-25', 'O+', 'Awolowo', 'Obiageli Ibekwe'],
            ['Musa', 'Bello', Gender::Male, '2008-09-19', 'B+', 'Azikiwe', 'Salamatu Bello'],
            ['Adaobi', 'Nnamdi', Gender::Female, '2009-05-28', 'A-', 'Bello', 'Ekaette Nnamdi'],
            ['Segun', 'Oyelaran', Gender::Male, '2009-01-16', 'O+', 'Macaulay', 'Bukola Oyelaran'],
            ['Rukayat', 'Lawal', Gender::Female, '2008-12-04', 'AB+', 'Awolowo', 'Sekinat Lawal'],
            ['Ifeanyi', 'Okafor', Gender::Male, '2009-07-09', 'B+', 'Azikiwe', 'Ijeoma Okafor'],
        ];

        $streets = [
            '14 Bode Thomas Street, Surulere, Lagos',
            '7 Ajose Adeogun Way, Victoria Island, Lagos',
            '22 Isaac John Street, Ikeja GRA, Lagos',
            '3 Oduduwa Crescent, Ikoyi, Lagos',
            '58 Awolowo Road, Ikoyi, Lagos',
            '11 Adeniyi Jones Avenue, Ikeja, Lagos',
        ];

        return collect($rows)->map(function (array $row, int $index) use ($streets): array {
            [$first, $last, $gender, $dob, $blood, $house, $guardian] = $row;
            $slug = strtolower($first.'.'.$last);

            return [
                'first_name' => $first,
                'last_name' => $last,
                'gender' => $gender,
                'date_of_birth' => $dob,
                'blood_group' => $blood,
                'house' => $house,
                'admission_date' => '2021-09-13',
                'address' => $streets[$index % count($streets)],
                'phone' => sprintf('0803%07d', 1000000 + $index),
                'email' => $slug.'@example.com',
                'guardian_name' => $guardian,
                'guardian_phone' => sprintf('0805%07d', 2000000 + $index),
                'guardian_email' => strtolower(str_replace(' ', '.', $guardian)).'@example.com',
            ];
        })->all();
    }
}
