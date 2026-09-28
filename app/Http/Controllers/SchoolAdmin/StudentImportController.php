<?php

namespace App\Http\Controllers\SchoolAdmin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\School;
use App\Models\SchoolClass;
use App\Services\IdentifierGenerator;
use App\Services\StudentImport\StudentImportException;
use App\Services\StudentImport\StudentImportParser;
use App\Services\StudentImport\StudentImportReader;
use App\Services\StudentImport\StudentImportTemplate;
use App\Services\StudentLicenceAllocation;
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
 *   2. Confirm. Only then are the students created, all in the class chosen
 *      on the upload form.
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
            'class_name' => ['required', 'string', Rule::in($this->classNames($school))],
            'file' => ['required', 'file', 'max:5120', 'extensions:'.implode(',', StudentImportReader::EXTENSIONS)],
            'name_order' => ['nullable', Rule::in([StudentImportParser::SURNAME_FIRST, StudentImportParser::FIRST_NAME_FIRST])],
        ], [
            'class_name.required' => 'Please select the class these students/pupils belong to.',
            'class_name.in' => 'Please select one of your school\'s classes.',
            'file.required' => 'Please choose the file containing your list of students/pupils.',
            'file.max' => 'The file must not be larger than 5MB.',
            'file.extensions' => 'Please upload a CSV, Excel (.xlsx), Word (.docx) or PDF file.',
        ]);

        if ($school->auto_generate_admission_numbers && ! $school->school_code) {
            return back()->withErrors(['file' => 'Admission numbers are generated automatically at your school, but no school code is set yet. Add one in Settings before importing.'])->withInput();
        }

        try {
            $result = $this->parser->parse(
                $this->reader->read($validated['file']),
                (bool) $school->auto_generate_admission_numbers,
                $school->students()->whereNotNull('admission_number')->pluck('admission_number')->all(),
                $validated['name_order'] ?? StudentImportParser::SURNAME_FIRST,
            );
        } catch (StudentImportException $e) {
            return back()->withErrors(['file' => $e->getMessage()])->withInput();
        }

        $token = Str::random(40);

        Cache::put($this->cacheKey($request, $token), [
            'school_id' => $school->id,
            'class_name' => $validated['class_name'],
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

        $className = $preview['class_name'];

        // The class could have been renamed or removed since the upload.
        if (! in_array($className, $this->classNames($school), true)) {
            return redirect()->route('students.import.create')
                ->withErrors(['class_name' => "The class \"{$className}\" no longer exists. Please upload the file again and select a class."]);
        }

        $rows = array_values(array_filter($preview['rows'], fn ($row) => $row['errors'] === []));

        if ($rows === []) {
            return back()->withErrors(['file' => 'There are no valid rows to import. Please correct the file and upload it again.']);
        }

        $autoGenerate = (bool) $school->auto_generate_admission_numbers;
        $skipped = [];

        $notAdded = 0;

        // Imports rows in file order until the school's allocation is full:
        // the current students plus this batch can never pass the limit, so
        // splitting a list across several uploads gets no further than one.
        $created = $this->licences->withRoomFor($school, function (?int $room) use ($school, $rows, $className, $autoGenerate, &$skipped, &$notAdded) {
            // Re-checked inside the lock: someone may have added a student
            // with one of these admission numbers while the preview was open.
            $taken = array_flip(array_map('mb_strtolower', $school->students()
                ->whereNotNull('admission_number')
                ->pluck('admission_number')
                ->all()));

            $count = 0;

            foreach ($rows as $index => $row) {
                if ($room !== null && $count >= $room) {
                    $notAdded = count($rows) - $index;

                    break;
                }

                $data = $row['data'];

                if ($autoGenerate) {
                    $data['admission_number'] = $this->identifiers->nextAdmissionNumber($school, $className);
                } elseif (isset($taken[mb_strtolower((string) $data['admission_number'])])) {
                    $skipped[] = "Row {$row['line']}: admission number {$data['admission_number']} is already in use.";

                    continue;
                }

                $school->students()->create([
                    ...array_filter($data, fn ($value) => $value !== null),
                    'class_name' => $className,
                    'is_active' => true,
                ]);

                $taken[mb_strtolower((string) $data['admission_number'])] = true;
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

        AuditLog::record(
            'students.imported',
            "Imported {$created} student(s)/pupil(s) into {$className} from {$preview['file_name']}."
                .($notAdded > 0 ? " {$notAdded} not added: subscription limit reached." : ''),
            $school,
        );

        $message = number_format($created).' '.Str::plural('student/pupil', $created)." imported into {$className}. You can edit any of them, add photographs and set their login details from the Students list.";

        $redirect = redirect()->route('students.index', ['class' => $className])->with('status', $message);

        if ($notAdded > 0) {
            $redirect->with('capacity_notice', $this->licences->importTruncatedMessage($school, $created));
        }

        return $skipped === [] ? $redirect : $redirect->withErrors(['import' => $skipped]);
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
