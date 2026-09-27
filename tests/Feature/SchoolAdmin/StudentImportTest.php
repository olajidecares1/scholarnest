<?php

use App\Enums\Gender;
use App\Enums\PlanKey;
use App\Enums\SubscriptionStatus;
use App\Enums\UserRole;
use App\Models\AcademicLevel;
use App\Models\Plan;
use App\Models\School;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\Subscription;
use App\Models\User;
use App\Services\StudentImport\StudentImportParser;
use App\Services\StudentImport\StudentImportReader;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use PhpOffice\PhpWord\IOFactory as WordIO;
use PhpOffice\PhpWord\PhpWord;

beforeEach(function () {
    $this->school = School::factory()->create(['auto_generate_admission_numbers' => false]);
    $this->admin = User::factory()->create(['role' => UserRole::SchoolAdmin, 'school_id' => $this->school->id]);
    activateSchool($this->school);

    $level = AcademicLevel::factory()->create(['school_id' => $this->school->id]);
    SchoolClass::factory()->create(['school_id' => $this->school->id, 'academic_level_id' => $level->id, 'name' => 'Primary 3']);
    SchoolClass::factory()->create(['school_id' => $this->school->id, 'academic_level_id' => $level->id, 'name' => 'JSS 1']);
});

function importCsv(string $contents, string $name = 'class-list.csv'): UploadedFile
{
    return UploadedFile::fake()->createWithContent($name, $contents);
}

/**
 * Upload, then follow to the review page. Returns the review token.
 */
function uploadList($test, UploadedFile $file, string $class = 'Primary 3', array $extra = []): string
{
    $response = $test->actingAs($test->admin)->post(route('students.import.preview'), [
        'class_name' => $class,
        'file' => $file,
        ...$extra,
    ]);

    $response->assertSessionHasNoErrors()->assertRedirect();
    preg_match('#/([A-Za-z0-9]{40})$#', $response->headers->get('Location'), $m);

    return $m[1];
}

test('the students list offers bulk upload alongside adding one at a time', function () {
    $this->actingAs($this->admin)
        ->get(route('students.index'))
        ->assertOk()
        ->assertSee('Bulk Upload')
        ->assertSee('Add Student')
        ->assertSee(route('students.import.create'), false);
});

test('the upload page lists the school classes and offers a template', function () {
    $this->actingAs($this->admin)
        ->get(route('students.import.create'))
        ->assertOk()
        ->assertSee('Primary 3')
        ->assertSee('JSS 1')
        ->assertSee('Download Template');

    $template = $this->actingAs($this->admin)->get(route('students.import.template'));
    $template->assertOk();
    expect($template->streamedContent())->toContain('"First Name","Last Name",Gender');
});

test('a class must be selected when importing', function () {
    $this->actingAs($this->admin)
        ->post(route('students.import.preview'), [
            'file' => importCsv("First Name,Last Name,Gender,Admission Number\nAda,Obi,F,A1\n"),
        ])
        ->assertSessionHasErrors('class_name');

    $this->actingAs($this->admin)
        ->post(route('students.import.preview'), [
            'class_name' => 'A Class That Does Not Exist',
            'file' => importCsv("First Name,Last Name,Gender,Admission Number\nAda,Obi,F,A1\n"),
        ])
        ->assertSessionHasErrors('class_name');

    expect(Student::count())->toBe(0);
});

test('a CSV list is previewed first, then imported into the selected class', function () {
    $csv = "S/N,Admission No,Surname,First Name,Sex,Date of Birth,Parent Name,Parent Phone,Class\n"
        ."1,ADM-100,OKAFOR,CHINEDU,M,14/03/2016,Ngozi Okafor,8012345678,Basic 3\n"
        ."2,ADM-101,Bello,Aisha,Female,2016-07-01,Musa Bello,08098765432,Basic 3\n";

    $token = uploadList($this, importCsv($csv));

    // Nothing saved until confirmed.
    expect(Student::count())->toBe(0);

    $this->actingAs($this->admin)
        ->get(route('students.import.review', ['token' => $token]))
        ->assertOk()
        ->assertSee('Chinedu')
        ->assertSee('Aisha')
        ->assertSee('Import 2 Students');

    $this->actingAs($this->admin)
        ->post(route('students.import.store', ['token' => $token]))
        ->assertRedirect(route('students.index', ['class' => 'Primary 3']))
        ->assertSessionHas('status');

    $chinedu = Student::where('admission_number', 'ADM-100')->firstOrFail();
    expect($chinedu->school_id)->toBe($this->school->id)
        ->and($chinedu->first_name)->toBe('Chinedu')
        ->and($chinedu->last_name)->toBe('Okafor')
        ->and($chinedu->gender)->toBe(Gender::Male)
        ->and($chinedu->class_name)->toBe('Primary 3')
        ->and($chinedu->date_of_birth->toDateString())->toBe('2016-03-14')
        ->and($chinedu->guardian_phone)->toBe('08012345678')
        ->and($chinedu->is_active)->toBeTrue();

    expect(Student::where('admission_number', 'ADM-101')->first()->gender)->toBe(Gender::Female);

    // The same review token cannot import twice.
    $this->actingAs($this->admin)->post(route('students.import.store', ['token' => $token]));
    expect(Student::count())->toBe(2);
});

test('invalid rows are reported and skipped while valid rows import', function () {
    Student::factory()->create(['school_id' => $this->school->id, 'admission_number' => 'TAKEN-1']);

    $csv = "Admission Number,First Name,Last Name,Gender\n"
        ."A-1,Tolu,Ade,F\n"
        ."TAKEN-1,Emeka,Nwosu,M\n"   // already used at the school
        ."A-2,Kemi,,F\n"             // no surname
        ."A-3,Sam,Eze,unknown\n"     // bad gender
        ."A-1,Dupe,Ola,F\n";         // duplicate in file

    $token = uploadList($this, importCsv($csv));

    $this->actingAs($this->admin)
        ->get(route('students.import.review', ['token' => $token]))
        ->assertSee('already used by another student')
        ->assertSee('Last name (surname) is missing')
        ->assertSee('is not recognised')
        ->assertSee('appears more than once');

    $this->actingAs($this->admin)->post(route('students.import.store', ['token' => $token]));

    expect(Student::where('school_id', $this->school->id)->orderBy('admission_number')->pluck('admission_number')->all())
        ->toBe(['A-1', 'TAKEN-1']);
    expect(Student::where('admission_number', 'A-1')->value('first_name'))->toBe('Tolu');
});

test('a single name column is split using the chosen order', function () {
    $csv = "Name,Gender,Admission Number\nAdebayo Tolu Grace,F,N-1\nMusa, Ibrahim,M,N-2\n";
    $token = uploadList($this, importCsv(str_replace('Musa, Ibrahim', '"Musa, Ibrahim"', $csv)));
    $this->actingAs($this->admin)->post(route('students.import.store', ['token' => $token]));

    $tolu = Student::where('admission_number', 'N-1')->firstOrFail();
    expect($tolu->last_name)->toBe('Adebayo')->and($tolu->first_name)->toBe('Tolu Grace');
    expect(Student::where('admission_number', 'N-2')->first()->last_name)->toBe('Musa');

    $token = uploadList($this, importCsv("Full Name,Sex,Adm No\nJohn Paul Okoro,Male,N-3\n"), 'JSS 1', ['name_order' => StudentImportParser::FIRST_NAME_FIRST]);
    $this->actingAs($this->admin)->post(route('students.import.store', ['token' => $token]));

    $john = Student::where('admission_number', 'N-3')->firstOrFail();
    expect($john->first_name)->toBe('John Paul')->and($john->last_name)->toBe('Okoro')->and($john->class_name)->toBe('JSS 1');
});

test('admission numbers are generated when the school auto-generates them', function () {
    $this->school->update(['school_code' => 'GHS', 'current_session' => '2026/2027', 'auto_generate_admission_numbers' => true]);

    $token = uploadList($this, importCsv("First Name,Last Name,Gender\nAda,Obi,F\nIke,Obi,M\n"));
    $this->actingAs($this->admin)->post(route('students.import.store', ['token' => $token]));

    $numbers = Student::where('school_id', $this->school->id)->orderBy('id')->pluck('admission_number')->all();
    expect($numbers)->toHaveCount(2)
        ->and($numbers[0])->toStartWith('GHS-2026/2027-')
        ->and($numbers[0])->not->toBe($numbers[1]);
});

/**
 * A school whose subscription allows $limit students, with a Primary 3 class
 * and an admin signed in as $test->admin.
 */
function cappedSchool($test, int $limit): School
{
    $school = School::factory()->create(['auto_generate_admission_numbers' => false]);
    $plan = Plan::where('key', PlanKey::Basic)->first() ?? Plan::factory()->create(['key' => PlanKey::Basic]);
    Subscription::factory()->create(['school_id' => $school->id, 'plan_id' => $plan->id, 'status' => SubscriptionStatus::Active, 'students_count' => $limit]);
    $level = AcademicLevel::factory()->create(['school_id' => $school->id]);
    SchoolClass::factory()->create(['school_id' => $school->id, 'academic_level_id' => $level->id, 'name' => 'Primary 3']);
    $test->admin = User::factory()->create(['role' => UserRole::SchoolAdmin, 'school_id' => $school->id]);

    return $school;
}

/**
 * [Admission Number, First Name, Last Name, Gender] rows numbered from $from.
 *
 * @return list<list<string>>
 */
function listRows(string $prefix, int $count, int $from = 1): array
{
    return array_map(fn ($i) => ["{$prefix}{$i}", "First{$i}", "Last{$i}", $i % 2 ? 'M' : 'F'], range($from, $from + $count - 1));
}

function csvList(array $rows): UploadedFile
{
    $lines = array_map(fn ($cells) => implode(',', $cells), [['Admission Number', 'First Name', 'Last Name', 'Gender'], ...$rows]);

    return importCsv(implode("\n", $lines)."\n");
}

function xlsxList(array $rows): UploadedFile
{
    $all = [['Admission Number', 'First Name', 'Last Name', 'Gender'], ...$rows];
    $sheet = '';

    foreach ($all as $r => $cells) {
        $sheet .= '<row r="'.($r + 1).'">';

        foreach ($cells as $c => $value) {
            $sheet .= '<c r="'.chr(65 + $c).($r + 1).'" t="inlineStr"><is><t>'.htmlspecialchars($value).'</t></is></c>';
        }

        $sheet .= '</row>';
    }

    $path = tempnam(sys_get_temp_dir(), 'xlsx');
    $zip = new ZipArchive;
    $zip->open($path, ZipArchive::OVERWRITE);
    $zip->addFromString('[Content_Types].xml', '<?xml version="1.0"?><Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"/>');
    $zip->addFromString('xl/workbook.xml', '<?xml version="1.0"?><workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships"><sheets><sheet name="Sheet1" sheetId="1" r:id="rId1"/></sheets></workbook>');
    $zip->addFromString('xl/_rels/workbook.xml.rels', '<?xml version="1.0"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/></Relationships>');
    $zip->addFromString('xl/worksheets/sheet1.xml', '<?xml version="1.0"?><worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><sheetData>'.$sheet.'</sheetData></worksheet>');
    $zip->close();

    return new UploadedFile($path, 'list.xlsx', null, null, true);
}

function docxList(array $rows): UploadedFile
{
    $word = new PhpWord;
    $table = $word->addSection()->addTable();

    foreach ([['Admission Number', 'First Name', 'Last Name', 'Gender'], ...$rows] as $cells) {
        $table->addRow();

        foreach ($cells as $cell) {
            $table->addCell(2000)->addText($cell);
        }
    }

    $path = tempnam(sys_get_temp_dir(), 'docx');
    WordIO::createWriter($word, 'Word2007')->save($path);

    return new UploadedFile($path, 'list.docx', null, null, true);
}

test('a list larger than the subscription imports only the first students up to the limit', function () {
    $school = cappedSchool($this, 100);

    $token = uploadList($this, csvList(listRows('S', 101)));

    $this->actingAs($this->admin)
        ->get(route('students.import.review', ['token' => $token]))
        ->assertOk()
        ->assertSee('Only the first 100 students in this list will be added', false)
        ->assertSee('Import 100 Students');

    $this->actingAs($this->admin)
        ->post(route('students.import.store', ['token' => $token]))
        ->assertRedirect()
        ->assertSessionHasNoErrors()
        ->assertSessionHas('capacity_notice', 'Your school subscription allows a maximum of 100 students. Only the first 100 students have been added. Please subscribe for additional student capacity.');

    expect($school->students()->count())->toBe(100)
        ->and($school->students()->where('admission_number', 'S100')->exists())->toBeTrue()
        ->and($school->students()->where('admission_number', 'S101')->exists())->toBeFalse();

    $this->actingAs($this->admin)
        ->get(route('students.index'))
        ->assertSee('<small class="block text-sm font-medium">Your school subscription allows a maximum of 100 students. Only the first 100 students have been added. Please subscribe for additional student capacity.</small>', false);
});

test('the subscription limit applies the same way to CSV, Excel and Word uploads', function (string $format) {
    $school = cappedSchool($this, 3);
    $rows = listRows(strtoupper($format).'-', 5);

    $file = match ($format) {
        'csv' => csvList($rows),
        'xlsx' => xlsxList($rows),
        'docx' => docxList($rows),
    };

    $token = uploadList($this, $file);

    $this->actingAs($this->admin)
        ->post(route('students.import.store', ['token' => $token]))
        ->assertSessionHasNoErrors()
        ->assertSessionHas('capacity_notice', 'Your school subscription allows a maximum of 3 students. Only the first 3 students have been added. Please subscribe for additional student capacity.');

    expect($school->students()->orderBy('id')->pluck('admission_number')->all())
        ->toBe([strtoupper($format).'-1', strtoupper($format).'-2', strtoupper($format).'-3']);
})->with(['csv', 'xlsx', 'docx']);

test('students already registered count towards the limit, so splitting a list into batches does not pass it', function () {
    $school = cappedSchool($this, 5);

    $first = uploadList($this, csvList(listRows('A', 3)));
    $this->actingAs($this->admin)->post(route('students.import.store', ['token' => $first]))
        ->assertSessionHasNoErrors()
        ->assertSessionMissing('capacity_notice');

    $second = uploadList($this, csvList(listRows('A', 4, 4)));
    $this->actingAs($this->admin)->post(route('students.import.store', ['token' => $second]))
        ->assertSessionHas('capacity_notice', 'Your school subscription allows a maximum of 5 students. Only the first 2 students have been added. Please subscribe for additional student capacity.');

    expect($school->students()->count())->toBe(5)
        ->and($school->students()->pluck('admission_number')->sort()->values()->all())->toBe(['A1', 'A2', 'A3', 'A4', 'A5']);

    // Full now: a third batch adds nobody, and neither does the manual form.
    $third = uploadList($this, csvList(listRows('A', 2, 8)));
    $this->actingAs($this->admin)->post(route('students.import.store', ['token' => $third]))
        ->assertSessionHasErrors('file');

    expect($school->students()->count())->toBe(5);
});

test('a list within the limit imports every student with no notice', function () {
    $school = cappedSchool($this, 10);

    $token = uploadList($this, csvList(listRows('OK', 10)));

    $this->actingAs($this->admin)->post(route('students.import.store', ['token' => $token]))
        ->assertSessionHasNoErrors()
        ->assertSessionMissing('capacity_notice');

    expect($school->students()->count())->toBe(10);
});

test('a review token cannot be used by another school', function () {
    $token = uploadList($this, importCsv("Admission Number,First Name,Last Name,Gender\nZ1,Ada,Obi,F\n"));

    $other = School::factory()->create();
    activateSchool($other);
    $otherAdmin = User::factory()->create(['role' => UserRole::SchoolAdmin, 'school_id' => $other->id]);

    $this->actingAs($otherAdmin)
        ->post(route('students.import.store', ['token' => $token]))
        ->assertRedirect(route('students.import.create'));

    expect(Student::count())->toBe(0);
});

test('an Excel workbook is read', function () {
    $path = tempnam(sys_get_temp_dir(), 'xlsx');
    $zip = new ZipArchive;
    $zip->open($path, ZipArchive::OVERWRITE);
    $zip->addFromString('[Content_Types].xml', '<?xml version="1.0"?><Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"/>');
    $zip->addFromString('xl/workbook.xml', '<?xml version="1.0"?><workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships"><sheets><sheet name="Sheet1" sheetId="1" r:id="rId1"/></sheets></workbook>');
    $zip->addFromString('xl/_rels/workbook.xml.rels', '<?xml version="1.0"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/></Relationships>');
    $zip->addFromString('xl/sharedStrings.xml', '<?xml version="1.0"?><sst xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><si><t>Admission Number</t></si><si><t>First Name</t></si><si><t>Surname</t></si><si><t>Gender</t></si><si><t>Date of Birth</t></si><si><t>Ada</t></si><si><t>Obi</t></si><si><t>Female</t></si></sst>');
    $zip->addFromString('xl/worksheets/sheet1.xml', '<?xml version="1.0"?><worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><sheetData>'
        .'<row r="1"><c r="A1" t="s"><v>0</v></c><c r="B1" t="s"><v>1</v></c><c r="C1" t="s"><v>2</v></c><c r="D1" t="s"><v>3</v></c><c r="E1" t="s"><v>4</v></c></row>'
        .'<row r="2"><c r="A2"><v>1001</v></c><c r="B2" t="s"><v>5</v></c><c r="C2" t="s"><v>6</v></c><c r="D2" t="s"><v>7</v></c><c r="E2"><v>42370</v></c></row>'
        .'</sheetData></worksheet>');
    $zip->close();

    $rows = app(StudentImportReader::class)->read(new UploadedFile($path, 'list.xlsx', null, null, true));
    $result = app(StudentImportParser::class)->parse($rows, false);

    expect($result['rows'])->toHaveCount(1)
        ->and($result['rows'][0]['errors'])->toBe([])
        ->and($result['rows'][0]['data']['admission_number'])->toBe('1001')
        ->and($result['rows'][0]['data']['first_name'])->toBe('Ada')
        ->and($result['rows'][0]['data']['last_name'])->toBe('Obi')
        ->and($result['rows'][0]['data']['gender'])->toBe('female')
        ->and($result['rows'][0]['data']['date_of_birth'])->toBe('2016-01-01');
});

test('a table in a Word document is read', function () {
    $word = new PhpWord;
    $section = $word->addSection();
    $section->addText('Greenfield School - Primary 3 Class List');
    $table = $section->addTable();

    foreach ([['Adm No', 'Surname', 'Other Names', 'Sex'], ['W-1', 'Eze', 'Chioma', 'F'], ['W-2', 'Lawal', 'Sodiq', 'M']] as $cells) {
        $table->addRow();

        foreach ($cells as $cell) {
            $table->addCell(2000)->addText($cell);
        }
    }

    $path = tempnam(sys_get_temp_dir(), 'docx');
    WordIO::createWriter($word, 'Word2007')->save($path);

    $rows = app(StudentImportReader::class)->read(new UploadedFile($path, 'list.docx', null, null, true));
    $result = app(StudentImportParser::class)->parse($rows, false);

    expect($result['rows'])->toHaveCount(2)
        ->and($result['rows'][0]['data']['last_name'])->toBe('Eze')
        ->and($result['rows'][0]['data']['first_name'])->toBe('Chioma')
        ->and($result['rows'][1]['data']['gender'])->toBe('male');
});

test('a table in a PDF is read, ignoring titles, repeated headings and footers', function () {
    $html = '<h3>Greenfield School</h3><table>'
        .'<tr><td>Admission No</td><td>Surname</td><td>First Name</td><td>Gender</td><td>Address</td></tr>'
        .'<tr><td>P-1</td><td>Obi</td><td>Ada Grace</td><td>F</td><td>12 Allen Avenue, Ikeja</td></tr>'
        .'<tr><td>Admission No</td><td>Surname</td><td>First Name</td><td>Gender</td><td>Address</td></tr>'
        .'<tr><td>P-2</td><td>Eze</td><td>Chidi</td><td>M</td><td></td></tr>'
        .'</table><p>Page 1 of 1</p>';

    $path = tempnam(sys_get_temp_dir(), 'pdf');
    file_put_contents($path, Pdf::loadHTML($html)->output());

    $rows = app(StudentImportReader::class)->read(new UploadedFile($path, 'list.pdf', null, null, true));
    $result = app(StudentImportParser::class)->parse($rows, false);

    expect($result['rows'])->toHaveCount(2)
        ->and($result['rows'][0]['errors'])->toBe([])
        ->and($result['rows'][0]['data']['first_name'])->toBe('Ada Grace')
        ->and($result['rows'][0]['data']['address'])->toBe('12 Allen Avenue, Ikeja')
        ->and($result['rows'][1]['data']['last_name'])->toBe('Eze')
        ->and($result['rows'][1]['data']['gender'])->toBe('male');
});

test('a file without recognisable headings is refused with guidance', function () {
    $this->actingAs($this->admin)
        ->post(route('students.import.preview'), [
            'class_name' => 'Primary 3',
            'file' => importCsv("foo,bar\n1,2\n"),
        ])
        ->assertSessionHasErrors('file');

    expect(session('errors')->first('file'))->toContain('column headings');
});

test('imported students can be edited, re-photographed and given a new password like any other', function () {
    $token = uploadList($this, importCsv("Admission Number,First Name,Last Name,Gender\nE-1,Ada,Obi,F\n"));
    $this->actingAs($this->admin)->post(route('students.import.store', ['token' => $token]));
    $student = Student::where('admission_number', 'E-1')->firstOrFail();
    Storage::fake('local');

    $this->actingAs($this->admin)
        ->put(route('students.update', $student), [
            'admission_number' => 'E-1',
            'first_name' => 'Adaeze',
            'last_name' => 'Obi',
            'gender' => 'female',
            'class_name' => 'JSS 1',
            'photo' => UploadedFile::fake()->image('ada.jpg', 400, 400),
        ])
        ->assertSessionHasNoErrors();

    $this->actingAs($this->admin)
        ->put(route('students.credentials', $student), [
            'password' => 'Lagos-Rainfall-93!',
            'password_confirmation' => 'Lagos-Rainfall-93!',
        ])
        ->assertSessionHasNoErrors();

    $student->refresh();
    expect($student->first_name)->toBe('Adaeze')
        ->and($student->class_name)->toBe('JSS 1')
        ->and($student->photo_path)->not->toBeNull()
        ->and(Hash::check('Lagos-Rainfall-93!', $student->password))->toBeTrue();
});
