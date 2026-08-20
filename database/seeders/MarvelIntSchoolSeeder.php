<?php

namespace Database\Seeders;

use App\Enums\AttendanceStatus;
use App\Enums\BillingCycle;
use App\Enums\CbtTestStatus;
use App\Enums\EmploymentType;
use App\Enums\EventAudience;
use App\Enums\ExamTerm;
use App\Enums\FeePaymentMethod;
use App\Enums\Gender;
use App\Enums\HostelGender;
use App\Enums\StaffRole;
use App\Enums\SubmissionStatus;
use App\Enums\SubscriptionStatus;
use App\Enums\UserRole;
use App\Models\AcademicLevel;
use App\Models\Announcement;
use App\Models\Assignment;
use App\Models\AttendanceRecord;
use App\Models\Book;
use App\Models\BookLoan;
use App\Models\CbtTest;
use App\Models\CbtTestAttempt;
use App\Models\Examination;
use App\Models\ExaminationSubject;
use App\Models\FeeStructure;
use App\Models\Guardian;
use App\Models\HeroSlide;
use App\Models\Hostel;
use App\Models\HostelAllocation;
use App\Models\HostelRoom;
use App\Models\Invoice;
use App\Models\JobPosting;
use App\Models\NavLink;
use App\Models\NewsPost;
use App\Models\Plan;
use App\Models\School;
use App\Models\SchoolClass;
use App\Models\SchoolEvent;
use App\Models\SchoolFacility;
use App\Models\SchoolGalleryImage;
use App\Models\SchoolWebsite;
use App\Models\Staff;
use App\Models\Student;
use App\Models\Subscription;
use App\Models\Testimonial;
use App\Models\TimetableEntry;
use App\Models\TransportAssignment;
use App\Models\TransportRoute;
use App\Models\TransportVehicle;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Populates a single, fully-realised demo school ("Marvel Int' School") so
 * every School Admin module has real, relationally-consistent data to show.
 *
 * Run with: php artisan db:seed --class=MarvelIntSchoolSeeder
 */
class MarvelIntSchoolSeeder extends Seeder
{
    private School $school;

    private User $admin;

    private int $admissionCounter = 1;

    private int $staffCounter = 1;

    /** @var Collection<int, array{name: string, phone: string, email: string, address: string}> */
    private Collection $guardianPool;

    /** @var Collection<int, array{name: string, staff_number: string, email: string}> */
    private Collection $teacherCredentials;

    private const TEACHER_PASSWORD = 'password123';

    private const MALE_NAMES = [
        'Chinedu', 'Tunde', 'Emeka', 'Ayodele', 'Ibrahim', 'Segun', 'Chidi', 'Femi', 'Kelechi', 'Abiodun',
        'Yusuf', 'Obinna', 'Damilare', 'Musa', 'Uche', 'Kayode', 'Nnamdi', 'Bashir', 'Olumide', 'Chukwuemeka',
        'Adewale', 'Ikenna', 'Sulaimon', 'Godwin', 'Victor',
    ];

    private const FEMALE_NAMES = [
        'Amaka', 'Ngozi', 'Funmilayo', 'Aisha', 'Chiamaka', 'Bisi', 'Halima', 'Adaeze', 'Yetunde', 'Blessing',
        'Zainab', 'Ifeoma', 'Omotayo', 'Chidinma', 'Fatima', 'Temitope', 'Nkechi', 'Rahmat', 'Adaobi', 'Folake',
        'Grace', 'Hauwa', 'Chioma', 'Simisola', 'Precious',
    ];

    private const LAST_NAMES = [
        'Obi', 'Bello', 'Okafor', 'Adeyemi', 'Eze', 'Yusuf', 'Okonkwo', 'Balogun', 'Nwosu', 'Abubakar',
        'Chukwu', 'Ogunleye', 'Ibrahim', 'Adekunle', 'Nwachukwu', 'Mohammed', 'Okoro', 'Afolabi', 'Onyeka', 'Suleiman',
        'Danladi', 'Nwafor', 'Adebayo', 'Usman', 'Okeke',
    ];

    public function run(): void
    {
        $this->guardianPool = collect();
        $this->teacherCredentials = collect();

        $this->command?->info("Seeding Marvel Int' School...");

        $this->seedSchool();
        $this->seedAdmin();
        $this->seedSubscription();

        $levels = $this->seedAcademicStructure();
        $this->command?->info('Academic structure ready: '.$levels->flatMap->classes->count().' classes.');

        $students = $this->seedStudents($levels);
        $this->command?->info("Seeded {$students->count()} students.");

        $this->seedStaff();
        $this->command?->info('Staff seeded.');

        $this->seedDemoTeacher();
        $this->seedDemoParent($students);
        $this->command?->info('Demo Teacher and Parent portal accounts seeded.');
        $this->printTeacherCredentials();

        $this->seedAttendance($students);
        $this->command?->info('Attendance seeded.');

        $this->seedExaminations($students);
        $this->command?->info('Examinations & scores seeded.');

        $this->seedAssignments($students);
        $this->command?->info('Assignments seeded.');

        $this->seedFinance($students);
        $this->command?->info('Finance seeded.');

        $this->seedLibrary($students);
        $this->command?->info('Library seeded.');

        $this->seedTransport($students);
        $this->command?->info('Transport seeded.');

        $this->seedHostel($students);
        $this->command?->info('Hostel seeded.');

        $this->seedEvents();
        $this->seedAnnouncements();
        $this->seedWebsite();
        $this->command?->info('Events, announcements, and website seeded.');

        $this->seedNews();
        $this->seedJobPostings();
        $this->command?->info('News and job postings seeded.');

        $this->seedTestimonials();
        $this->seedFacilities();
        $this->command?->info('Testimonials and facilities seeded.');

        $this->command?->info("Done. Marvel Int' School (ID {$this->school->id}) is fully populated.");
    }

    private function seedSchool(): void
    {
        $this->school = School::firstOrCreate(
            ['name' => "Marvel Int' School"],
            [
                'current_session' => '2025/2026',
                'timezone' => 'Africa/Lagos',
                'billing_contact_name' => "Marvel Int' School",
                'billing_email' => 'accounts@marvelschool.test',
                'billing_phone' => '08012345678',
                'billing_address' => '12 Marvel Close, Lekki, Lagos',
                'is_active' => true,
            ]
        );

        $this->school->update([
            'current_session' => '2025/2026',
            'timezone' => 'Africa/Lagos',
            'is_active' => true,
        ]);

        if (! $this->school->logo_path) {
            $this->generatePlaceholderImage('schools/marvel-logo.jpg', 'M', [37, 99, 235], 300, 300);
            $this->school->update(['logo_path' => 'schools/marvel-logo.jpg']);
        }
    }

    private function seedAdmin(): void
    {
        $this->admin = User::firstOrCreate(
            ['school_id' => $this->school->id, 'role' => UserRole::SchoolAdmin],
            [
                'name' => "Marvel Int' School",
                'email' => 'admin@marvelschool.test',
                'username' => 'marveladmin',
                'password' => Hash::make('password'),
                'is_active' => true,
            ]
        );
    }

    private function seedSubscription(): void
    {
        if ($this->school->activeSubscription) {
            return;
        }

        $plan = Plan::where('key', 'standard')->first();

        if (! $plan) {
            return;
        }

        Subscription::create([
            'school_id' => $this->school->id,
            'plan_id' => $plan->id,
            'billing_cycle' => BillingCycle::PerTerm,
            'students_count' => 140,
            'amount' => $plan->price_per_term ?? 150000,
            'currency' => 'NGN',
            'status' => SubscriptionStatus::Active,
            'reference' => 'SEED-'.Str::upper(Str::random(10)),
            'starts_at' => now()->subMonths(2),
            'ends_at' => now()->addMonths(10),
        ]);
    }

    /**
     * @return Collection<int, AcademicLevel>
     */
    private function seedAcademicStructure(): Collection
    {
        SchoolClass::where('school_id', $this->school->id)->delete();
        AcademicLevel::where('school_id', $this->school->id)->delete();

        $structure = [
            'Creche' => ['Creche'],
            'Nursery' => ['Nursery'],
            'Kindergarten' => ['Kindergarten'],
            'Primary' => ['Primary 1', 'Primary 2', 'Primary 3', 'Primary 4', 'Primary 5'],
            'JSS' => ['JSS 1', 'JSS 2', 'JSS 3'],
            'SSS' => ['SSS 1', 'SSS 2', 'SSS 3'],
        ];

        $sortOrder = 0;

        foreach ($structure as $levelName => $classNames) {
            $level = $this->school->academicLevels()->create([
                'name' => $levelName,
                'sort_order' => $sortOrder++,
            ]);

            $classSort = 0;

            foreach ($classNames as $className) {
                $level->classes()->create([
                    'school_id' => $this->school->id,
                    'name' => $className,
                    'sort_order' => $classSort++,
                ]);
            }
        }

        return $this->school->academicLevels()->with('classes')->get();
    }

    /**
     * @param  Collection<int, AcademicLevel>  $levels
     * @return Collection<int, Student>
     */
    private function seedStudents(Collection $levels): Collection
    {
        Student::where('school_id', $this->school->id)->delete();

        $students = collect();

        foreach ($levels as $level) {
            foreach ($level->classes as $class) {
                for ($i = 0; $i < 10; $i++) {
                    $gender = fake()->randomElement(Gender::cases());
                    $firstName = fake()->randomElement($gender === Gender::Male ? self::MALE_NAMES : self::FEMALE_NAMES);
                    $lastName = fake()->randomElement(self::LAST_NAMES);
                    $guardian = $this->guardianFor($lastName);

                    $bloodGroup = fake()->randomElement(['A+', 'A-', 'B+', 'B-', 'O+', 'O-', 'AB+', 'AB-']);
                    $genotype = fake()->randomElement(['AA', 'AA', 'AA', 'AS', 'AC', 'SS']);
                    $medical = fake()->boolean(15)
                        ? fake()->randomElement(['Mild asthma', 'Seasonal allergies', 'Peanut allergy', 'Uses corrective glasses', 'Sickle cell — on routine review'])
                        : 'None reported';

                    $isScholarship = fake()->boolean(8);

                    $admissionNumber = 'MIS-'.now()->year.'-'.str_pad((string) $this->admissionCounter++, 4, '0', STR_PAD_LEFT);

                    $student = Student::create([
                        'school_id' => $this->school->id,
                        'admission_number' => $admissionNumber,
                        'first_name' => $firstName,
                        'last_name' => $lastName,
                        'gender' => $gender,
                        'date_of_birth' => $this->dobForClass($level->name, $class->name),
                        'class_name' => $class->name,
                        'guardian_name' => $guardian['name'],
                        'guardian_phone' => $guardian['phone'],
                        'guardian_email' => $guardian['email'],
                        'address' => $guardian['address'],
                        'admission_date' => now()->subMonths(fake()->numberBetween(2, 36))->subDays(fake()->numberBetween(0, 28)),
                        'is_active' => fake()->boolean(96),
                        'notes' => "Blood Group: {$bloodGroup} · Genotype: {$genotype} · Medical: {$medical}"
                            .($isScholarship ? ' · Scholarship: Fees fully exempted for this session.' : ''),
                    ]);

                    $student->setAttribute('is_scholarship', $isScholarship);
                    $students->push($student);
                }
            }
        }

        return $students;
    }

    /**
     * @return array{name: string, phone: string, email: string, address: string}
     */
    private function guardianFor(string $studentLastName): array
    {
        // ~35% chance of reusing an existing guardian to simulate siblings across classes.
        if ($this->guardianPool->isNotEmpty() && fake()->boolean(35)) {
            return $this->guardianPool->random();
        }

        $title = fake()->randomElement(['Mr.', 'Mrs.', 'Engr.', 'Dr.', 'Barr.', 'Pastor', 'Alhaji', 'Alhaja']);
        $firstName = fake()->randomElement([...self::MALE_NAMES, ...self::FEMALE_NAMES]);
        $name = "{$title} {$firstName} {$studentLastName}";

        $guardian = [
            'name' => $name,
            'phone' => '0'.fake()->randomElement(['803', '805', '806', '807', '810', '813', '816', '703', '706', '901', '902']).fake()->numerify('#######'),
            'email' => Str::slug("{$firstName} {$studentLastName}").fake()->numberBetween(1, 999).'@example.com',
            'address' => fake()->numberBetween(1, 200).' '.fake()->randomElement(['Marina Road', 'Allen Avenue', 'Admiralty Way', 'Adeola Odeku Street', 'Awolowo Road', 'Ikorodu Road', 'Opebi Link Road', 'Ligali Ayorinde Street']).', Lagos',
        ];

        $this->guardianPool->push($guardian);

        return $guardian;
    }

    private function dobForClass(string $levelName, string $className): Carbon
    {
        $age = match (true) {
            str_starts_with($className, 'Primary ') => 5 + (int) substr($className, 8),
            str_starts_with($className, 'JSS ') => 10 + (int) substr($className, 4),
            str_starts_with($className, 'SSS ') => 13 + (int) substr($className, 4),
            $levelName === 'Creche' => fake()->numberBetween(1, 2),
            $levelName === 'Nursery' => fake()->numberBetween(3, 4),
            $levelName === 'Kindergarten' => 5,
            default => fake()->numberBetween(6, 16),
        };

        return now()->subYears($age)->subDays(fake()->numberBetween(0, 364));
    }

    private function seedStaff(): void
    {
        Staff::where('school_id', $this->school->id)->delete();

        $teachingSubjects = [
            'Mathematics', 'English Language', 'Basic Science', 'Social Studies', 'Civic Education',
            'Christian Religious Studies', 'Computer Studies', 'Agricultural Science', 'Home Economics',
            'French', 'Physical & Health Education', 'Fine Arts', 'Business Studies', 'Biology',
            'Chemistry', 'Physics', 'Economics', 'Government', 'Literature-in-English',
        ];

        foreach ($teachingSubjects as $subject) {
            $this->createStaff(StaffRole::Teacher, $subject, fake()->randomElement(['B.Sc', 'B.Ed', 'B.A', 'M.Sc', 'M.Ed', 'PGDE', 'NCE']));
        }

        // A few teachers doubling as class teachers / HODs, noted since there's no dedicated field.
        $this->createStaff(StaffRole::Teacher, 'Mathematics', 'M.Sc', 'Head of Department — Sciences; Class Teacher, SSS 2');
        $this->createStaff(StaffRole::Teacher, 'English Language', 'M.A', 'Head of Department — Languages; Class Teacher, JSS 3');
        $this->createStaff(StaffRole::Teacher, 'Basic Science', 'B.Ed', 'Class Teacher, Primary 4');

        $nonTeaching = [
            ['Principal', StaffRole::Management, "Principal's Office"],
            ['Vice Principal (Academics)', StaffRole::Management, "Principal's Office"],
            ['Vice Principal (Administration)', StaffRole::Management, "Principal's Office"],
            ['Registrar', StaffRole::Administrator, 'Admissions & Records'],
            ['Accountant/Bursar', StaffRole::Administrator, 'Finance'],
            ['Secretary', StaffRole::Administrator, "Principal's Office"],
            ['ICT Administrator', StaffRole::Administrator, 'ICT'],
            ['Librarian', StaffRole::SupportStaff, 'Library'],
            ['Laboratory Attendant', StaffRole::SupportStaff, 'Science Laboratory'],
            ['Receptionist', StaffRole::Administrator, 'Front Desk'],
            ['School Nurse', StaffRole::SupportStaff, 'Clinic'],
            ['Storekeeper', StaffRole::SupportStaff, 'Stores'],
        ];

        foreach ($nonTeaching as [$title, $role, $department]) {
            $this->createStaff($role, $department, fake()->randomElement(['SSCE', 'OND', 'HND', 'B.Sc']), $title);
        }

        foreach (range(1, 3) as $i) {
            $this->createStaff(StaffRole::SupportStaff, 'Security', 'SSCE', 'Security Personnel');
        }
        foreach (range(1, 2) as $i) {
            $this->createStaff(StaffRole::SupportStaff, 'Transport', 'SSCE', 'Driver');
        }
        foreach (range(1, 4) as $i) {
            $this->createStaff(StaffRole::SupportStaff, 'Facilities', 'SSCE', 'Cleaner');
        }
        foreach (range(1, 2) as $i) {
            $this->createStaff(StaffRole::SupportStaff, 'Facilities', 'SSCE', 'Gardener');
        }
        foreach (range(1, 2) as $i) {
            $this->createStaff(StaffRole::SupportStaff, 'Facilities', 'SSCE', 'Maintenance Officer');
        }

        // Part-time staff.
        $this->createStaff(StaffRole::Teacher, 'Music', 'NCE', 'Part-time · Works Mondays and Wednesdays');
        $this->createStaff(StaffRole::Teacher, 'French', 'B.A', 'Part-time · Works Tuesdays and Thursdays');
        $this->createStaff(StaffRole::SupportStaff, 'Facilities', 'SSCE', 'Part-time Cleaner · Works Saturdays');
    }

    private function createStaff(StaffRole $role, string $department, string $qualification, ?string $titleNote = null): void
    {
        $gender = fake()->randomElement(Gender::cases());
        $firstName = fake()->randomElement($gender === Gender::Male ? self::MALE_NAMES : self::FEMALE_NAMES);
        $lastName = fake()->randomElement(self::LAST_NAMES);
        $staffNumber = 'STF-'.str_pad((string) $this->staffCounter++, 4, '0', STR_PAD_LEFT);

        Staff::create([
            'school_id' => $this->school->id,
            'staff_number' => $staffNumber,
            'first_name' => $firstName,
            'last_name' => $lastName,
            'gender' => $gender,
            'date_of_birth' => now()->subYears(fake()->numberBetween(24, 58))->subDays(fake()->numberBetween(0, 364)),
            'role' => $role,
            'department' => $titleNote ? "{$titleNote} ({$department})" : $department,
            'qualification' => $qualification,
            'employment_date' => now()->subYears(fake()->numberBetween(0, 12))->subMonths(fake()->numberBetween(0, 11)),
            'emergency_contact_name' => fake()->name(),
            'emergency_contact_phone' => '0'.fake()->randomElement(['803', '805', '806', '807', '810']).fake()->numerify('#######'),
            'address' => fake()->numberBetween(1, 150).' '.fake()->randomElement(['Ikorodu Road', 'Agege Motor Road', 'Herbert Macaulay Way', 'Muritala Mohammed Way']).', Lagos',
            'phone' => '0'.fake()->randomElement(['803', '805', '806', '807', '810']).fake()->numerify('#######'),
            'email' => Str::slug("{$firstName}.{$lastName}").'@marvelschool.test',
            'is_active' => fake()->boolean(97),
            'notes' => $titleNote && ! str_contains($titleNote, 'Part-time') ? $titleNote : null,
        ]);
    }

    private function printTeacherCredentials(): void
    {
        $teacher = $this->teacherCredentials->first();

        if (! $teacher) {
            return;
        }

        $this->command?->info("Teacher Portal login — {$teacher['name']} — email: {$teacher['email']} — password: ".self::TEACHER_PASSWORD);
    }

    /**
     * Demo Teacher Portal account with fixed credentials for manual testing,
     * plus a realistic timetable and one CBT test in each workflow status
     * (Draft, Locked, Published, Archived) so the whole feature is visible
     * without needing to create anything by hand.
     */
    private function seedDemoTeacher(): void
    {
        $teacher = Staff::updateOrCreate(
            ['school_id' => $this->school->id, 'email' => 'teacher@edunest.com'],
            [
                'staff_number' => 'DEMO-TCH-001',
                'first_name' => 'David',
                'last_name' => 'Johnson',
                'gender' => Gender::Male,
                'date_of_birth' => now()->subYears(34),
                'role' => StaffRole::Teacher,
                'department' => 'Mathematics',
                'qualification' => 'B.Sc Mathematics, PGDE',
                'employment_date' => now()->subYears(5),
                'emergency_contact_name' => 'Grace Johnson',
                'emergency_contact_phone' => '08031112222',
                'address' => '15 Adeola Odeku Street, Victoria Island, Lagos',
                'phone' => '08031234567',
                'password' => Hash::make(self::TEACHER_PASSWORD),
                'is_active' => true,
                'notes' => 'Demo account for testing the Teacher Portal.',
            ]
        );

        $this->teacherCredentials->prepend([
            'name' => 'David Johnson (Demo — full timetable + CBT tests)',
            'staff_number' => $teacher->staff_number,
            'email' => $teacher->email,
        ]);

        TimetableEntry::where('staff_id', $teacher->id)->delete();

        $slots = [
            ['day' => 1, 'class' => 'JSS 1', 'start' => '08:00', 'end' => '08:45'],
            ['day' => 1, 'class' => 'JSS 2', 'start' => '09:00', 'end' => '09:45'],
            ['day' => 2, 'class' => 'JSS 1', 'start' => '08:00', 'end' => '08:45'],
            ['day' => 3, 'class' => 'SSS 1', 'start' => '10:00', 'end' => '10:45'],
            ['day' => 4, 'class' => 'JSS 2', 'start' => '11:00', 'end' => '11:45'],
            ['day' => 5, 'class' => 'JSS 1', 'start' => '08:00', 'end' => '08:45'],
        ];

        foreach ($slots as $slot) {
            TimetableEntry::create([
                'school_id' => $this->school->id,
                'class_name' => $slot['class'],
                'day_of_week' => $slot['day'],
                'start_time' => $slot['start'],
                'end_time' => $slot['end'],
                'subject' => 'Mathematics',
                'staff_id' => $teacher->id,
                'room' => 'Room 4',
            ]);
        }

        $this->seedDemoCbtTests($teacher);
    }

    private function seedDemoCbtTests(Staff $teacher): void
    {
        CbtTest::where('staff_id', $teacher->id)->delete();

        $this->createDemoCbtTest($teacher, 'Fractions Quiz', 'JSS 1', CbtTestStatus::Draft);
        $this->createDemoCbtTest($teacher, 'Algebra Basics Test', 'JSS 2', CbtTestStatus::Locked);

        $published = $this->createDemoCbtTest($teacher, 'First Term Mathematics CBT', 'JSS 1', CbtTestStatus::Published, [
            'available_from' => now()->subDays(2),
            'available_until' => now()->addDays(12),
        ]);

        $this->createDemoCbtTest($teacher, 'Second Term Revision Test', 'SSS 1', CbtTestStatus::Archived);

        // A finished attempt on the published test so the teacher/admin have real results to look at.
        $respondent = Student::where('school_id', $this->school->id)->where('class_name', 'JSS 1')->first();

        if ($respondent) {
            $firstQuestion = $published->questions()->orderBy('sort_order')->first();

            $attempt = CbtTestAttempt::create([
                'student_id' => $respondent->id,
                'cbt_test_id' => $published->id,
                'started_at' => now()->subDay(),
                'expires_at' => now()->subDay()->addMinutes($published->duration_minutes),
                'submitted_at' => now()->subDay()->addMinutes(18),
                'total_questions' => $published->questions()->count(),
                'score' => 80,
            ]);

            if ($firstQuestion) {
                $attempt->answers()->create([
                    'cbt_test_question_id' => $firstQuestion->id,
                    'cbt_test_question_option_id' => $firstQuestion->correctOption()?->id,
                    'is_correct' => true,
                ]);
            }
        }
    }

    /**
     * @param  array<string, mixed>  $extra
     */
    private function createDemoCbtTest(Staff $teacher, string $title, string $className, CbtTestStatus $status, array $extra = []): CbtTest
    {
        $test = $teacher->cbtTests()->create([
            'school_id' => $this->school->id,
            'title' => $title,
            'subject' => 'Mathematics',
            'class_name' => $className,
            'session' => '2025/2026',
            'duration_minutes' => 30,
            'pass_mark' => 50,
            'status' => $status,
            ...$extra,
        ]);

        $questions = [
            ['text' => 'What is 7 + 5?', 'options' => ['10', '11', '12', '13'], 'correct' => 2],
            ['text' => 'Simplify: 3/6', 'options' => ['1/3', '1/2', '2/3', '3/4'], 'correct' => 1],
            ['text' => 'What is the value of x in 2x = 10?', 'options' => ['2', '4', '5', '8'], 'correct' => 2],
            ['text' => 'Which of these is a prime number?', 'options' => ['4', '6', '9', '11'], 'correct' => 3],
            ['text' => 'What is 15% of 200?', 'options' => ['20', '25', '30', '35'], 'correct' => 2],
        ];

        foreach ($questions as $i => $q) {
            $question = $test->questions()->create([
                'question_text' => $q['text'],
                'sort_order' => $i,
            ]);

            foreach ($q['options'] as $index => $optionText) {
                $question->options()->create([
                    'label' => ['A', 'B', 'C', 'D'][$index],
                    'option_text' => $optionText,
                    'is_correct' => $index === $q['correct'],
                ]);
            }
        }

        return $test;
    }

    /**
     * Demo Parent Portal account with fixed credentials, linked to one of the
     * seeded students (plus a sibling in a different class if one exists) so
     * the multi-child switcher and every child-scoped page have real data.
     *
     * @param  Collection<int, Student>  $students
     */
    private function seedDemoParent(Collection $students): void
    {
        $primaryChild = $students->firstWhere('class_name', 'JSS 1') ?? $students->first();

        if (! $primaryChild) {
            return;
        }

        $guardian = Guardian::updateOrCreate(
            ['school_id' => $this->school->id, 'email' => 'parent@edunest.com'],
            [
                'name' => 'Mrs. Blessing Adeyemi',
                'phone' => '08029876543',
                'password' => Hash::make('password123'),
                'is_active' => true,
            ]
        );

        $guardian->students()->syncWithoutDetaching([$primaryChild->id => ['relationship' => 'Mother']]);

        $sibling = $students->first(fn (Student $s) => $s->id !== $primaryChild->id && $s->class_name !== $primaryChild->class_name);

        if ($sibling) {
            $guardian->students()->syncWithoutDetaching([$sibling->id => ['relationship' => 'Mother']]);
        }
    }

    /**
     * @param  Collection<int, Student>  $students
     */
    private function seedAttendance(Collection $students): void
    {
        AttendanceRecord::where('school_id', $this->school->id)->delete();

        $schoolDays = collect();
        $cursor = today()->subDays(27);
        while ($cursor->lessThanOrEqualTo(today())) {
            if (! $cursor->isWeekend()) {
                $schoolDays->push($cursor->toDateString());
            }
            $cursor = $cursor->copy()->addDay();
        }

        $rows = [];
        $now = now();

        foreach ($students->where('is_active', true) as $student) {
            foreach ($schoolDays as $date) {
                $status = fake()->randomElement([
                    AttendanceStatus::Present, AttendanceStatus::Present, AttendanceStatus::Present,
                    AttendanceStatus::Present, AttendanceStatus::Present, AttendanceStatus::Present,
                    AttendanceStatus::Present, AttendanceStatus::Late, AttendanceStatus::Absent,
                    AttendanceStatus::Excused,
                ]);

                $rows[] = [
                    'uuid' => (string) Str::uuid(),
                    'school_id' => $this->school->id,
                    'student_id' => $student->id,
                    'class_name' => $student->class_name,
                    'date' => $date,
                    'status' => $status->value,
                    'marked_by' => $this->admin->id,
                    'notes' => null,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }
        }

        foreach (array_chunk($rows, 1000) as $chunk) {
            DB::table('attendance_records')->insert($chunk);
        }
    }

    /**
     * @param  Collection<int, Student>  $students
     */
    private function seedExaminations(Collection $students): void
    {
        $examinable = ['Primary 1', 'Primary 2', 'Primary 3', 'Primary 4', 'Primary 5', 'JSS 1', 'JSS 2', 'JSS 3', 'SSS 1', 'SSS 2', 'SSS 3'];

        $primarySubjects = ['Mathematics', 'English Language', 'Basic Science', 'Social Studies', 'Christian Religious Studies', 'Handwriting'];
        $jssSubjects = ['Mathematics', 'English Language', 'Basic Science', 'Social Studies', 'Civic Education', 'Business Studies', 'French'];
        $sssSubjects = ['Mathematics', 'English Language', 'Biology', 'Chemistry', 'Physics', 'Economics', 'Government'];

        $studentsByClass = $students->where('is_active', true)->groupBy('class_name');

        foreach ($examinable as $className) {
            $subjects = match (true) {
                str_starts_with($className, 'Primary') => $primarySubjects,
                str_starts_with($className, 'JSS') => $jssSubjects,
                default => $sssSubjects,
            };

            $classStudents = $studentsByClass->get($className, collect());

            if ($classStudents->isEmpty()) {
                continue;
            }

            foreach ([ExamTerm::First, ExamTerm::Second] as $term) {
                $examination = Examination::create([
                    'school_id' => $this->school->id,
                    'name' => $term->label().' Examination',
                    'class_name' => $className,
                    'term' => $term,
                    'session' => '2025/2026',
                    'exam_date' => $term === ExamTerm::First ? now()->subMonths(4) : now()->subDays(10),
                ]);

                $subjectRows = [];
                $now = now();

                foreach ($subjects as $subjectName) {
                    $subject = ExaminationSubject::create([
                        'examination_id' => $examination->id,
                        'name' => $subjectName,
                        'max_score' => 100,
                    ]);

                    foreach ($classStudents as $student) {
                        // A slice of the class is deliberately left ungraded to show "needs review" states.
                        if (fake()->boolean(90)) {
                            $subjectRows[] = [
                                'uuid' => (string) Str::uuid(),
                                'examination_subject_id' => $subject->id,
                                'student_id' => $student->id,
                                'score' => fake()->numberBetween(28, 100),
                                'remark' => null,
                                'created_at' => $now,
                                'updated_at' => $now,
                            ];
                        }
                    }
                }

                foreach (array_chunk($subjectRows, 1000) as $chunk) {
                    DB::table('examination_scores')->insert($chunk);
                }
            }
        }
    }

    /**
     * @param  Collection<int, Student>  $students
     */
    private function seedAssignments(Collection $students): void
    {
        $examinable = ['Primary 1', 'Primary 2', 'Primary 3', 'Primary 4', 'Primary 5', 'JSS 1', 'JSS 2', 'JSS 3', 'SSS 1', 'SSS 2', 'SSS 3'];
        $studentsByClass = $students->where('is_active', true)->groupBy('class_name');

        $titles = [
            'Mathematics' => ['Fractions Worksheet', 'Times Tables Practice'],
            'English Language' => ['Essay: My Holiday', 'Comprehension Passage'],
            'Basic Science' => ['Labelled Diagram of a Plant Cell', 'States of Matter Worksheet'],
        ];

        foreach ($examinable as $className) {
            $classStudents = $studentsByClass->get($className, collect());

            if ($classStudents->isEmpty()) {
                continue;
            }

            foreach (array_slice($titles, 0, 2, true) as $subject => $options) {
                $assignment = Assignment::create([
                    'school_id' => $this->school->id,
                    'class_name' => $className,
                    'subject' => $subject,
                    'title' => fake()->randomElement($options),
                    'description' => 'Complete and submit for review before the due date.',
                    'due_date' => now()->addDays(fake()->numberBetween(-10, 10)),
                    'max_score' => 20,
                ]);

                foreach ($classStudents as $student) {
                    $status = fake()->randomElement([
                        SubmissionStatus::Graded, SubmissionStatus::Graded, SubmissionStatus::Submitted,
                        SubmissionStatus::Submitted, SubmissionStatus::Late, SubmissionStatus::NotSubmitted,
                    ]);

                    $assignment->submissions()->create([
                        'student_id' => $student->id,
                        'status' => $status,
                        'score' => $status === SubmissionStatus::Graded ? fake()->numberBetween(10, 20) : null,
                        'feedback' => $status === SubmissionStatus::Graded ? fake()->randomElement(['Well done!', 'Good effort, revise section 2.', 'Excellent work.', 'See me for corrections.']) : null,
                    ]);
                }
            }
        }
    }

    /**
     * @param  Collection<int, Student>  $students
     */
    private function seedFinance(Collection $students): void
    {
        FeeStructure::where('school_id', $this->school->id)->delete();

        $amounts = [
            'Creche' => 45000, 'Nursery' => 50000, 'Kindergarten' => 55000,
            'Primary' => 65000, 'JSS' => 80000, 'SSS' => 95000,
        ];

        $structuresByLevel = [];

        foreach ($amounts as $levelName => $amount) {
            $structuresByLevel[$levelName] = FeeStructure::create([
                'school_id' => $this->school->id,
                'name' => "{$levelName} Tuition — First Term",
                'class_name' => null,
                'amount' => $amount,
                'session' => '2025/2026',
                'term' => 'First Term',
            ]);
        }

        $levelForClass = fn (string $className) => match (true) {
            str_starts_with($className, 'Primary') => 'Primary',
            str_starts_with($className, 'JSS') => 'JSS',
            str_starts_with($className, 'SSS') => 'SSS',
            default => $className,
        };

        foreach ($students as $student) {
            $isScholarship = (bool) $student->getAttribute('is_scholarship');
            $structure = $structuresByLevel[$levelForClass($student->class_name)] ?? null;

            if (! $structure) {
                continue;
            }

            $invoice = Invoice::create([
                'school_id' => $this->school->id,
                'student_id' => $student->id,
                'fee_structure_id' => $structure->id,
                'title' => $structure->name,
                'amount' => $structure->amount,
                'due_date' => now()->addDays(14),
                'notes' => $isScholarship ? 'Scholarship — fees waived.' : null,
            ]);

            if ($isScholarship) {
                $invoice->payments()->create([
                    'amount' => $structure->amount,
                    'paid_at' => $invoice->created_at ?? now(),
                    'method' => FeePaymentMethod::Other,
                    'reference' => 'SCHOLARSHIP-WAIVER',
                    'recorded_by' => $this->admin->id,
                ]);

                continue;
            }

            $outcome = fake()->randomElement(['paid', 'paid', 'partial', 'partial', 'unpaid']);

            if ($outcome === 'paid') {
                $invoice->payments()->create([
                    'amount' => $structure->amount,
                    'paid_at' => now()->subDays(fake()->numberBetween(1, 40)),
                    'method' => fake()->randomElement(FeePaymentMethod::cases()),
                    'reference' => strtoupper(Str::random(10)),
                    'recorded_by' => $this->admin->id,
                ]);
            } elseif ($outcome === 'partial') {
                $partial = round(((float) $structure->amount) * fake()->randomFloat(2, 0.3, 0.7), 2);
                $invoice->payments()->create([
                    'amount' => $partial,
                    'paid_at' => now()->subDays(fake()->numberBetween(1, 40)),
                    'method' => fake()->randomElement(FeePaymentMethod::cases()),
                    'reference' => strtoupper(Str::random(10)),
                    'recorded_by' => $this->admin->id,
                ]);
            }
            // 'unpaid' → no payment rows, invoice stays fully outstanding.
        }
    }

    /**
     * @param  Collection<int, Student>  $students
     */
    private function seedLibrary(Collection $students): void
    {
        Book::where('school_id', $this->school->id)->delete();

        $catalog = [
            ['New General Mathematics', 'M.F. Macrae', 'Mathematics'],
            ['Essential Mathematics', 'AJS Oluwasanmi', 'Mathematics'],
            ['New Oxford English Course', 'Ayo Banjo', 'English Language'],
            ['A Reader\'s Guide to African Literature', 'Various', 'Literature'],
            ['Things Fall Apart', 'Chinua Achebe', 'Literature'],
            ['Basic Science for Junior Secondary', 'STAN', 'Science'],
            ['New School Physics', 'M.W. Anyakoha', 'Science'],
            ['New School Chemistry', 'Osei Yaw Ababio', 'Science'],
            ['Modern Biology', 'Ramalingam', 'Science'],
            ['Countries of the World Atlas', 'Philip\'s', 'Geography'],
            ['History of West Africa', 'J.F.A. Ajayi', 'History'],
            ['Introduction to Economics', 'A. Falodun', 'Economics'],
            ['Civic Education for Schools', 'NERDC', 'Social Studies'],
            ['Computer Studies for Junior Secondary', 'C.O. Enyi', 'Computer Studies'],
            ['Practical Agriculture', 'S.O. Adesina', 'Agricultural Science'],
            ['Great Expectations', 'Charles Dickens', 'Literature'],
            ['The Lion and the Jewel', 'Wole Soyinka', 'Literature'],
            ['Fairy Tales for Early Readers', 'Various', 'Early Years'],
            ['Picture Dictionary', 'Various', 'Early Years'],
            ['Junior Atlas for Nigeria', 'Macmillan', 'Geography'],
        ];

        $books = collect();

        foreach ($catalog as [$title, $author, $category]) {
            $copies = fake()->numberBetween(3, 10);
            $books->push(Book::create([
                'school_id' => $this->school->id,
                'title' => $title,
                'author' => $author,
                'isbn' => fake()->numerify('978-###-###-##-#'),
                'category' => $category,
                'copies_total' => $copies,
                'copies_available' => $copies,
            ]));
        }

        $activeStudents = $students->where('is_active', true)->values();

        foreach (range(1, 40) as $i) {
            $book = $books->random();

            if ($book->copies_available <= 0) {
                continue;
            }

            $student = $activeStudents->random();
            $borrowedAt = now()->subDays(fake()->numberBetween(2, 40));
            $dueAt = $borrowedAt->copy()->addDays(14);

            $outcome = fake()->randomElement(['returned', 'returned', 'active', 'overdue']);

            $loan = BookLoan::create([
                'book_id' => $book->id,
                'student_id' => $student->id,
                'borrowed_at' => $borrowedAt,
                'due_at' => $outcome === 'overdue' ? now()->subDays(fake()->numberBetween(1, 10)) : $dueAt,
                'returned_at' => $outcome === 'returned' ? $borrowedAt->copy()->addDays(fake()->numberBetween(1, 13)) : null,
            ]);

            if ($outcome !== 'returned') {
                $book->decrement('copies_available');
            }
        }
    }

    /**
     * @param  Collection<int, Student>  $students
     */
    private function seedTransport(Collection $students): void
    {
        TransportVehicle::where('school_id', $this->school->id)->delete();

        $vehicles = collect([
            ['Bus 1', 'LND-234-XY', 30, 'Rasheed Adamu'],
            ['Bus 2', 'LND-567-KJ', 25, 'Emmanuel Okoro'],
            ['Hiace 1', 'LND-891-QW', 14, 'Sunday Bassey'],
            ['Hiace 2', 'LND-102-ZP', 14, 'Ibrahim Sani'],
        ])->map(fn ($v) => TransportVehicle::create([
            'school_id' => $this->school->id,
            'name' => $v[0],
            'plate_number' => $v[1],
            'capacity' => $v[2],
            'driver_name' => $v[3],
            'driver_phone' => '0'.fake()->randomElement(['803', '805', '806']).fake()->numerify('#######'),
            'is_active' => true,
        ]));

        $routes = collect([
            ['Lekki–Ajah Route', 12000],
            ['Victoria Island Route', 10000],
            ['Surulere–Yaba Route', 9000],
            ['Ikeja–Agege Route', 9500],
        ])->map(fn ($r, $i) => TransportRoute::create([
            'school_id' => $this->school->id,
            'transport_vehicle_id' => $vehicles[$i]->id,
            'name' => $r[0],
            'description' => 'Daily pickup and drop-off, morning and afternoon runs.',
            'fee' => $r[1],
        ]));

        $eligible = $students->where('is_active', true)->shuffle()->take(40);

        foreach ($eligible as $student) {
            TransportAssignment::create([
                'transport_route_id' => $routes->random()->id,
                'student_id' => $student->id,
                'pickup_point' => fake()->randomElement(['Main Gate Junction', 'Chevron Roundabout', 'Ajah Bus Stop', 'Falomo Bridge', 'Yaba Bus Stop']),
            ]);
        }
    }

    /**
     * @param  Collection<int, Student>  $students
     */
    private function seedHostel(Collection $students): void
    {
        Hostel::where('school_id', $this->school->id)->delete();

        $boysHostel = Hostel::create([
            'school_id' => $this->school->id,
            'name' => "Marvel Boys' Hostel",
            'gender' => HostelGender::Male,
            'warden_name' => 'Mr. Godwin Nwafor',
            'warden_phone' => '08031234567',
        ]);

        $girlsHostel = Hostel::create([
            'school_id' => $this->school->id,
            'name' => "Marvel Girls' Hostel",
            'gender' => HostelGender::Female,
            'warden_name' => 'Mrs. Folake Adebayo',
            'warden_phone' => '08039876543',
        ]);

        $boysRooms = collect(range(1, 6))->map(fn ($n) => HostelRoom::create([
            'hostel_id' => $boysHostel->id,
            'room_number' => "B{$n}",
            'capacity' => 4,
        ]));

        $girlsRooms = collect(range(1, 6))->map(fn ($n) => HostelRoom::create([
            'hostel_id' => $girlsHostel->id,
            'room_number' => "G{$n}",
            'capacity' => 4,
        ]));

        $boardingEligible = $students->where('is_active', true)->filter(
            fn (Student $s) => str_starts_with($s->class_name, 'JSS') || str_starts_with($s->class_name, 'SSS')
        );

        $roomOccupancy = [];

        foreach ($boardingEligible->shuffle()->take(36) as $student) {
            $rooms = $student->gender === Gender::Male ? $boysRooms : $girlsRooms;

            $room = $rooms->first(function ($r) use (&$roomOccupancy) {
                return ($roomOccupancy[$r->id] ?? 0) < $r->capacity;
            });

            if (! $room) {
                continue;
            }

            HostelAllocation::create([
                'hostel_room_id' => $room->id,
                'student_id' => $student->id,
                'allocated_date' => now()->subDays(fake()->numberBetween(10, 90)),
            ]);

            $roomOccupancy[$room->id] = ($roomOccupancy[$room->id] ?? 0) + 1;
        }
    }

    private function seedEvents(): void
    {
        SchoolEvent::where('school_id', $this->school->id)->delete();

        $events = [
            ['First Term Resumption', 'events.index'.'', now()->subMonths(4), EventAudience::Everyone, 'School Assembly Ground'],
            ['Mid-Term Break Begins', null, now()->subMonths(2), EventAudience::Everyone, null],
            ['PTA General Meeting', null, now()->addDays(5), EventAudience::Parents, 'School Hall'],
            ['Inter-House Sports Competition', null, now()->addDays(14), EventAudience::Everyone, 'Sports Complex'],
            ['Staff Development Workshop', null, now()->addDays(21), EventAudience::Staff, 'Conference Room'],
            ['Second Term Examinations Begin', null, now()->addDays(35), EventAudience::Students, null],
            ['Founders\' Day Celebration', null, now()->addDays(50), EventAudience::Everyone, 'School Field'],
        ];

        foreach ($events as [$title, , $date, $audience, $location]) {
            SchoolEvent::create([
                'school_id' => $this->school->id,
                'title' => $title,
                'description' => 'Details will be communicated to all stakeholders ahead of the date.',
                'location' => $location,
                'audience' => $audience,
                'is_all_day' => true,
                'starts_at' => $date,
                'ends_at' => null,
            ]);
        }
    }

    private function seedAnnouncements(): void
    {
        $announcements = [
            ['Resumption Date Confirmed', 'All students are to resume for the Second Term on the scheduled date. School buses will operate normal routes.'],
            ['PTA Meeting Reminder', 'Parents are kindly reminded of the general PTA meeting holding in the school hall. Attendance is highly encouraged.'],
            ['Mid-Term Break Notice', 'The school will observe a one-week mid-term break. Classes resume immediately after.'],
            ['Inter-House Sports', 'Get ready! This term\'s inter-house sports competition promises to be bigger and better. Parents and guardians are welcome.'],
        ];

        foreach ($announcements as [$title, $body]) {
            Announcement::create([
                'sent_by' => $this->admin->id,
                'title' => $title,
                'body' => $body,
                'recipients_count' => fake()->numberBetween(120, 160),
            ]);
        }
    }

    private function seedWebsite(): void
    {
        $website = SchoolWebsite::updateOrCreate(
            ['school_id' => $this->school->id],
            [
                'hero_title' => "Marvel Int' School",
                'hero_subtitle' => 'Nurturing Excellence, Building Character, Inspiring Futures.',
                'hero_secondary_text' => 'Take a Virtual Tour',
                'hero_secondary_url' => '#campus-life',
                'topbar_announcement' => 'Admissions are now open for the 2025/2026 session.',
                'topbar_badge_text' => 'New',
                'topbar_link_text' => 'Parent & Staff Portal',
                'topbar_link_url' => null,
                'cta_text' => 'Apply for Admission',
                'cta_url' => null,
                'whats_happening_title' => "What's Happening",
                'show_whats_happening' => true,
                'about_text' => "Marvel Int' School is a co-educational institution offering quality education from Creche through Senior Secondary School. We combine a strong academic foundation with character development, sports, and the arts to raise well-rounded, future-ready students.",
                'slogan' => 'Learn Today, Lead Tomorrow, Change the World.',
                'slogan_tagline' => 'Excellence | Integrity | Innovation | Compassion',
                'principal_name' => 'Dr. James Anderson',
                'principal_title' => 'Principal',
                'principal_message' => "At Marvel Int' School, we are committed to raising well-rounded individuals who will lead with integrity, knowledge, and compassion in a global community. Every child who walks through our gates is nurtured to discover their potential and pursue it with confidence.",
                'quote_text' => 'Education is the most powerful weapon which you can use to change the world.',
                'quote_author' => 'Nelson Mandela',
                'quote_author_role' => null,
                'campus_video_url' => null,
                'stats' => [
                    ['label' => 'Students', 'value' => $this->school->students()->count().'+'],
                    ['label' => 'Qualified Staff', 'value' => (string) $this->school->staff()->count()],
                    ['label' => 'Clubs & Societies', 'value' => '12+'],
                    ['label' => 'Years of Excellence', 'value' => '15+'],
                ],
                'admissions_intro' => 'We welcome applications from families who share our commitment to academic excellence and character development. Our admissions process is designed to be straightforward and supportive from your first enquiry to resumption day.',
                'admissions_steps' => [
                    ['title' => 'Submit Enquiry', 'description' => 'Reach out via phone, email, or our contact form to request an application pack.'],
                    ['title' => 'Tour & Assessment', 'description' => 'Visit the school for a campus tour and a simple age-appropriate placement assessment.'],
                    ['title' => 'Submit Documents', 'description' => 'Provide the required documents and pay the non-refundable application fee.'],
                    ['title' => 'Offer & Enrolment', 'description' => 'Successful applicants receive an admission offer and complete enrolment before resumption.'],
                ],
                'admissions_requirements' => [
                    'Completed application form',
                    'Birth certificate or age declaration',
                    'Passport photographs (2 copies)',
                    "Previous school's report card (where applicable)",
                    'Immunization record',
                    'Transfer certificate for transfer students',
                ],
                'contact_email' => 'info@marvelschool.test',
                'contact_phone' => '08012345678',
                'contact_address' => '12 Marvel Close, Lekki, Lagos',
                'facebook_url' => 'https://facebook.com/marvelintschool',
                'twitter_url' => 'https://twitter.com/marvelintschool',
                'instagram_url' => 'https://instagram.com/marvelintschool',
                'is_published' => true,
            ]
        );

        SchoolGalleryImage::where('school_id', $this->school->id)->delete();

        $captions = [
            ['Main Assembly Ground', [37, 99, 235]],
            ['Science Laboratory', [22, 163, 74]],
            ['Inter-House Sports Day', [217, 119, 6]],
            ['Library & Resource Centre', [147, 51, 234]],
            ['Graduation Ceremony', [220, 38, 38]],
        ];

        foreach ($captions as $i => [$caption, $rgb]) {
            $path = "gallery/marvel-{$i}.jpg";
            $this->generatePlaceholderImage($path, $caption, $rgb);

            SchoolGalleryImage::create([
                'school_id' => $this->school->id,
                'image_path' => $path,
                'caption' => $caption,
                'sort_order' => $i,
            ]);
        }

        $this->generatePlaceholderImage('website/marvel-hero.jpg', "Marvel Int' School", [17, 26, 53], 900, 400);
        $this->generatePlaceholderImage('website/marvel-principal.jpg', 'Dr. J. Anderson', [30, 58, 138], 300, 300);
        $website->update([
            'hero_image_path' => 'website/marvel-hero.jpg',
            'principal_photo_path' => 'website/marvel-principal.jpg',
        ]);

        $this->seedHeroSlides();
        $this->seedNavLinks();
    }

    private function seedNavLinks(): void
    {
        NavLink::where('school_id', $this->school->id)->delete();

        $links = [
            ['label' => 'Home', 'url' => route('public.school-website', $this->school)],
            ['label' => 'About', 'url' => route('public.school-about.index', $this->school)],
            ['label' => 'Academics', 'url' => route('public.school-website', $this->school).'#academics'],
            ['label' => 'Admissions', 'url' => route('public.school-admissions.index', $this->school)],
            ['label' => 'News', 'url' => route('public.school-news.index', $this->school)],
            ['label' => 'Events', 'url' => route('public.school-events.index', $this->school)],
            ['label' => 'Facilities', 'url' => route('public.school-facilities.index', $this->school)],
            ['label' => 'Gallery', 'url' => route('public.school-gallery.index', $this->school)],
            ['label' => 'Contact', 'url' => route('public.school-contact.index', $this->school)],
        ];

        foreach ($links as $i => $link) {
            NavLink::create([
                'school_id' => $this->school->id,
                'label' => $link['label'],
                'url' => $link['url'],
                'sort_order' => $i,
            ]);
        }
    }

    private function seedHeroSlides(): void
    {
        HeroSlide::where('school_id', $this->school->id)->delete();

        // Generated at a large canvas so the hero carousel stays sharp when
        // stretched to cover a tall, wide hero section (bg-cover).
        $slides = [
            ['Welcome to Marvel Int\' School', [17, 26, 53]],
            ['Nurturing Every Learner', [22, 82, 130]],
            ['Excellence in Academics', [21, 94, 66]],
            ['Building Tomorrow\'s Leaders', [88, 40, 130]],
        ];

        foreach ($slides as $i => [$caption, $rgb]) {
            $path = "hero-slides/marvel-{$i}.jpg";
            $this->generatePlaceholderImage($path, $caption, $rgb, 1600, 800);

            HeroSlide::create([
                'school_id' => $this->school->id,
                'image_path' => $path,
                'sort_order' => $i,
            ]);
        }
    }

    private function seedTestimonials(): void
    {
        Testimonial::where('school_id', $this->school->id)->delete();

        $testimonials = [
            ['name' => 'Mrs. Funmilayo Adeyemi', 'role' => 'Parent of Primary 3 Pupil', 'quote' => "Marvel Int' School has been wonderful for my daughter. Her teachers are patient and genuinely invested in her growth, both academically and socially. I couldn't have asked for a better foundation."],
            ['name' => 'Chinedu Okafor', 'role' => 'SSS 3 Student', 'quote' => "The teachers here don't just teach for exams — they push us to actually understand and think for ourselves. I feel genuinely prepared for university."],
            ['name' => 'Mr. Ibrahim Bello', 'role' => 'Parent of JSS 2 Student', 'quote' => 'From the moment we enrolled, the communication has been excellent. We always know how our son is doing, and the staff are quick to respond to any concern.'],
            ['name' => 'Amaka Nwosu', 'role' => 'Alumna, Class of 2023', 'quote' => 'Marvel gave me more than academics — it gave me confidence, discipline, and lifelong friends. I still think back fondly on my time there.'],
        ];

        foreach ($testimonials as $i => $testimonial) {
            Testimonial::create([
                'school_id' => $this->school->id,
                'name' => $testimonial['name'],
                'role' => $testimonial['role'],
                'quote' => $testimonial['quote'],
                'is_active' => true,
                'sort_order' => $i,
            ]);
        }
    }

    private function seedFacilities(): void
    {
        SchoolFacility::where('school_id', $this->school->id)->delete();

        $facilities = [
            ['name' => 'Science Laboratory', 'category' => 'Academic', 'description' => 'Fully equipped labs for physics, chemistry, and biology practicals.', 'rgb' => [22, 163, 74]],
            ['name' => 'ICT Laboratory', 'category' => 'Academic', 'description' => 'Modern computers and high-speed internet for coding and digital literacy classes.', 'rgb' => [37, 99, 235]],
            ['name' => 'Library & Resource Centre', 'category' => 'Academic', 'description' => 'A quiet, well-stocked space for reading, research, and independent study.', 'rgb' => [147, 51, 234]],
            ['name' => 'Sports Complex', 'category' => 'Sports', 'description' => 'Football field, basketball and tennis courts for inter-house and inter-school competitions.', 'rgb' => [217, 119, 6]],
            ['name' => 'Assembly Hall', 'category' => 'Recreation', 'description' => 'A large hall for assemblies, performances, and school-wide events.', 'rgb' => [220, 38, 38]],
            ['name' => 'Playground', 'category' => 'Recreation', 'description' => 'A safe, supervised outdoor play area for our youngest pupils.', 'rgb' => [8, 145, 178]],
        ];

        foreach ($facilities as $i => $facility) {
            $path = "facilities/marvel-{$i}.jpg";
            $this->generatePlaceholderImage($path, $facility['name'], $facility['rgb']);

            SchoolFacility::create([
                'school_id' => $this->school->id,
                'name' => $facility['name'],
                'category' => $facility['category'],
                'description' => $facility['description'],
                'image_path' => $path,
                'sort_order' => $i,
            ]);
        }
    }

    private function seedNews(): void
    {
        NewsPost::where('school_id', $this->school->id)->delete();

        $posts = [
            ['title' => 'Marvel Students Win National Science Competition', 'category' => 'Achievements', 'days' => 12, 'body' => "Our SSS 2 science team represented Marvel Int' School at the National Science Competition and clinched first place in the Junior Innovators category. The team impressed judges with a working water-filtration prototype built entirely from recycled materials.\n\nWe are incredibly proud of their dedication and the hours of after-school preparation that made this win possible. Congratulations to the team and their supervising teachers."],
            ['title' => 'New Library & Innovation Lab Commissioned', 'category' => 'School News', 'days' => 24, 'body' => "We are delighted to announce the official commissioning of our new Library and Innovation Lab. The expanded space features over 2,000 new titles, a dedicated reading corner for our younger pupils, and a fully equipped computer lab for coding and robotics classes.\n\nThe project was completed ahead of schedule and is already in daily use by students across all levels."],
            ['title' => 'Graduating Class of 2025 Celebrated', 'category' => 'Events', 'days' => 40, 'body' => "Marvel Int' School held its annual graduation ceremony for the SSS 3 class of 2025, celebrating years of hard work, growth, and achievement. Parents, staff, and guests gathered to honour the graduating class, who will be proceeding to universities across Nigeria and abroad.\n\nWe wish our graduates every success as they begin the next chapter of their journey."],
            ['title' => 'Inter-House Sports Competition Date Announced', 'category' => 'Events', 'days' => 3, 'body' => "Get ready! This term's Inter-House Sports Competition promises to be bigger and better, with new events added across all age categories. Parents and guardians are warmly invited to cheer on their houses.\n\nMore details, including the full fixture list, will be shared via the school app and noticeboard closer to the date."],
        ];

        foreach ($posts as $post) {
            NewsPost::create([
                'school_id' => $this->school->id,
                'title' => $post['title'],
                'body' => $post['body'],
                'category' => $post['category'],
                'is_published' => true,
                'published_at' => now()->subDays($post['days']),
            ]);
        }
    }

    private function seedJobPostings(): void
    {
        JobPosting::where('school_id', $this->school->id)->delete();

        $jobs = [
            ['title' => 'Mathematics Teacher', 'department' => 'Academics', 'type' => EmploymentType::FullTime, 'location' => 'Lagos, Nigeria', 'closesInDays' => 30, 'description' => "We are seeking an experienced Mathematics teacher to join our Secondary school department. The successful candidate will teach JSS and SSS classes, prepare students for external examinations, and contribute to curriculum development.\n\nRequirements: B.Sc/B.Ed in Mathematics or related field, minimum 2 years teaching experience, NCE or TRCN registration an advantage."],
            ['title' => 'ICT Instructor', 'department' => 'Academics', 'type' => EmploymentType::FullTime, 'location' => 'Lagos, Nigeria', 'closesInDays' => 21, 'description' => "Marvel Int' School is looking for a passionate ICT Instructor to teach computer studies and coding classes across Primary and Secondary levels, and to support the school's digital infrastructure.\n\nRequirements: B.Sc in Computer Science or related field, strong classroom management skills, experience with educational software an advantage."],
            ['title' => 'School Nurse', 'department' => 'Administration', 'type' => EmploymentType::PartTime, 'location' => 'Lagos, Nigeria', 'closesInDays' => null, 'description' => "We are hiring a part-time School Nurse to manage our school clinic, attend to student and staff health needs, and support our health and safety programmes.\n\nRequirements: RN certification, prior experience in a school or paediatric setting preferred."],
        ];

        foreach ($jobs as $job) {
            JobPosting::create([
                'school_id' => $this->school->id,
                'title' => $job['title'],
                'department' => $job['department'],
                'employment_type' => $job['type'],
                'location' => $job['location'],
                'description' => $job['description'],
                'is_active' => true,
                'posted_at' => now()->subDays(5),
                'closes_at' => $job['closesInDays'] ? now()->addDays($job['closesInDays']) : null,
            ]);
        }
    }

    private function generatePlaceholderImage(string $path, string $label, array $rgb, int $width = 600, int $height = 400): void
    {
        if (! function_exists('imagecreatetruecolor')) {
            return;
        }

        $image = imagecreatetruecolor($width, $height);
        $bg = imagecolorallocate($image, $rgb[0], $rgb[1], $rgb[2]);
        imagefill($image, 0, 0, $bg);

        $white = imagecolorallocate($image, 255, 255, 255);
        $font = 5;
        $textWidth = imagefontwidth($font) * strlen($label);
        $x = max(10, (int) (($width - $textWidth) / 2));
        $y = (int) ($height / 2) - 10;
        imagestring($image, $font, $x, $y, $label, $white);

        ob_start();
        imagejpeg($image, null, 85);
        $contents = ob_get_clean();
        imagedestroy($image);

        Storage::disk('public')->put($path, $contents);
    }
}
