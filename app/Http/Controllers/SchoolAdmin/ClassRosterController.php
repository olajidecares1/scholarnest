<?php

namespace App\Http\Controllers\SchoolAdmin;

use App\Http\Controllers\Concerns\AuthorizesSchoolOwnership;
use App\Http\Controllers\Controller;
use App\Models\ClassUpdateBatch;
use App\Models\School;
use App\Models\SchoolClass;
use App\Services\ClassRoster\ClassRosterComparer;
use App\Services\ClassRoster\ClassRosterExport;
use App\Services\ClassRoster\ClassRosterFileParser;
use App\Services\ClassRoster\ClassRosterWriter;
use App\Services\ClassRoster\RosterFields;
use App\Services\StudentImport\StudentImportException;
use App\Services\StudentImport\StudentImportParser;
use App\Services\StudentImport\StudentImportReader;
use App\Services\StudentLicenceAllocation;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response;

/**
 * Updating a whole class at once, two ways:
 *
 *  1. UPLOAD FILE TO UPDATE CLASS. The school uploads a revised list (Excel,
 *     CSV, Word or PDF), usually the class list downloaded from this page and
 *     edited. Every row is matched to its student and compared field by
 *     field; the review page shows exactly which students changed and how,
 *     whether that is one student, several or the whole class, and nothing
 *     is saved until the school confirms. Individual students and fields can
 *     be left out, or every change applied to the whole class at once.
 *
 *  2. EDIT CLASS WITHIN THE SYSTEM. The whole class in one spreadsheet-style
 *     grid: change any cell, paste from Excel, add students/pupils and
 *     columns, take students out of the class, then Save Changes.
 *
 * Both are applied by ClassRosterWriter, which writes only what changed and
 * keeps a backup of every value it replaced. Each update is listed on this
 * page and can be rolled back.
 */
class ClassRosterController extends Controller
{
    use AuthorizesSchoolOwnership;

    private const PREVIEW_TTL_MINUTES = 60;

    /** The class editor's largest class, and longest cell. */
    private const EDITOR_MAX_ROWS = 1000;

    public function __construct(
        private readonly StudentImportReader $reader,
        private readonly ClassRosterFileParser $parser,
        private readonly ClassRosterWriter $writer,
        private readonly ClassRosterExport $export,
        private readonly StudentLicenceAllocation $licences,
    ) {}

    /**
     * The class's update page: the two ways to update it, and its history.
     */
    public function show(Request $request, SchoolClass $class): View
    {
        $this->authorizeSchoolOwnership($class);
        $school = $request->user()->school;

        return view('school-admin.students.class-update.show', [
            'school' => $school,
            'class' => $class,
            'classes' => $this->classes($school),
            'studentCount' => $school->students()->where('class_name', $class->name)->count(),
            'batches' => ClassUpdateBatch::query()
                ->where('school_id', $school->id)
                ->where('school_class_id', $class->id)
                ->latest('id')
                ->limit(25)
                ->get(),
        ]);
    }

    /**
     * The class list as it is now, to edit and upload back.
     */
    public function export(Request $request, SchoolClass $class): Response
    {
        $this->authorizeSchoolOwnership($class);
        $school = $request->user()->school;

        $format = strtolower((string) $request->query('format', 'xlsx'));
        if (! array_key_exists($format, ClassRosterExport::FORMATS)) {
            $format = 'xlsx';
        }

        $students = $school->students()->where('class_name', $class->name)->alphabetical()->get();
        $contents = $this->export->build($format, $class, $students, $this->classes($school)->pluck('name')->all());

        return response($contents, 200, [
            'Content-Type' => $this->export->contentType($format),
            'Content-Disposition' => 'attachment; filename="'.$this->export->filename($format).'"',
            'Cache-Control' => 'no-store, private',
        ]);
    }

    /**
     * Option 1, step 1: read the file and work out every change.
     */
    public function upload(Request $request, SchoolClass $class): RedirectResponse
    {
        $this->authorizeSchoolOwnership($class);
        $school = $request->user()->school;

        $validated = $request->validate([
            'file' => ['required', 'file', 'max:5120', 'extensions:'.implode(',', StudentImportReader::EXTENSIONS)],
            'blanks' => ['nullable', Rule::in([ClassRosterComparer::BLANKS_KEEP, ClassRosterComparer::BLANKS_CLEAR])],
            'name_order' => ['nullable', Rule::in([StudentImportParser::SURNAME_FIRST, StudentImportParser::FIRST_NAME_FIRST])],
        ], [
            'file.required' => 'Please choose the file with the updated class list.',
            'file.max' => 'The file must not be larger than 5MB.',
            'file.extensions' => 'Please upload a CSV, Excel (.xlsx), Word (.docx) or PDF file.',
        ]);

        try {
            $parsed = $this->parser->parse($this->reader->read($validated['file']));
        } catch (StudentImportException $e) {
            return back()->withErrors(['file' => $e->getMessage()])->withInput();
        }

        $comparer = new ClassRosterComparer($school, $class);

        // Columns in the file the class does not have yet are offered as new
        // columns; only ones with something in them are worth adding.
        $fileExtras = array_map(fn ($c) => RosterFields::extraHeading($c), array_filter($parsed['columns'], fn ($c) => RosterFields::isExtra($c)));
        $fileExtras = array_values(array_filter($fileExtras, fn ($heading) => in_array($heading, $comparer->extraHeadings(), true)
            || collect($parsed['rows'])->contains(fn ($row) => filled($row['raw'][RosterFields::extraKey($heading)] ?? null))));
        $extraAfter = array_values(array_unique([...$comparer->extraHeadings(), ...$fileExtras]));

        if (count($extraAfter) > RosterFields::EXTRA_MAX_COLUMNS) {
            return back()->withErrors(['file' => 'A class can have up to '.RosterFields::EXTRA_MAX_COLUMNS.' added columns. This file would bring '.$class->name.' to '.count($extraAfter).'. Remove the columns you do not need and upload it again.'])->withInput();
        }

        $columns = array_values(array_filter($parsed['columns'], fn ($c) => ! RosterFields::isExtra($c) || in_array(RosterFields::extraHeading($c), $extraAfter, true)));

        $diff = $comparer->compare(
            array_map(fn ($row) => ['key' => 'line-'.$row['line'], 'line' => $row['line'], 'raw' => $row['raw'], 'full_name' => $row['full_name']], $parsed['rows']),
            $columns,
            [
                'source' => ClassUpdateBatch::SOURCE_FILE,
                'blanks' => $validated['blanks'] ?? ClassRosterComparer::BLANKS_KEEP,
                'name_order' => $validated['name_order'] ?? StudentImportParser::SURNAME_FIRST,
                'extra_columns' => $extraAfter,
                'file_name' => $validated['file']->getClientOriginalName(),
            ],
        );

        $token = Str::random(40);
        Cache::put($this->cacheKey($request, $class, $token), $diff, now()->addMinutes(self::PREVIEW_TTL_MINUTES));

        return redirect()->route('classes.update.review', ['class' => $class, 'token' => $token]);
    }

    /**
     * Option 1, step 2: the detailed preview of every change.
     */
    public function review(Request $request, SchoolClass $class, string $token): View|RedirectResponse
    {
        $this->authorizeSchoolOwnership($class);
        $diff = $this->pending($request, $class, $token);

        if ($diff === null) {
            return redirect()->route('classes.update.show', $class)
                ->withErrors(['file' => 'That upload has expired. Please upload the file again.']);
        }

        return view('school-admin.students.class-update.review', [
            'school' => $request->user()->school,
            'class' => $class,
            'diff' => $diff,
            'scope' => ClassRosterComparer::scope($diff),
            'token' => $token,
            'capacity' => $this->licences->summary($request->user()->school),
        ]);
    }

    /**
     * Option 1, step 3: apply what the school confirmed.
     */
    public function apply(Request $request, SchoolClass $class, string $token): RedirectResponse
    {
        $this->authorizeSchoolOwnership($class);
        $school = $request->user()->school;
        $diff = $this->pending($request, $class, $token);

        if ($diff === null) {
            return redirect()->route('classes.update.show', $class)
                ->withErrors(['file' => 'That upload has expired. Please upload the file again.']);
        }

        $request->validate([
            'updates' => ['nullable', 'array'],
            'updates.*' => ['array'],
            'updates.*.*' => ['string'],
            'additions' => ['nullable', 'array'],
            'additions.*' => ['string'],
            'new_columns' => ['nullable', 'array'],
            'new_columns.*' => ['string'],
            'apply_all' => ['nullable', 'boolean'],
        ]);

        // "Apply to all": every detected change, to every student.
        $selection = $request->boolean('apply_all')
            ? [
                'updates' => array_map(fn ($u) => array_keys($u['changes']), $diff['updates']),
                'additions' => array_keys($diff['additions']),
                'new_columns' => $diff['new_columns'],
            ]
            : [
                'updates' => (array) $request->input('updates', []),
                'additions' => (array) $request->input('additions', []),
                'new_columns' => (array) $request->input('new_columns', []),
            ];

        $result = $this->writer->apply($school, $class, $diff, $selection, $request->user());

        if ($result['error'] !== null) {
            $back = back()->withErrors(['apply' => $result['error']]);

            return $result['skipped'] === [] ? $back : $back->with('roster_notes', $result['skipped']);
        }

        Cache::forget($this->cacheKey($request, $class, $token));

        return redirect()->route('classes.update.show', $class)
            ->with('status', "{$class->name} was updated: {$result['batch']->describe()}. A backup was kept, so you can roll this update back below.")
            ->with('roster_notes', $result['skipped']);
    }

    public function cancel(Request $request, SchoolClass $class, string $token): RedirectResponse
    {
        $this->authorizeSchoolOwnership($class);
        Cache::forget($this->cacheKey($request, $class, $token));

        return redirect()->route('classes.update.show', $class)->with('status', 'Upload discarded. Nothing was changed.');
    }

    /**
     * Option 2: the whole class in an editable grid.
     */
    public function edit(Request $request, SchoolClass $class): View
    {
        $this->authorizeSchoolOwnership($class);
        $school = $request->user()->school;

        $comparer = new ClassRosterComparer($school, $class);
        $extra = $comparer->extraHeadings();

        $rows = $comparer->students()->map(function ($student) use ($extra) {
            $values = RosterFields::current($student, $extra);

            return [
                'key' => $student->uuid,
                'uuid' => $student->uuid,
                'values' => array_map(fn ($v) => $v ?? '', $values),
            ];
        })->values()->all();

        return view('school-admin.students.class-update.edit', [
            'school' => $school,
            'class' => $class,
            'rows' => $rows,
            'extraColumns' => $extra,
            'classes' => $this->classes($school)->pluck('name')->all(),
            'fields' => $this->editorFields($school),
            'capacity' => $this->licences->summary($school),
        ]);
    }

    /**
     * Option 2: Save Changes.
     */
    public function save(Request $request, SchoolClass $class): RedirectResponse
    {
        $this->authorizeSchoolOwnership($class);
        $school = $request->user()->school;

        $request->validate([
            'roster' => ['required', 'string', 'max:5000000'],
            'columns' => ['nullable', 'string', 'max:10000'],
        ]);

        $roster = json_decode((string) $request->input('roster'), true);
        $headings = json_decode((string) $request->input('columns', '[]'), true);

        if (! is_array($roster) || ! is_array($headings) || count($roster) > self::EDITOR_MAX_ROWS) {
            return back()->withErrors(['roster' => 'The changes could not be read. Please reload the editor and try again.']);
        }

        // The class's added columns, as the editor left them.
        $extraAfter = [];
        foreach ($headings as $heading) {
            $heading = RosterFields::cleanHeading((string) $heading);

            if ($heading === '' || in_array(mb_strtolower($heading), array_map('mb_strtolower', $extraAfter), true)) {
                continue;
            }

            if ($this->parser->isStandardHeading($heading) && ! in_array($heading, $class->extra_columns ?? [], true)) {
                return back()->withErrors(['roster' => "A column called \"{$heading}\" would be confused with one of the standard student details. Please give it a different name."])->withInput();
            }

            $extraAfter[] = $heading;
        }

        if (count($extraAfter) > RosterFields::EXTRA_MAX_COLUMNS) {
            return back()->withErrors(['roster' => 'A class can have up to '.RosterFields::EXTRA_MAX_COLUMNS.' added columns.'])->withInput();
        }

        $fields = array_keys($this->editorFields($school));
        $columns = [...$fields, ...array_map(fn ($h) => RosterFields::extraKey($h), $extraAfter)];

        $rows = [];
        foreach (array_values($roster) as $index => $row) {
            if (! is_array($row)) {
                continue;
            }

            $values = is_array($row['values'] ?? null) ? $row['values'] : [];
            $raw = [];

            foreach ($columns as $column) {
                $value = $values[$column] ?? '';
                $raw[$column] = is_scalar($value) ? mb_substr((string) $value, 0, 2000) : '';
            }

            $rows[] = [
                'key' => (string) ($row['key'] ?? 'new-'.$index),
                'line' => $index + 1,
                'student_uuid' => is_string($row['uuid'] ?? null) ? $row['uuid'] : null,
                'raw' => $raw,
                'remove' => (bool) ($row['removed'] ?? false),
            ];
        }

        $comparer = new ClassRosterComparer($school, $class);
        $diff = $comparer->compare($rows, $columns, [
            'source' => ClassUpdateBatch::SOURCE_EDITOR,
            'extra_columns' => $extraAfter,
        ]);

        if ($diff['errors'] !== []) {
            return back()
                ->withErrors(['roster' => 'Nothing was saved yet. Please correct the highlighted '.Str::plural('cell', count($diff['errors'])).' and save again.'])
                ->with('roster_errors', $diff['errors'])
                ->withInput();
        }

        $result = $this->writer->apply($school, $class, $diff, [
            'updates' => array_map(fn ($u) => array_keys($u['changes']), $diff['updates']),
            'additions' => array_keys($diff['additions']),
            'removals' => array_keys($diff['removals']),
            'new_columns' => $diff['new_columns'],
        ], $request->user());

        if ($result['error'] !== null) {
            return back()->withErrors(['roster' => $result['error']])->with('roster_notes', $result['skipped'])->withInput();
        }

        return redirect()->route('classes.update.edit', $class)
            ->with('status', "Changes saved: {$result['batch']->describe()}. A backup was kept, so you can roll this back from the Update Class page.")
            ->with('roster_notes', [
                ...$result['skipped'],
                ...array_map(fn ($w) => "{$w['name']}: {$w['message']}", $diff['warnings']),
            ]);
    }

    /**
     * Undo an update from its automatic backup.
     */
    public function rollback(Request $request, SchoolClass $class, ClassUpdateBatch $batch): RedirectResponse
    {
        $this->authorizeSchoolOwnership($class, $batch);
        abort_unless($batch->school_class_id === $class->id, 404);

        $result = $this->writer->rollback($batch, $request->user());

        if ($result['error'] !== null) {
            return back()->withErrors(['rollback' => $result['error']]);
        }

        return back()
            ->with('status', "The update of {$batch->created_at?->format('d/m/Y \\a\\t H:i')} was rolled back. {$class->name} is back to how it was before it.")
            ->with('roster_notes', $result['notes']);
    }

    /**
     * The backup itself, as a spreadsheet: every value the update replaced.
     */
    public function backup(Request $request, SchoolClass $class, ClassUpdateBatch $batch): Response
    {
        $this->authorizeSchoolOwnership($class, $batch);
        abort_unless($batch->school_class_id === $class->id, 404);

        $out = fopen('php://temp', 'r+');
        fwrite($out, "\xEF\xBB\xBF");
        fputcsv($out, ['Student/Pupil', 'Change', 'Detail', 'Before', 'After'], ',', '"', '');

        foreach ($batch->snapshot['updated'] ?? [] as $entry) {
            foreach ($entry['after'] as $field => $after) {
                $before = $entry['before'][$field] ?? null;
                fputcsv($out, [$entry['name'], 'Updated', RosterFields::label($field), RosterFields::display($field, $before), RosterFields::display($field, $after)], ',', '"', '');
            }
        }

        foreach ($batch->snapshot['removed'] ?? [] as $entry) {
            fputcsv($out, [$entry['name'], 'Removed from class', 'Class', $entry['class_name'], ''], ',', '"', '');
        }

        foreach ($batch->snapshot['created'] ?? [] as $id) {
            fputcsv($out, ['Student/pupil #'.$id, 'Added', '', '', ''], ',', '"', '');
        }

        rewind($out);
        $contents = (string) stream_get_contents($out);
        fclose($out);

        $name = Str::slug($class->name).'-update-backup-'.$batch->created_at?->format('Y-m-d-His').'.csv';

        return response($contents, 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="'.$name.'"',
            'Cache-Control' => 'no-store, private',
        ]);
    }

    /**
     * The details the editor shows, in order. Admission numbers are
     * read-only where the school generates them, so they are not sent back.
     *
     * @return array<string, string>
     */
    private function editorFields(School $school): array
    {
        $fields = RosterFields::FIELDS;

        if ($school->auto_generate_admission_numbers) {
            unset($fields['admission_number']);
        }

        return $fields;
    }

    /**
     * @return Collection<int, SchoolClass>
     */
    private function classes(School $school)
    {
        return SchoolClass::query()
            ->where('school_id', $school->id)
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();
    }

    /**
     * @return array<string, mixed>|null
     */
    private function pending(Request $request, SchoolClass $class, string $token): ?array
    {
        $diff = Cache::get($this->cacheKey($request, $class, $token));

        if (! is_array($diff) || ($diff['school_id'] ?? null) !== $request->user()->school_id || ($diff['class_id'] ?? null) !== $class->id) {
            return null;
        }

        return $diff;
    }

    private function cacheKey(Request $request, SchoolClass $class, string $token): string
    {
        return 'class-update:'.$request->user()->getAuthIdentifier().':'.$class->id.':'.hash('sha256', $token);
    }
}
