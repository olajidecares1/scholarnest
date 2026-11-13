<?php

use App\Enums\Gender;
use App\Enums\StaffRole;
use App\Enums\TeacherAssignmentType;
use App\Enums\UserRole;
use App\Models\AcademicLevel;
use App\Models\Plan;
use App\Models\School;
use App\Models\SchoolClass;
use App\Models\Staff;
use App\Models\TeacherAssignment;
use App\Models\User;
use App\Services\StaffImport\StaffRoleResolver;
use Illuminate\Http\Client\Request as HttpRequest;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    $this->school = School::factory()->create(['school_code' => 'GRN']);
    $this->admin = User::factory()->create(['role' => UserRole::SchoolAdmin, 'school_id' => $this->school->id]);
    activateSchool($this->school);

    $level = AcademicLevel::factory()->create(['school_id' => $this->school->id]);

    foreach (['Primary 1', 'Primary 2', 'JSS 1', 'JSS 2'] as $class) {
        SchoolClass::factory()->create(['school_id' => $this->school->id, 'academic_level_id' => $level->id, 'name' => $class]);
    }

    config(['services.whatsapp.token' => null, 'services.whatsapp.phone_number_id' => null, 'services.whatsapp.template' => null]);
});

function staffCsv(string $contents, string $name = 'staff-list.csv'): UploadedFile
{
    return UploadedFile::fake()->createWithContent($name, $contents);
}

/**
 * Upload a staff list and return the review token.
 */
function uploadStaffList($test, UploadedFile $file, array $extra = []): string
{
    $response = $test->actingAs($test->admin)->post(route('staff.import.preview'), ['file' => $file, ...$extra]);

    $response->assertSessionHasNoErrors()->assertRedirect();
    preg_match('#/([A-Za-z0-9]{40})$#', $response->headers->get('Location'), $m);

    return $m[1];
}

const STAFF_HEADER = "Full Name,Role,Class,Subjects,Gender,Phone,Email,Date of Birth,Password\n";

test('the staff list offers bulk upload alongside adding one at a time', function () {
    $this->actingAs($this->admin)
        ->get(route('staff.index'))
        ->assertOk()
        ->assertSee('Bulk Upload')
        ->assertSee('Add Staff')
        ->assertSee(route('staff.import.create'), false);
});

test('the upload page explains the roles and lists the school classes', function () {
    $page = $this->actingAs($this->admin)->get(route('staff.import.create'))->assertOk();

    $page->assertSee('Class Teacher')->assertSee('Subject Teacher')->assertSee('JSS 1, JSS 2, Primary 1, Primary 2');

    foreach (['xlsx', 'csv', 'docx', 'pdf'] as $format) {
        $page->assertSee(route('staff.import.template', ['format' => $format]), false);
    }
});

test('the template downloads in every format and reads straight back', function (string $format) {
    $response = $this->actingAs($this->admin)->get(route('staff.import.template', ['format' => $format]));
    $response->assertOk();
    expect($response->headers->get('Content-Disposition'))->toContain('staff-import-template.'.$format);

    $token = uploadStaffList($this, UploadedFile::fake()->createWithContent('staff-import-template.'.$format, $response->getContent()));

    $this->actingAs($this->admin)
        ->get(route('staff.import.review', ['token' => $token]))
        ->assertOk()
        ->assertSee('Ngozi Okafor')
        ->assertSee('Class Teacher')
        ->assertSee('Primary 1')
        ->assertSee('Ready');
})->with(['csv', 'xlsx', 'docx', 'pdf']);

test('the Excel template offers the roles, genders and school classes as drop-downs', function () {
    $content = $this->actingAs($this->admin)->get(route('staff.import.template', ['format' => 'xlsx']))->getContent();

    $path = tempnam(sys_get_temp_dir(), 'xlsx');
    file_put_contents($path, $content);
    $zip = new ZipArchive;
    $zip->open($path);
    $sheet = $zip->getFromName('xl/worksheets/sheet1.xml');
    $zip->close();
    unlink($path);

    expect($sheet)
        ->toContain('<dataValidations count="3">')
        ->toContain('Class Teacher,Subject Teacher,Teacher,Administrative Staff,Management,Support Staff,Other')
        ->toContain('Male,Female')
        ->toContain('JSS 1,JSS 2,Primary 1,Primary 2');
});

test('a staff list is reviewed first, then registered with roles, class teachers, subjects and passwords', function () {
    $csv = STAFF_HEADER
        ."Mrs. Ngozi Okafor,Class Teacher,Primary 1,,F,08031234567,ngozi@example.com,14/03/1988,\n"
        ."Bayo Adeyemi,Subject Teacher,\"JSS 1, JSS 2\",\"Mathematics, Physics\",Male,8051112222,bayo@example.com,,\n"
        ."Tolu Ade,Bursar,,,Female,08061112222,tolu@example.com,,\n"
        ."Musa Bello,Driver,,,M,08071112222,,,\n"
        ."Grace Eze,Principal,,,F,08081112222,grace@example.com,,\n"
        ."Kunle Obi,English Teacher,JSS 1,,M,08091112222,kunle@example.com,,\n";

    $token = uploadStaffList($this, staffCsv($csv));

    // Nothing saved until confirmed.
    expect(Staff::count())->toBe(0);

    $this->actingAs($this->admin)
        ->get(route('staff.import.review', ['token' => $token]))
        ->assertOk()
        ->assertSee('Register 6 Staff Members')
        ->assertSee('Administrative Staff')
        ->assertSee('"Bursar"', false)
        ->assertDontSee('Mrs.');

    $response = $this->actingAs($this->admin)->post(route('staff.import.store', ['token' => $token]));
    $response->assertRedirect(route('staff.import.summary', ['token' => $token]));

    expect(Staff::count())->toBe(6);

    $ngozi = Staff::where('first_name', 'Ngozi')->firstOrFail();
    expect($ngozi->last_name)->toBe('Okafor')
        ->and($ngozi->role)->toBe(StaffRole::Teacher)
        ->and($ngozi->gender)->toBe(Gender::Female)
        ->and($ngozi->date_of_birth->toDateString())->toBe('1988-03-14')
        ->and($ngozi->staff_number)->toStartWith('GRN-STAFF-')
        ->and($ngozi->password)->not->toBeNull()
        ->and($ngozi->is_active)->toBeTrue();

    expect(TeacherAssignment::where('staff_id', $ngozi->id)->where('type', TeacherAssignmentType::ClassTeacher)->value('class_name'))->toBe('Primary 1');

    $bayo = Staff::where('first_name', 'Bayo')->firstOrFail();
    expect($bayo->phone)->toBe('08051112222')
        ->and(TeacherAssignment::where('staff_id', $bayo->id)->where('type', TeacherAssignmentType::SubjectTeacher)->count())->toBe(4);

    // "English Teacher" is a subject teacher of English.
    $kunle = Staff::where('first_name', 'Kunle')->firstOrFail();
    expect(TeacherAssignment::where('staff_id', $kunle->id)->value('subject'))->toBe('English');

    expect(Staff::where('first_name', 'Tolu')->value('role'))->toBe(StaffRole::Administrator)
        ->and(Staff::where('first_name', 'Musa')->value('role'))->toBe(StaffRole::SupportStaff)
        ->and(Staff::where('first_name', 'Grace')->value('role'))->toBe(StaffRole::Management);

    // Every Staff ID is unique and in the school's sequence.
    expect(Staff::pluck('staff_number')->unique()->count())->toBe(6);

    $summary = $this->actingAs($this->admin)->get(route('staff.import.summary', ['token' => $token]));
    $summary->assertOk()
        ->assertSee('Total uploaded')
        ->assertSee('Class teachers')
        ->assertSee('Administrative Staff 1')
        ->assertSee('Send on WhatsApp')
        ->assertSee('https://wa.me/2348031234567', false)
        ->assertSee('once only');
});

test('generated passwords work, are shown once, and are never kept readable', function () {
    $token = uploadStaffList($this, staffCsv(STAFF_HEADER."Ngozi Okafor,Class Teacher,Primary 1,,F,08031234567,,,\n"));
    $this->actingAs($this->admin)->post(route('staff.import.store', ['token' => $token]));

    $first = $this->actingAs($this->admin)->get(route('staff.import.summary', ['token' => $token]))->assertOk();
    preg_match('#<span class="select-all font-mono[^"]*">([^<]+)</span>#', $first->getContent(), $m);
    $password = html_entity_decode($m[1] ?? '');

    expect($password)->not->toBe('')
        ->and(Hash::check($password, Staff::firstOrFail()->password))->toBeTrue();

    // The summary can be opened again; the password cannot.
    $this->actingAs($this->admin)->get(route('staff.import.summary', ['token' => $token]))
        ->assertOk()
        ->assertSee('Ngozi Okafor')
        ->assertDontSee($password);
});

test('a password in the file is used, checked for strength, and encrypted while the list waits', function () {
    $csv = STAFF_HEADER
        ."Ngozi Okafor,Class Teacher,Primary 1,,F,08031234567,,,Blue-Kite-42!\n"
        ."Bayo Adeyemi,Teacher,,,M,08051112222,,,password\n";

    $token = uploadStaffList($this, staffCsv($csv));

    $cached = serialize(Cache::get('staff-import:'.$this->admin->id.':'.hash('sha256', $token)));
    expect($cached)->not->toContain('Blue-Kite-42!');

    $this->actingAs($this->admin)
        ->get(route('staff.import.review', ['token' => $token]))
        ->assertSee('From the file')
        ->assertSee('Password:')
        ->assertDontSee('Blue-Kite-42!');

    $this->actingAs($this->admin)->post(route('staff.import.store', ['token' => $token]));

    expect(Staff::count())->toBe(1)
        ->and(Hash::check('Blue-Kite-42!', Staff::firstOrFail()->password))->toBeTrue();
});

test('every kind of mistake is reported before anything is registered', function () {
    TeacherAssignment::create([
        'school_id' => $this->school->id,
        'staff_id' => Staff::factory()->create(['school_id' => $this->school->id, 'first_name' => 'Ada', 'last_name' => 'Nwosu', 'role' => StaffRole::Teacher])->id,
        'type' => TeacherAssignmentType::ClassTeacher,
        'class_name' => 'Primary 2',
    ]);

    $csv = STAFF_HEADER
        ."No Role,,,,F,08030000001,,,\n"
        ."Odd Role,Astronaut,,,F,08030000002,,,\n"
        ."Wrong Class,Class Teacher,Primery 1,,F,08030000003,,,\n"
        ."No Class,Class Teacher,,,F,08030000004,,,\n"
        ."Two Classes,Class Teacher,\"JSS 1, JSS 2\",,F,08030000005,,,\n"
        ."Taken Class,Class Teacher,Primary 2,,F,08030000006,,,\n"
        ."Bad Email,Teacher,,,M,08030000007,not-an-email,,\n"
        ."First Email,Teacher,,,M,08030000008,same@example.com,,\n"
        ."Second Email,Teacher,,,M,08030000009,same@example.com,,\n"
        ."First Phone,Teacher,,,M,08031111111,,,\n"
        ."Second Phone,Teacher,,,M,+2348031111111,,,\n"
        ."No Gender,Teacher,,,,08030000010,,,\n"
        ."First Class Teacher,Class Teacher,JSS 1,,F,08030000011,,,\n"
        ."Second Class Teacher,Class Teacher,JSS 1,,F,08030000012,,,\n";

    $token = uploadStaffList($this, staffCsv($csv));

    $this->actingAs($this->admin)
        ->get(route('staff.import.review', ['token' => $token]))
        ->assertOk()
        ->assertSee('Role is missing')
        ->assertSee('Role &quot;Astronaut&quot; is not recognised', false)
        ->assertSee('Did you mean Primary 1?')
        ->assertSee('Class is missing. A class teacher needs the class')
        ->assertSee('A class teacher takes one class, but 2 are listed')
        ->assertSee('Primary 2 already has a class teacher: Ada Nwosu')
        ->assertSee('is not a valid email address')
        ->assertSee('Email same@example.com is also used on row 9')
        ->assertSee('Phone number +2348031111111 is also used on row 11')
        ->assertSee('Gender is missing')
        ->assertSee('JSS 1 can only have one class teacher, and row 14 is already its class teacher')
        ->assertSee('Register 3 Staff Members');

    expect(Staff::count())->toBe(1);
});

test('staff already on the list are skipped and never changed', function () {
    $existing = Staff::factory()->create([
        'school_id' => $this->school->id,
        'first_name' => 'Ngozi',
        'last_name' => 'Okafor',
        'email' => 'ngozi@example.com',
        'phone' => '08031234567',
        'role' => StaffRole::Administrator,
    ]);

    $csv = STAFF_HEADER
        ."Someone Else,Teacher,,,F,08099999999,ngozi@example.com,,\n"
        ."Other Person,Teacher,,,M,+234 803 123 4567,,,\n"
        ."Ngozi Okafor,Class Teacher,Primary 1,,F,08000000000,,,\n"
        ."New Person,Teacher,,,M,08011112222,,,\n";

    $token = uploadStaffList($this, staffCsv($csv));

    $this->actingAs($this->admin)
        ->get(route('staff.import.review', ['token' => $token]))
        ->assertSee('Already registered')
        ->assertSee('Email ngozi@example.com is already used by Ngozi Okafor')
        ->assertSee('Register 1 Staff Member');

    $this->actingAs($this->admin)->post(route('staff.import.store', ['token' => $token]));

    expect(Staff::count())->toBe(2)
        ->and($existing->fresh()->role)->toBe(StaffRole::Administrator)
        ->and(TeacherAssignment::count())->toBe(0);

    $this->actingAs($this->admin)->get(route('staff.import.summary', ['token' => $token]))
        ->assertSee('Not registered (3)')
        ->assertSee('The existing record was left unchanged.');
});

test('a row can be corrected or left out on the review page', function () {
    $csv = STAFF_HEADER
        ."Ngozi Okafor,Class Teachr,Primery 1,,F,08031234567,,,\n"
        ."Bayo Adeyemi,Teacher,,,M,08051112222,,,\n";

    $token = uploadStaffList($this, staffCsv($csv));

    $this->actingAs($this->admin)
        ->put(route('staff.import.rows.update', ['token' => $token, 'line' => 2]), [
            'first_name' => 'Ngozi',
            'last_name' => 'Okafor',
            'role' => 'Class Teacher',
            'class' => 'Primary 1',
            'gender' => 'Female',
            'phone' => '08031234567',
        ])
        ->assertRedirect();

    $this->actingAs($this->admin)
        ->delete(route('staff.import.rows.destroy', ['token' => $token, 'line' => 3]))
        ->assertRedirect(route('staff.import.review', ['token' => $token]));

    $this->actingAs($this->admin)
        ->get(route('staff.import.review', ['token' => $token]))
        ->assertSee('Register 1 Staff Member')
        ->assertDontSee('Bayo Adeyemi');

    $this->actingAs($this->admin)->post(route('staff.import.store', ['token' => $token]));

    expect(Staff::pluck('first_name')->all())->toBe(['Ngozi'])
        ->and(TeacherAssignment::value('class_name'))->toBe('Primary 1');
});

test('login details are sent on WhatsApp when it is set up, and failures are reported', function () {
    config([
        'services.whatsapp.token' => 'test-token',
        'services.whatsapp.phone_number_id' => '123456',
        'services.whatsapp.template' => 'staff_account_created',
    ]);

    Http::fake([
        'graph.facebook.com/*' => function (HttpRequest $request) {
            return $request['to'] === '2348051112222'
                ? Http::response(['error' => ['message' => 'Recipient phone number not in allowed list']], 400)
                : Http::response(['messages' => [['id' => 'wamid.1']]], 200);
        },
    ]);

    $csv = STAFF_HEADER
        ."Ngozi Okafor,Class Teacher,Primary 1,,F,08031234567,,,\n"
        ."Bayo Adeyemi,Teacher,,,M,08051112222,,,\n"
        ."Musa Bello,Driver,,,M,,,,\n";

    $token = uploadStaffList($this, staffCsv($csv));
    $this->actingAs($this->admin)->post(route('staff.import.store', ['token' => $token]));

    Http::assertSentCount(2);
    Http::assertSent(function (HttpRequest $request) {
        $values = collect($request['template']['components'][0]['parameters'] ?? [])->pluck('text');

        return str_contains($request->url(), '/123456/messages')
            && $request->hasHeader('Authorization', 'Bearer test-token')
            && $request['to'] === '2348031234567'
            && $request['template']['name'] === 'staff_account_created'
            && $values[0] === 'Ngozi Okafor'
            && str_starts_with($values[2], 'GRN-STAFF-')
            && Hash::check($values[3], Staff::where('first_name', 'Ngozi')->value('password'));
    });

    $this->actingAs($this->admin)->get(route('staff.import.summary', ['token' => $token]))
        ->assertOk()
        ->assertSee('1 / 3')
        ->assertSee('Recipient phone number not in allowed list')
        ->assertSee('No phone');
});

test('teachers beyond the plan limit are not registered', function () {
    Plan::query()->update(['max_teachers' => 1]);

    $csv = STAFF_HEADER
        ."Ngozi Okafor,Class Teacher,Primary 1,,F,08031234567,,,\n"
        ."Bayo Adeyemi,Teacher,,,M,08051112222,,,\n"
        ."Tolu Ade,Bursar,,,F,08061112222,,,\n";

    $token = uploadStaffList($this, staffCsv($csv));

    $this->actingAs($this->admin)->get(route('staff.import.review', ['token' => $token]))
        ->assertSee('1 of the teachers in this list will not be registered');

    $this->actingAs($this->admin)->post(route('staff.import.store', ['token' => $token]));

    expect(Staff::count())->toBe(2)
        ->and(Staff::where('role', StaffRole::Teacher)->count())->toBe(1);
});

test('an upload belongs to the admin who made it', function () {
    $token = uploadStaffList($this, staffCsv(STAFF_HEADER."Ngozi Okafor,Teacher,,,F,08031234567,,,\n"));

    $otherSchool = School::factory()->create(['school_code' => 'OTH']);
    activateSchool($otherSchool);
    $otherAdmin = User::factory()->create(['role' => UserRole::SchoolAdmin, 'school_id' => $otherSchool->id]);

    $this->actingAs($otherAdmin)->post(route('staff.import.store', ['token' => $token]))
        ->assertRedirect(route('staff.import.create'));

    expect(Staff::count())->toBe(0);
});

test('a list with neither a role nor a class column is refused with a clear message', function () {
    $this->actingAs($this->admin)
        ->post(route('staff.import.preview'), ['file' => staffCsv("Full Name,Gender,Phone\nNgozi Okafor,F,08031234567\n")])
        ->assertSessionHasErrors(['file' => 'We could not find a "Role" or "Class" column. Each staff member needs a role, for example Class Teacher, Teacher, Subject Teacher, Administrative Staff, Management or Support Staff; for teachers, a Class column is enough.']);
});

test('everyday job titles are understood as roles', function (string $wording, string $kind) {
    expect(app(StaffRoleResolver::class)->resolve($wording)['kind'])->toBe($kind);
})->with([
    ['Class Teacher', StaffRoleResolver::CLASS_TEACHER],
    ['FORM MASTER', StaffRoleResolver::CLASS_TEACHER],
    ['Class Teacher (Primary 2)', StaffRoleResolver::CLASS_TEACHER],
    ['Primary 2 Class Teacher', StaffRoleResolver::CLASS_TEACHER],
    ['Form Mistress, JSS 1', StaffRoleResolver::CLASS_TEACHER],
    ['Subject Teacher', StaffRoleResolver::SUBJECT_TEACHER],
    ['Mathematics Teacher', StaffRoleResolver::SUBJECT_TEACHER],
    ['Teacher', StaffRoleResolver::TEACHER],
    ['Administrative Staff', 'administrator'],
    ['Bursar', 'administrator'],
    ['Secretary', 'administrator'],
    ['Vice Principal', 'management'],
    ['Head Teacher', 'management'],
    ['Proprietress', 'management'],
    ['Non-Teaching Staff', 'support_staff'],
    ['Security Guard', 'support_staff'],
    ['Other', 'other'],
]);

function importedClassTeacherClass(string $firstName): ?string
{
    return TeacherAssignment::query()
        ->where('staff_id', Staff::where('first_name', $firstName)->value('id'))
        ->where('type', TeacherAssignmentType::ClassTeacher)
        ->value('class_name');
}

test('class teachers are worked out from a list of teachers and their classes, with no role column', function () {
    $csv = "S/N,Full Name,Class,Subjects,Gender,Phone\n"
        ."1,Ngozi Okafor,Primary 1,,F,08031234567\n"
        ."2,Bayo Adeyemi,Pry 2,,M,08051112222\n"
        ."3,Kemi Lawal,JS1,,F,08061112222\n"
        ."4,Chidi Eze,JSS Two,Mathematics,M,08071112222\n"
        ."5,Musa Bello,\"JSS 1, JSS 2\",Physics,M,08081112222\n";

    $token = uploadStaffList($this, staffCsv($csv));

    $this->actingAs($this->admin)
        ->get(route('staff.import.review', ['token' => $token]))
        ->assertOk()
        ->assertSee('Class teachers found in this list')
        ->assertSee('Class teacher of Primary 2')
        ->assertSee('Register 5 Staff Members')
        ->assertDontSee('Needs correcting');

    $this->actingAs($this->admin)->post(route('staff.import.store', ['token' => $token]));

    expect(Staff::count())->toBe(5)
        ->and(Staff::where('role', StaffRole::Teacher)->count())->toBe(5)
        ->and(importedClassTeacherClass('Ngozi'))->toBe('Primary 1')
        ->and(importedClassTeacherClass('Bayo'))->toBe('Primary 2')
        ->and(importedClassTeacherClass('Kemi'))->toBe('JSS 1')
        ->and(importedClassTeacherClass('Chidi'))->toBe('JSS 2')
        ->and(importedClassTeacherClass('Musa'))->toBeNull()
        ->and(TeacherAssignment::where('type', TeacherAssignmentType::ClassTeacher)->count())->toBe(4);

    // A class teacher with subjects teaches them in their class.
    expect(TeacherAssignment::where('staff_id', Staff::where('first_name', 'Chidi')->value('id'))
        ->where('type', TeacherAssignmentType::SubjectTeacher)->get(['class_name', 'subject'])->toArray())
        ->toBe([['class_name' => 'JSS 2', 'subject' => 'Mathematics']]);

    // Several classes and a subject: a subject teacher in each.
    expect(TeacherAssignment::where('staff_id', Staff::where('first_name', 'Musa')->value('id'))->count())->toBe(2);

    $this->actingAs($this->admin)->get(route('staff.import.summary', ['token' => $token]))
        ->assertOk()
        ->assertSee('Class teacher of JSS 1');
});

test('the class teacher can be given in the role, a Class Teacher Of column, or a Yes/No column', function () {
    $csv = "Full Name,Role,Class,Class Teacher,Subjects,Gender,Phone\n"
        ."Ngozi Okafor,Class Teacher (Primary 2),,,,F,08031234567\n"
        ."Bayo Adeyemi,Subject Teacher,\"JSS 1, JSS 2\",JSS 2,Mathematics,M,08051112222\n"
        ."Kemi Lawal,Form Mistress JSS1,,,,F,08061112222\n"
        ."Chidi Eze,Teacher,Primary 1,Yes,,M,08071112222\n"
        ."Tolu Ade,Teacher,Primary 1,No,English,F,08081112222\n";

    $token = uploadStaffList($this, staffCsv($csv));
    $this->actingAs($this->admin)->get(route('staff.import.review', ['token' => $token]))->assertDontSee('Needs correcting');
    $this->actingAs($this->admin)->post(route('staff.import.store', ['token' => $token]));

    expect(Staff::count())->toBe(5)
        ->and(importedClassTeacherClass('Ngozi'))->toBe('Primary 2')
        ->and(importedClassTeacherClass('Bayo'))->toBe('JSS 2')
        ->and(importedClassTeacherClass('Kemi'))->toBe('JSS 1')
        ->and(importedClassTeacherClass('Chidi'))->toBe('Primary 1')
        ->and(importedClassTeacherClass('Tolu'))->toBeNull();

    // The Class Teacher Of column does not stop subjects in other classes.
    expect(TeacherAssignment::where('staff_id', Staff::where('first_name', 'Bayo')->value('id'))
        ->where('type', TeacherAssignmentType::SubjectTeacher)->pluck('class_name')->sort()->values()->all())
        ->toBe(['JSS 1', 'JSS 2']);
});

test('a class that already has a class teacher never holds up a teacher worked out from the list', function () {
    TeacherAssignment::create([
        'school_id' => $this->school->id,
        'staff_id' => Staff::factory()->create(['school_id' => $this->school->id, 'first_name' => 'Ada', 'last_name' => 'Nwosu', 'role' => StaffRole::Teacher])->id,
        'type' => TeacherAssignmentType::ClassTeacher,
        'class_name' => 'Primary 2',
    ]);

    $csv = STAFF_HEADER
        ."Bayo Adeyemi,Teacher,Primary 2,,M,08051112222,,,\n"
        ."Kemi Lawal,Teacher,JSS 1,,F,08061112222,,,\n"
        ."Chidi Eze,Teacher,JSS 1,English,M,08071112222,,,\n"
        ."Tolu Ade,Teacher,JSS 2,,F,08081112222,,,\n"
        ."Grace Obi,Class Teacher,JSS 2,,F,08091112222,,,\n";

    $token = uploadStaffList($this, staffCsv($csv));

    $this->actingAs($this->admin)
        ->get(route('staff.import.review', ['token' => $token]))
        ->assertSee('Primary 2 already has a class teacher (Ada Nwosu')
        ->assertSee('JSS 1 is already given to row 3 as its class teacher.')
        ->assertSee('They are registered as a Subject Teacher of English in JSS 1.')
        ->assertSee('JSS 2 is already given to row 6 as its class teacher.')
        ->assertSee('Register 5 Staff Members')
        ->assertDontSee('Needs correcting');

    $this->actingAs($this->admin)->post(route('staff.import.store', ['token' => $token]));

    expect(Staff::count())->toBe(6)
        ->and(importedClassTeacherClass('Bayo'))->toBeNull()
        ->and(importedClassTeacherClass('Kemi'))->toBe('JSS 1')
        ->and(importedClassTeacherClass('Chidi'))->toBeNull()
        ->and(importedClassTeacherClass('Tolu'))->toBeNull()
        ->and(importedClassTeacherClass('Grace'))->toBe('JSS 2')
        ->and(TeacherAssignment::where('class_name', 'Primary 2')->where('type', TeacherAssignmentType::ClassTeacher)->value('staff_id'))
        ->toBe(Staff::where('first_name', 'Ada')->value('id'));
});

test('teachers already on the staff list are given their class without being changed', function () {
    $bola = Staff::factory()->create(['school_id' => $this->school->id, 'first_name' => 'Bola', 'last_name' => 'Ige', 'phone' => '08020000001', 'role' => StaffRole::Teacher]);
    $chi = Staff::factory()->create(['school_id' => $this->school->id, 'first_name' => 'Chi', 'last_name' => 'Uche', 'phone' => '08020000002', 'role' => StaffRole::Teacher]);
    Staff::factory()->create(['school_id' => $this->school->id, 'first_name' => 'Dayo', 'last_name' => 'Ola', 'phone' => '08020000003', 'role' => StaffRole::Administrator]);
    TeacherAssignment::create(['school_id' => $this->school->id, 'staff_id' => $chi->id, 'type' => TeacherAssignmentType::ClassTeacher, 'class_name' => 'Primary 1']);
    $bolaBefore = $bola->fresh()->only(['first_name', 'last_name', 'phone', 'email', 'password', 'staff_number']);

    $csv = STAFF_HEADER
        ."Bola Ige,Teacher,JSS 1,,M,08099999999,,,\n"
        ."Chi Uche,Teacher,JSS 2,,F,08020000002,,,\n"
        ."Dayo Ola,Teacher,Primary 2,,M,08020000003,,,\n"
        ."New Person,Teacher,Primary 2,,F,08011112222,,,\n";

    $token = uploadStaffList($this, staffCsv($csv));

    $this->actingAs($this->admin)
        ->get(route('staff.import.review', ['token' => $token]))
        ->assertSee('Will be made class teacher of JSS 1')
        ->assertSee('because they are already class teacher of Primary 1')
        ->assertSee('because their role on the staff list is not Teacher')
        ->assertSee('Register 1 Staff Member &amp; Assign 1 Class Teacher', false);

    $this->actingAs($this->admin)->post(route('staff.import.store', ['token' => $token]));

    expect(Staff::count())->toBe(4)
        ->and(importedClassTeacherClass('Bola'))->toBe('JSS 1')
        ->and($bola->fresh()->only(['first_name', 'last_name', 'phone', 'email', 'password', 'staff_number']))->toBe($bolaBefore)
        ->and($chi->classesAsClassTeacher()->all())->toBe(['Primary 1'])
        ->and(importedClassTeacherClass('Dayo'))->toBeNull()
        ->and(importedClassTeacherClass('New'))->toBe('Primary 2');

    $this->actingAs($this->admin)->get(route('staff.import.summary', ['token' => $token]))
        ->assertOk()
        ->assertSee('Existing staff made class teachers (1)')
        ->assertSee('Bola Ige')
        ->assertSee('Not registered (2)');
});

test('a list of existing teachers alone can be used to hand out classes', function () {
    Staff::factory()->create(['school_id' => $this->school->id, 'first_name' => 'Bola', 'last_name' => 'Ige', 'role' => StaffRole::Teacher]);

    $token = uploadStaffList($this, staffCsv("Full Name,Class,Gender\nBola Ige,Primary 2,M\n"));

    $this->actingAs($this->admin)
        ->get(route('staff.import.review', ['token' => $token]))
        ->assertSee('Assign 1 Class Teacher');

    $this->actingAs($this->admin)->post(route('staff.import.store', ['token' => $token]))
        ->assertRedirect(route('staff.import.summary', ['token' => $token]));

    expect(importedClassTeacherClass('Bola'))->toBe('Primary 2');
});

test('an assistant teacher with a class is not made its class teacher', function () {
    $token = uploadStaffList($this, staffCsv(STAFF_HEADER."Bayo Adeyemi,Assistant Teacher,Primary 1,,M,08051112222,,,\n"));
    $this->actingAs($this->admin)->post(route('staff.import.store', ['token' => $token]));

    expect(Staff::count())->toBe(1)
        ->and(importedClassTeacherClass('Bayo'))->toBeNull();
});
