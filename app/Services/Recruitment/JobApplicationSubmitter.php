<?php

namespace App\Services\Recruitment;

use App\Enums\JobApplicationStatus;
use App\Enums\JobQuestionType;
use App\Enums\UserRole;
use App\Models\JobApplication;
use App\Models\JobPosting;
use App\Models\User;
use App\Notifications\JobApplicationReceivedNotification;
use App\Notifications\NewJobApplicationNotification;
use App\Services\Uploads\UploadStorage;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Throwable;

/**
 * Takes an application for a vacancy, from anybody, on the school's Job Portal.
 *
 * The school is taken from the VACANCY, never from anything the form sends:
 * the vacancy was found by its token within the school the address resolved
 * to, so an application can only ever land with the school it was made to.
 */
class JobApplicationSubmitter
{
    public const MAX_DOCUMENT_KB = 5120;

    public const MAX_DOCUMENTS = 5;

    public function __construct(private readonly UploadStorage $uploads) {}

    /**
     * @return array<string, mixed>
     */
    public function rules(JobPosting $job): array
    {
        $rules = [
            'full_name' => ['required', 'string', 'max:150'],
            'email' => [
                'required', 'email', 'max:255',
                Rule::unique('job_applications', 'email')->where('job_posting_id', $job->id),
            ],
            'phone' => ['required', 'string', 'max:40', 'regex:/^[0-9+()\-\s]{7,40}$/'],
            'address' => ['nullable', 'string', 'max:500'],
            'cover_letter' => ['required', 'string', 'min:30', 'max:5000'],
            'qualifications' => ['required', 'string', 'max:2000'],
            'years_of_experience' => ['required', 'integer', 'min:0', 'max:60'],

            // mimes reads the file's CONTENT to decide the type; mimetypes pins
            // the exact Word and PDF types, so a renamed script is refused.
            'cv' => [
                'required', 'file', 'max:'.self::MAX_DOCUMENT_KB,
                'mimes:pdf,doc,docx',
                'mimetypes:application/pdf,application/msword,application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            ],
            'documents' => ['nullable', 'array', 'max:'.self::MAX_DOCUMENTS],
            'documents.*' => ['file', 'max:'.self::MAX_DOCUMENT_KB, 'mimes:pdf,doc,docx,jpg,jpeg,png'],
        ];

        foreach ($job->questions as $question) {
            $field = $question->fieldName();

            $rules[$field] = match ($question->type) {
                JobQuestionType::ShortText => [$question->is_required ? 'required' : 'nullable', 'string', 'max:500'],
                JobQuestionType::LongText => [$question->is_required ? 'required' : 'nullable', 'string', 'max:3000'],
                JobQuestionType::YesNo, JobQuestionType::Choice => [$question->is_required ? 'required' : 'nullable', 'string', Rule::in($question->choices())],
            };
        }

        return $rules;
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'email.unique' => 'You have already applied for this position with this email address.',
            'phone.regex' => 'Enter a valid phone number.',
            'cover_letter.min' => 'Tell the school a little more in your cover letter, at least a few sentences.',
            'cv.required' => 'Attach your CV or résumé.',
            'cv.mimes' => 'Your CV must be a PDF or Word document.',
            'cv.mimetypes' => 'Your CV must be a PDF or Word document.',
            'cv.max' => 'Your CV must be 5MB or smaller.',
            'cv.uploaded' => 'Your CV could not be uploaded. It may be larger than 5MB. Please try again with a smaller file.',
            'documents.max' => 'You can attach at most '.self::MAX_DOCUMENTS.' supporting documents.',
            'documents.*.mimes' => 'Supporting documents must be PDF, Word, JPG or PNG files.',
            'documents.*.max' => 'Each supporting document must be 5MB or smaller.',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(JobPosting $job): array
    {
        $attributes = [
            'full_name' => 'full name',
            'years_of_experience' => 'years of experience',
            'cover_letter' => 'cover letter',
            'cv' => 'CV',
        ];

        foreach ($job->questions as $question) {
            $attributes[$question->fieldName()] = '"'.$question->question.'"';
        }

        return $attributes;
    }

    public function submit(JobPosting $job, Request $request): JobApplication
    {
        // Compared as stored: "Ada@Mail.com" and "ada@mail.com" are one person,
        // and the duplicate check must see them as one before the database does.
        if (is_string($request->input('email'))) {
            $request->merge(['email' => mb_strtolower(trim($request->input('email')))]);
        }

        $validated = $request->validate($this->rules($job), $this->messages(), $this->attributes($job));

        $directory = JobApplication::DIRECTORY.'/'.$job->school_id;
        $stored = [];

        try {
            $cv = $request->file('cv');
            $cvPath = $this->uploads->storeFile($cv, JobApplication::DISK, $directory, 'cv');
            $stored[] = $cvPath;

            $documents = [];

            foreach ($request->file('documents') ?? [] as $index => $document) {
                $path = $this->uploads->storeFile($document, JobApplication::DISK, $directory, "documents.{$index}");
                $stored[] = $path;
                $documents[] = [$document, $path];
            }

            $application = DB::transaction(function () use ($job, $validated, $cv, $cvPath, $documents) {
                $application = JobApplication::create([
                    'school_id' => $job->school_id,
                    'job_posting_id' => $job->id,
                    'full_name' => trim($validated['full_name']),
                    'email' => mb_strtolower(trim($validated['email'])),
                    'phone' => trim($validated['phone']),
                    'address' => $validated['address'] ?? null,
                    'cover_letter' => $validated['cover_letter'],
                    'qualifications' => $validated['qualifications'],
                    'years_of_experience' => (int) $validated['years_of_experience'],
                    'cv_path' => $cvPath,
                    'cv_original_name' => mb_substr($cv->getClientOriginalName(), 0, 255),
                    'cv_mime_type' => $cv->getMimeType(),
                    'cv_size_bytes' => (int) $cv->getSize(),
                    'answers' => $this->answers($job, $validated),
                    'status' => JobApplicationStatus::New,
                    'status_changed_at' => now(),
                ]);

                foreach ($documents as [$file, $path]) {
                    /** @var UploadedFile $file */
                    $application->documents()->create([
                        'path' => $path,
                        'original_name' => mb_substr($file->getClientOriginalName(), 0, 255),
                        'mime_type' => $file->getMimeType(),
                        'size_bytes' => (int) $file->getSize(),
                    ]);
                }

                $application->events()->create([
                    'from_status' => null,
                    'to_status' => JobApplicationStatus::New->value,
                    'note' => 'Application submitted on the Job Portal.',
                    'created_at' => now(),
                ]);

                return $application;
            });
        } catch (Throwable $e) {
            // Nothing half-made is left behind: no files without a record.
            foreach ($stored as $path) {
                $this->uploads->delete(JobApplication::DISK, $path);
            }

            // Two submissions racing past validation, a double tap on a slow
            // connection, meet the database's unique index. The second is told
            // plainly, not shown an error page.
            if ($e instanceof UniqueConstraintViolationException) {
                throw ValidationException::withMessages(['email' => $this->messages()['email.unique']]);
            }

            throw $e;
        }

        $this->notify($application);

        return $application;
    }

    /**
     * Each answer stored beside a copy of its question's wording.
     *
     * @param  array<string, mixed>  $validated
     * @return list<array{question: string, answer: string|null}>
     */
    private function answers(JobPosting $job, array $validated): array
    {
        return $job->questions
            ->map(fn ($question) => [
                'question' => $question->question,
                'answer' => filled($validated['answers'][$question->id] ?? null) ? (string) $validated['answers'][$question->id] : null,
            ])
            ->values()
            ->all();
    }

    /**
     * The school's admins are told in the dashboard; the applicant is sent a
     * confirmation. Neither can fail the application: it is already saved, and
     * a mail server being down is not the applicant's problem to retry.
     */
    private function notify(JobApplication $application): void
    {
        User::query()
            ->where('school_id', $application->school_id)
            ->where('role', UserRole::SchoolAdmin)
            ->get()
            ->each(fn (User $admin) => $admin->notify(new NewJobApplicationNotification($application)));

        try {
            Notification::route('mail', [$application->email => $application->full_name])
                ->notify(new JobApplicationReceivedNotification($application));
        } catch (Throwable $e) {
            Log::warning('Job application confirmation email could not be sent.', [
                'application' => $application->uuid,
                'reason' => $e->getMessage(),
            ]);
        }
    }
}
