<?php

namespace App\Http\Controllers\SchoolAdmin;

use App\Enums\StaffRole;
use App\Http\Controllers\Controller;
use App\Models\School;
use App\Models\SchoolClass;
use App\Services\StaffImport\StaffImportParser;
use App\Services\StaffImport\StaffImportRegistrar;
use App\Services\StaffImport\StaffImportTemplate;
use App\Services\StaffImport\StaffImportValidator;
use App\Services\StaffImport\StaffRoleResolver;
use App\Services\StudentImport\StudentImportException;
use App\Services\StudentImport\StudentImportReader;
use App\Services\WhatsApp\WhatsAppGateway;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response;

/**
 * Registering a whole staff list from one file.
 *
 *   1. Upload. The file is read and every row checked: missing details,
 *      duplicate emails and phone numbers, unknown roles and classes, classes
 *      that already have a class teacher, people already on the staff list.
 *   2. Review. The School Admin sees every row as it was understood, and can
 *      correct or remove a row right there; the whole list is checked again
 *      after every change.
 *   3. Confirm. Accounts are created, roles and class/subject assignments
 *      set, passwords issued and login details sent on WhatsApp, then a
 *      summary shows exactly what happened.
 *
 * Nothing is saved until step 3. Registered staff are ordinary Staff records:
 * everything the school can do to a staff member added by hand works on them.
 */
class StaffImportController extends Controller
{
    /** How long an uploaded list waits for confirmation. */
    private const PREVIEW_TTL_MINUTES = 60;

    /** How long the summary can be opened again (passwords are never in it). */
    private const SUMMARY_TTL_MINUTES = 120;

    /** Fields the review page lets the School Admin correct. */
    private const EDITABLE = ['first_name', 'last_name', 'role', 'class', 'subjects', 'gender', 'phone', 'email', 'date_of_birth'];

    public function __construct(
        private readonly StudentImportReader $reader,
        private readonly StaffImportParser $parser,
        private readonly StaffImportValidator $validator,
        private readonly StaffImportRegistrar $registrar,
        private readonly StaffImportTemplate $templates,
        private readonly WhatsAppGateway $whatsApp,
    ) {}

    public function create(Request $request): View
    {
        $school = $request->user()->school;

        return view('school-admin.staff.import', [
            'school' => $school,
            ...$this->pageData($school),
            'preview' => null,
            'rows' => [],
            'token' => null,
        ]);
    }

    /**
     * Step 1: read and check the file, then show the review.
     */
    public function preview(Request $request): RedirectResponse
    {
        $school = $request->user()->school;

        $validated = $request->validate([
            'file' => ['required', 'file', 'max:5120', 'extensions:'.implode(',', StudentImportReader::EXTENSIONS)],
            'name_order' => ['nullable', Rule::in([StaffImportParser::SURNAME_FIRST, StaffImportParser::FIRST_NAME_FIRST])],
        ], [
            'file.required' => 'Please choose the file containing your staff list.',
            'file.max' => 'The file must not be larger than 5MB.',
            'file.extensions' => 'Please upload an Excel (.xlsx), CSV, Word (.docx) or PDF file.',
        ]);

        if (! $school->school_code) {
            return back()->withErrors(['file' => 'Staff IDs are made from your school code, and no school code is set yet. Add one in Settings before registering staff.'])->withInput();
        }

        try {
            $result = $this->parser->parse(
                $this->reader->read($validated['file']),
                $validated['name_order'] ?? StaffImportParser::FIRST_NAME_FIRST,
            );
        } catch (StudentImportException $e) {
            return back()->withErrors(['file' => $e->getMessage()])->withInput();
        }

        $token = Str::random(40);

        $this->savePending($request, $token, [
            'school_id' => $school->id,
            'file_name' => $validated['file']->getClientOriginalName(),
            'columns' => $result['columns'],
            'rows' => $result['rows'],
        ]);

        return redirect()->route('staff.import.review', ['token' => $token]);
    }

    public function review(Request $request, string $token): View|RedirectResponse
    {
        $school = $request->user()->school;
        $pending = $this->pending($request, $token, $school);

        if ($pending === null) {
            return $this->expired();
        }

        $rows = $this->validator->validate($school, $pending['rows'], $this->classNames($school));

        return view('school-admin.staff.import', [
            'school' => $school,
            ...$this->pageData($school),
            'preview' => $pending,
            'rows' => $rows,
            'token' => $token,
        ]);
    }

    /**
     * Correct one row on the review page. The whole list is checked again
     * when the page reloads.
     */
    public function updateRow(Request $request, string $token, int $line): RedirectResponse
    {
        $school = $request->user()->school;
        $pending = $this->pending($request, $token, $school);

        if ($pending === null) {
            return $this->expired();
        }

        $validated = $request->validate([
            ...array_fill_keys(self::EDITABLE, ['nullable', 'string', 'max:2000']),
            'password' => ['nullable', 'string', 'max:255'],
            'generate_password' => ['nullable', 'boolean'],
        ]);

        $index = $this->rowIndex($pending, $line);
        abort_if($index === null, 404);

        $raw = $pending['rows'][$index]['raw'];

        foreach (self::EDITABLE as $field) {
            $value = trim((string) ($validated[$field] ?? ''));

            if ($value === '') {
                unset($raw[$field]);
            } else {
                $raw[$field] = $value;
            }
        }

        // The password is never sent back to the page, so a blank field
        // keeps whatever the row had; the box is how to drop it.
        if ($request->boolean('generate_password')) {
            unset($raw['password']);
        } elseif (filled($validated['password'] ?? null)) {
            $raw['password'] = $validated['password'];
        }

        $pending['rows'][$index]['raw'] = $raw;
        $this->savePending($request, $token, $pending);

        return redirect()->to(route('staff.import.review', ['token' => $token])."#row-{$line}")
            ->with('status', "Row {$line} updated and the list checked again.");
    }

    public function removeRow(Request $request, string $token, int $line): RedirectResponse
    {
        $school = $request->user()->school;
        $pending = $this->pending($request, $token, $school);

        if ($pending === null) {
            return $this->expired();
        }

        $index = $this->rowIndex($pending, $line);
        abort_if($index === null, 404);

        array_splice($pending['rows'], $index, 1);

        if ($pending['rows'] === []) {
            $this->forgetPending($request, $token);

            return redirect()->route('staff.import.create')->with('status', 'Every row was removed, so the upload was discarded. Nothing was registered.');
        }

        $this->savePending($request, $token, $pending);

        return redirect()->route('staff.import.review', ['token' => $token])
            ->with('status', "Row {$line} removed from this upload. Nothing else changed.");
    }

    /**
     * Step 3: register everyone who is ready, then show the summary.
     */
    public function store(Request $request, string $token): RedirectResponse
    {
        $school = $request->user()->school;
        $pending = $this->pending($request, $token, $school);

        if ($pending === null) {
            return $this->expired();
        }

        // Checked again against the school as it is now: someone may have
        // added a staff member or a class teacher while the review was open.
        $rows = $this->validator->validate($school, $pending['rows'], $this->classNames($school));

        foreach ($rows as $i => $row) {
            $rows[$i]['password_plain'] = $pending['rows'][$i]['raw']['password'] ?? null;
        }

        $ready = array_filter($rows, fn ($row) => $row['errors'] === [] && $row['existing'] === null);

        if ($ready === []) {
            return back()->withErrors(['file' => 'There is nobody ready to register. Correct the rows marked in red, or upload a corrected file.']);
        }

        $result = $this->registrar->register($school, $rows, $pending['file_name']);

        $this->forgetPending($request, $token);
        Cache::put($this->summaryKey($request, $token), $result['summary'], now()->addMinutes(self::SUMMARY_TTL_MINUTES));

        // The passwords, for this one page view only: the summary can be
        // opened again, the passwords cannot, exactly as when one is set by
        // hand on a staff member's page.
        return redirect()->route('staff.import.summary', ['token' => $token])
            ->with('staff_import_shares', $result['shares']);
    }

    public function summary(Request $request, string $token): View|RedirectResponse
    {
        $summary = Cache::get($this->summaryKey($request, $token));

        if (! is_array($summary)) {
            return redirect()->route('staff.index')->withErrors(['import' => 'That registration summary has expired. The staff registered are in your staff list.']);
        }

        return view('school-admin.staff.import-summary', [
            'summary' => $summary,
            'shares' => session('staff_import_shares', []),
        ]);
    }

    public function cancel(Request $request, string $token): RedirectResponse
    {
        $this->forgetPending($request, $token);

        return redirect()->route('staff.import.create')->with('status', 'Upload discarded. Nobody was registered.');
    }

    /**
     * The blank staff list: ?format=xlsx (the default here), csv, docx or pdf.
     */
    public function template(Request $request): Response
    {
        $school = $request->user()->school;
        $format = strtolower((string) $request->query('format', 'xlsx'));

        if (! array_key_exists($format, StaffImportTemplate::FORMATS)) {
            $format = 'xlsx';
        }

        return response($this->templates->build($format, $this->classNames($school)), 200, [
            'Content-Type' => $this->templates->contentType($format),
            'Content-Disposition' => 'attachment; filename="'.$this->templates->filename($format).'"',
            'Cache-Control' => 'no-store, private',
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function pageData(School $school): array
    {
        $limit = $school->teacherAccountLimit();

        return [
            'classes' => $this->classNames($school),
            'roleOptions' => StaffRoleResolver::KINDS,
            'whatsAppReady' => $this->whatsApp->isConfigured(),
            'teacherRoom' => $limit === null
                ? null
                : max(0, $limit - $school->staff()->where('role', StaffRole::Teacher)->where('is_active', true)->count()),
            'teacherLimit' => $limit,
        ];
    }

    private function expired(): RedirectResponse
    {
        return redirect()->route('staff.import.create')
            ->withErrors(['file' => 'That upload has expired. Please upload the file again.']);
    }

    /**
     * @param  array<string, mixed>  $pending
     */
    private function rowIndex(array $pending, int $line): ?int
    {
        foreach ($pending['rows'] as $index => $row) {
            if ($row['line'] === $line) {
                return $index;
            }
        }

        return null;
    }

    /**
     * The upload as it waits for confirmation, with any password from the
     * file readable again.
     *
     * @return array<string, mixed>|null
     */
    private function pending(Request $request, string $token, School $school): ?array
    {
        $pending = Cache::get($this->cacheKey($request, $token));

        // Tied to the admin who uploaded it and their school, so a token
        // cannot be replayed by anyone else.
        if (! is_array($pending) || ($pending['school_id'] ?? null) !== $school->id) {
            return null;
        }

        foreach ($pending['rows'] as $i => $row) {
            if (isset($row['raw']['password'])) {
                try {
                    $pending['rows'][$i]['raw']['password'] = Crypt::decryptString($row['raw']['password']);
                } catch (DecryptException) {
                    unset($pending['rows'][$i]['raw']['password']);
                }
            }
        }

        return $pending;
    }

    /**
     * Kept in the cache with any password from the file encrypted, so a
     * password is never stored readable, even for the hour a list waits.
     *
     * @param  array<string, mixed>  $pending
     */
    private function savePending(Request $request, string $token, array $pending): void
    {
        foreach ($pending['rows'] as $i => $row) {
            if (isset($row['raw']['password'])) {
                $pending['rows'][$i]['raw']['password'] = Crypt::encryptString($row['raw']['password']);
            }
        }

        Cache::put($this->cacheKey($request, $token), $pending, now()->addMinutes(self::PREVIEW_TTL_MINUTES));
    }

    private function forgetPending(Request $request, string $token): void
    {
        Cache::forget($this->cacheKey($request, $token));
    }

    private function cacheKey(Request $request, string $token): string
    {
        return 'staff-import:'.$request->user()->getAuthIdentifier().':'.hash('sha256', $token);
    }

    private function summaryKey(Request $request, string $token): string
    {
        return 'staff-import-summary:'.$request->user()->getAuthIdentifier().':'.hash('sha256', $token);
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
