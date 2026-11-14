<?php

namespace App\Http\Controllers\SchoolAdmin;

use App\Enums\RegistrationSource;
use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\School;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Services\IdentifierGenerator;
use App\Services\Import\ClassNameMatcher;
use App\Services\StudentImport\ExistingStudentMatcher;
use App\Services\StudentImport\StudentImportException;
use App\Services\StudentImport\StudentImportParser;
use App\Services\StudentImport\StudentImportReader;
use App\Services\StudentImport\StudentImportTemplate;
use App\Services\StudentLicenceAllocation;
use App\Support\DuplicateStudentNotice;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response;

/**
 * Adding a whole class of students/pupils from one file.
 *
 * Optional, alongside the one-at-a-time Add Student form rather than instead
 * of it. Two steps, deliberately:
 *
 *   1. Upload. The file is read and every row checked, and the school sees
 *      exactly what was understood, row by row, before anything is saved.
 *   2. Confirm. Only then are the students created.
 *
 * WHICH CLASS. A list that names each student's class ("Class" column) is
 * imported into those classes, several in one upload, with no class chosen
 * on the form. Choosing a class on the form puts everyone in the file into
 * that class instead, whatever the file says, which is how a single class
 * register without a Class column is imported. A class name in the file must
 * be one of the school's own classes (read loosely: "Pry 3" is "Primary 3");
 * a row whose class is missing or unknown is shown for correcting and not
 * imported, so a typo can never invent a class.
 *
 * Imported students are ordinary Student records. Everything the school can
 * do to a student added by hand (edit details, change the photograph, set or
 * reset portal login details, deactivate, delete) works on them unchanged.
 */
class StudentImportController extends Controller
{
    /** How long a checked upload waits for confirmation. */
    private const PREVIEW_TTL_MINUTES = 60;

    public function __construct(
        private readonly StudentImportReader $reader,
        private readonly StudentImportParser $parser,
        private readonly IdentifierGenerator $identifiers,
        private readonly StudentLicenceAllocation $licences,
        private readonly StudentImportTemplate $templates,
    ) {}

    public function create(Request $request): View
    {
        $school = $request->user()->school;

        return view('school-admin.students.import', [
            'school' => $school,
            'classes' => $this->classNames($school),
            'capacity' => $this->licences->summary($school),
            'preview' => null,
            'token' => null,
        ]);
    }

    /**
     * Step 1: read and check the file, then show the preview.
     */
    public function preview(Request $request): RedirectResponse
    {
        $school = $request->user()->school;

        $validated = $request->validate([
            // Optional: without it, each row goes into the class the file gives.
            'class_name' => ['nullable', 'string', Rule::in($this->classNames($school))],
            'file' => ['required', 'file', 'max:5120', 'extensions:'.implode(',', StudentImportReader::EXTENSIONS)],
            'name_order' => ['nullable', Rule::in([StudentImportParser::SURNAME_FIRST, StudentImportParser::FIRST_NAME_FIRST])],
        ], [
            'class_name.in' => 'Please select one of your school\'s classes.',
            'file.required' => 'Please choose the file containing your list of students/pupils.',
            'file.max' => 'The file must not be larger than 5MB.',
            'file.extensions' => 'Please upload a CSV, Excel (.xlsx), Word (.docx) or PDF file.',
        ]);

        if ($school->auto_generate_admission_numbers && ! $school->school_code) {
            return back()->withErrors(['file' => 'Admission numbers are generated automatically at your school, but no school code is set yet. Add one in Settings before importing.'])->withInput();
        }

        try {
            // Students already at the school are recognised below rather than
            // by the parser, so they are skipped rather than reported as errors.
            $result = $this->parser->parse(
                $this->reader->read($validated['file']),
                (bool) $school->auto_generate_admission_numbers,
                [],
                $validated['name_order'] ?? StudentImportParser::SURNAME_FIRST,
            );
        } catch (StudentImportException $e) {
            return back()->withErrors(['file' => $e->getMessage()])->withInput();
        }

        $chosenClass = $validated['class_name'] ?? null;

        if ($chosenClass === null && ! $result['has_class_column']) {
            return back()->withErrors(['class_name' => 'Your file has no Class column, so we cannot tell which class each student/pupil is in. Select a class for everyone in this file, or add a "Class" column to the file.'])->withInput();
        }

        $result = $this->assignClasses($result, $chosenClass, $this->classNames($school));

        // STRICT RULE: a student/pupil who is already registered is never
        // overridden, replaced or modified, and neither is their admission
        // number. Such rows are marked and skipped; the rest carry on.
        // Two checks: against the school's records, then against earlier rows
        // of this file (the same child listed twice). A repeated admission
        // number within the file is already reported by the parser.
        // Each row is checked against the class it is going into.
        $registered = ExistingStudentMatcher::forSchool($school);
        $earlierInFile = new ExistingStudentMatcher;

        foreach ($result['rows'] as $i => $row) {
            $identity = $row['data'] + ['class_name' => $row['class_name']];
            $found = $registered->find($identity, $row['file_admission_number']);

            if ($found === null && $row['errors'] === []) {
                $found = $earlierInFile->find($identity);
                $earlierInFile->remember($identity);
            }

            $result['rows'][$i]['existing'] = $found['reason'] ?? null;

            // The existing record itself, so the review can show its admission
            // number, full details and how it was registered.
            $result['rows'][$i]['existing_student'] = ($found['student_id'] ?? null) !== null
                ? DuplicateStudentNotice::for(Student::findOrFail($found['student_id']), $found['reason'])
                : null;
        }

        // Listed, and imported, class by class in the school's class order,
        // and alphabetically by first name then surname (as names are shown)
        // within each class, whatever order the file was in, so automatically
        // issued admission numbers follow the register too. Done after the
        // duplicate checks above, which depend on file order. Each row keeps
        // its file line number so mistakes can still be found in the file.
        $result['rows'] = self::alphabetical($result['rows'], $this->classNames($school));

        $token = Str::random(40);

        Cache::put($this->cacheKey($request, $token), [
            'school_id' => $school->id,
            'class_name' => $chosenClass,
            'file_name' => $validated['file']->getClientOriginalName(),
            ...$result,
        ], now()->addMinutes(self::PREVIEW_TTL_MINUTES));

        return redirect()->route('students.import.review', ['token' => $token]);
    }

    public function review(Request $request, string $token): View|RedirectResponse
    {
        $school = $request->user()->school;
        $preview = $this->pending($request, $token, $school);

        if ($preview === null) {
            return redirect()->route('students.import.create')
                ->withErrors(['file' => 'That upload has expired. Please upload the file again.']);
        }

        return view('school-admin.students.import', [
            'school' => $school,
            'classes' => $this->classNames($school),
            'capacity' => $this->licences->summary($school),
            'preview' => $preview,
            'token' => $token,
        ]);
    }

    /**
     * Step 2: create the students the school has just reviewed.
     */
    public function store(Request $request, string $token): RedirectResponse
    {
        $school = $request->user()->school;
        $preview = $this->pending($request, $token, $school);

        if ($preview === null) {
            return redirect()->route('students.import.create')
                ->withErrors(['file' => 'That upload has expired. Please upload the file again.']);
        }

        $classNames = $this->classNames($school);

        // An upload checked before rows carried their own class had one
        // class for the whole file.
        foreach ($preview['rows'] as $i => $row) {
            $preview['rows'][$i]['class_name'] ??= $preview['class_name'] ?? null;
        }

        // A class could have been renamed or removed since the upload.
        $missing = collect($preview['rows'])
            ->filter(fn ($row) => $row['errors'] === [] && ($row['existing'] ?? null) === null)
            ->pluck('class_name')
            ->unique()
            ->reject(fn ($class) => in_array($class, $classNames, true))
            ->values();

        if ($missing->isNotEmpty()) {
            return redirect()->route('students.import.create')
                ->withErrors(['class_name' => 'The '.Str::plural('class', $missing->count()).' "'.$missing->implode('", "').'" no longer '.($missing->count() === 1 ? 'exists' : 'exist').'. Please upload the file again.']);
        }

        $rows = self::alphabetical(array_filter($preview['rows'], fn ($row) => $row['errors'] === [] && ($row['existing'] ?? null) === null), $classNames);
        $alreadyRegistered = count(array_filter($preview['rows'], fn ($row) => ($row['existing'] ?? null) !== null));

        if ($rows === []) {
            return back()->withErrors(['file' => $alreadyRegistered > 0
                ? 'Every student/pupil in this file is already registered at your school, so there is nothing new to import. Existing records have not been changed.'
                : 'There are no valid rows to import. Please correct the file and upload it again.']);
        }

        $autoGenerate = (bool) $school->auto_generate_admission_numbers;
        $skipped = [];
        $duplicates = [];

        $notAdded = 0;

        // Imports rows in alphabetical order until the school's allocation is full:
        // the current students plus this batch can never pass the limit, so
        // splitting a list across several uploads gets no further than one.
        $importedInto = [];

        $created = $this->licences->withRoomFor($school, function (?int $room) use ($school, $rows, $autoGenerate, &$skipped, &$duplicates, &$notAdded, &$importedInto) {
            // Re-checked inside the lock against the students as they are
            // now: someone may have registered one of these children, or used
            // one of these admission numbers, while the preview was open.
            $matcher = ExistingStudentMatcher::forSchool($school);

            $count = 0;

            foreach ($rows as $index => $row) {
                if ($room !== null && $count >= $room) {
                    $notAdded = count($rows) - $index;

                    break;
                }

                $data = $row['data'];
                $className = $row['class_name'];
                $identity = $data + ['class_name' => $className];

                // Already registered: skip entirely, never touch the record.
                if (($found = $matcher->find($identity, $row['file_admission_number'] ?? null)) !== null) {
                    $skipped[] = "Row {$row['line']} skipped. {$found['reason']} The existing record was left unchanged.";

                    if ($found['student_id'] !== null) {
                        $duplicates[] = DuplicateStudentNotice::for(Student::findOrFail($found['student_id']), $found['reason']);
                    }

                    continue;
                }

                if ($autoGenerate) {
                    // Never hand out a number someone already holds, even if
                    // the school's sequence was reset or numbers were typed in.
                    $attempts = 0;

                    do {
                        $data['admission_number'] = $this->identifiers->nextAdmissionNumber($school, $className);
                    } while ($matcher->admissionNumberTaken($data['admission_number']) && ++$attempts < 1000);
                }

                // create() only: a bulk upload never updates an existing row.
                $student = $school->students()->create([
                    ...array_filter($data, fn ($value) => $value !== null),
                    'class_name' => $className,
                    'is_active' => true,
                    'registration_source' => RegistrationSource::Bulk,
                ]);

                $matcher->remember($data + ['class_name' => $className], $student->fullName()." ({$student->admission_number})", $student->id);
                $importedInto[$className] = ($importedInto[$className] ?? 0) + 1;
                $count++;
            }

            return $count;
        });

        if ($created === null) {
            return back()->withErrors([
                'file' => $this->licences->limitReachedMessage($school),
            ]);
        }

        Cache::forget($this->cacheKey($request, $token));

        $alreadyRegistered += count($skipped);

        $classes = array_keys($importedInto);
        $into = match (count($classes)) {
            0 => 'your school',
            1 => $classes[0],
            default => count($classes).' classes ('.implode(', ', $classes).')',
        };

        AuditLog::record(
            'students.imported',
            "Imported {$created} student(s)/pupil(s) into {$into} from {$preview['file_name']}."
                .($alreadyRegistered > 0 ? " {$alreadyRegistered} skipped: already registered, left unchanged." : '')
                .($notAdded > 0 ? " {$notAdded} not added: subscription limit reached." : ''),
            $school,
        );

        $message = number_format($created).' '.Str::plural('student/pupil', $created)." imported into {$into}."
            .($alreadyRegistered > 0 ? ' '.number_format($alreadyRegistered).' already registered '.($alreadyRegistered === 1 ? 'was' : 'were').' skipped and left unchanged.' : '')
            .' You can edit any of them, add photographs and set their login details from the Students list.';

        $redirect = redirect()->route('students.index', count($classes) === 1 ? ['class' => $classes[0]] : [])->with('status', $message);

        if ($notAdded > 0) {
            $redirect->with('capacity_notice', $this->licences->importTruncatedMessage($school, $created));
        }

        if ($duplicates !== []) {
            $redirect->with('duplicate_students', $duplicates);
        }

        return $skipped === [] ? $redirect : $redirect->withErrors(['import' => $skipped]);
    }

    /**
     * Which class each row goes into: the class chosen on the upload form for
     * every row, or else the class the row gives, as the school spells it.
     * A row with no class, or a class the school does not have, gets an error
     * and is not imported.
     *
     * @param  array<string, mixed>  $result  from StudentImportParser::parse()
     * @param  list<string>  $classNames
     * @return array<string, mixed>
     */
    private function assignClasses(array $result, ?string $chosenClass, array $classNames): array
    {
        if ($chosenClass !== null && $result['has_class_column']) {
            $result['ignored'][] = $result['class_heading'].' (you selected '.$chosenClass.' for everyone in this file)';
        }

        $matcher = new ClassNameMatcher($classNames);

        foreach ($result['rows'] as $i => $row) {
            if ($chosenClass !== null) {
                $result['rows'][$i]['class_name'] = $chosenClass;

                continue;
            }

            $written = trim((string) ($row['file_class'] ?? ''));
            $class = $matcher->match($written);

            if ($class === null) {
                $result['rows'][$i]['errors'][] = $written === ''
                    ? 'Class is missing. Add it to the file, or select one class for everyone on the upload page.'
                    : $matcher->unknownMessage($written);
            }

            $result['rows'][$i]['class_name'] = $class;
        }

        return $result;
    }

    /**
     * Upload rows sorted by class (in the school's class order), then first
     * name, then surname, then file line. Rows with no name or no class
     * (mistakes) go to the end of their group.
     *
     * @param  array<int, array<string, mixed>>  $rows
     * @param  list<string>  $classOrder
     * @return list<array<string, mixed>>
     */
    private static function alphabetical(array $rows, array $classOrder = []): array
    {
        $position = array_flip($classOrder);

        $key = fn (array $row): array => [
            $position[$row['class_name'] ?? ''] ?? PHP_INT_MAX,
            blank($row['data']['last_name'] ?? null) && blank($row['data']['first_name'] ?? null) ? 1 : 0,
            mb_strtolower(trim((string) ($row['data']['first_name'] ?? ''))),
            mb_strtolower(trim((string) ($row['data']['last_name'] ?? ''))),
            (int) ($row['line'] ?? 0),
        ];

        $rows = array_values($rows);
        usort($rows, fn (array $a, array $b) => $key($a) <=> $key($b));

        return $rows;
    }

    public function cancel(Request $request, string $token): RedirectResponse
    {
        Cache::forget($this->cacheKey($request, $token));

        return redirect()->route('students.import.create')->with('status', 'Upload discarded. Nothing was imported.');
    }

    /**
     * A ready-made CSV with the headings the importer understands. It opens in
     * Excel, so a school can paste its list straight in.
     */
    /**
     * The blank list to fill in, in whichever format the school works in:
     * ?format=csv (the default), xlsx, docx or pdf.
     */
    public function template(Request $request): Response
    {
        $school = $request->user()->school;

        $format = strtolower((string) $request->query('format', 'csv'));

        if (! array_key_exists($format, StudentImportTemplate::FORMATS)) {
            $format = 'csv';
        }

        return response($this->templates->build($format, (bool) $school->auto_generate_admission_numbers), 200, [
            'Content-Type' => $this->templates->contentType($format),
            'Content-Disposition' => 'attachment; filename="'.$this->templates->filename($format).'"',
            'Cache-Control' => 'no-store, private',
        ]);
    }

    /**
     * @return array<string, mixed>|null
     */
    private function pending(Request $request, string $token, School $school): ?array
    {
        $preview = Cache::get($this->cacheKey($request, $token));

        // Tied to the admin who uploaded it and their school, so a token
        // cannot be replayed by anyone else.
        if (! is_array($preview) || ($preview['school_id'] ?? null) !== $school->id) {
            return null;
        }

        return $preview;
    }

    private function cacheKey(Request $request, string $token): string
    {
        return 'student-import:'.$request->user()->getAuthIdentifier().':'.hash('sha256', $token);
    }

    /**
     * @return list<string>
     */
    private function classNames(School $school): array
    {
        return SchoolClass::query()
            ->where('school_id', $school->id)
            ->orderBy('sort_order')
            ->orderBy('name')
            ->pluck('name')
            ->unique()
            ->values()
            ->all();
    }
}
