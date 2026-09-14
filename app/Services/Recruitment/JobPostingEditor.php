<?php

namespace App\Services\Recruitment;

use App\Enums\EmploymentType;
use App\Enums\JobQuestionType;
use App\Models\JobPosting;
use App\Models\School;
use App\Rules\UploadedImage;
use App\Services\Uploads\UploadStorage;
use App\Support\Uploads\ImageProfile;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

/**
 * Creates and updates a school's vacancies: the fields, the featured image and
 * the extra questions, in one place for both the create and edit forms.
 */
class JobPostingEditor
{
    public const MAX_QUESTIONS = 15;

    public function __construct(private readonly UploadStorage $uploads) {}

    /**
     * @return array<string, mixed>
     */
    public function rules(?JobPosting $job = null): array
    {
        $deadline = ['required', 'date'];

        // A deadline must be in the future when it is SET. Editing some other
        // field of a vacancy whose deadline has already passed must not be
        // blocked by the date it already had.
        if ($job === null || request()->input('closes_at') !== $job->closes_at?->format('Y-m-d')) {
            $deadline[] = 'after_or_equal:today';
        }

        return [
            'title' => ['required', 'string', 'max:150'],
            'department' => ['nullable', 'string', 'max:100'],
            'employment_type' => ['required', Rule::enum(EmploymentType::class)],
            'location' => ['nullable', 'string', 'max:150'],
            'openings' => ['nullable', 'integer', 'min:1', 'max:999'],
            'salary_range' => ['nullable', 'string', 'max:120'],
            'closes_at' => $deadline,
            'description' => ['required', 'string', 'max:10000'],
            'responsibilities' => ['nullable', 'string', 'max:5000'],
            'requirements' => ['nullable', 'string', 'max:5000'],
            'qualifications' => ['nullable', 'string', 'max:5000'],
            'experience_required' => ['nullable', 'string', 'max:150'],
            'application_instructions' => ['nullable', 'string', 'max:3000'],
            'contact_name' => ['nullable', 'string', 'max:150'],
            'contact_email' => ['nullable', 'email', 'max:255'],
            'contact_phone' => ['nullable', 'string', 'max:40'],
            'featured_image' => UploadedImage::rules(ImageProfile::Website),
            'remove_featured_image' => ['nullable', 'boolean'],

            'questions' => ['nullable', 'array', 'max:'.self::MAX_QUESTIONS],
            'questions.*.id' => ['nullable', 'integer'],
            'questions.*.question' => ['required', 'string', 'max:255'],
            'questions.*.type' => ['required', Rule::enum(JobQuestionType::class)],
            'questions.*.options' => ['nullable', 'string', 'max:1000', 'required_if:questions.*.type,'.JobQuestionType::Choice->value],
            'questions.*.required' => ['nullable', 'boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'closes_at.required' => 'Set the date applications close.',
            'closes_at.after_or_equal' => 'The application deadline cannot be in the past.',
            'questions.max' => 'A vacancy can ask at most '.self::MAX_QUESTIONS.' extra questions.',
            'questions.*.question.required' => 'Every extra question needs its wording.',
            'questions.*.options.required_if' => 'List the options for a "Choose one option" question, separated by commas.',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'closes_at' => 'application deadline',
            'employment_type' => 'employment type',
            'featured_image' => 'job image',
        ];
    }

    public function create(School $school, Request $request): JobPosting
    {
        $validated = $request->validate($this->rules(), $this->messages(), $this->attributes());

        return DB::transaction(function () use ($school, $request, $validated) {
            $job = $school->jobPostings()->create($this->attributesFrom($validated));

            $this->saveImage($job, $request);
            $this->syncQuestions($job, $validated['questions'] ?? []);

            return $job;
        });
    }

    public function update(JobPosting $job, Request $request): JobPosting
    {
        $validated = $request->validate($this->rules($job), $this->messages(), $this->attributes());

        DB::transaction(function () use ($job, $request, $validated) {
            $job->update($this->attributesFrom($validated));

            $this->saveImage($job, $request);
            $this->syncQuestions($job, $validated['questions'] ?? []);
        });

        return $job->fresh();
    }

    /**
     * @param  array<string, mixed>  $validated
     * @return array<string, mixed>
     */
    private function attributesFrom(array $validated): array
    {
        return collect($validated)
            ->except(['featured_image', 'remove_featured_image', 'questions'])
            ->all();
    }

    private function saveImage(JobPosting $job, Request $request): void
    {
        $previous = $job->featured_image_path;

        if ($request->hasFile('featured_image')) {
            $path = $this->uploads->storeImage($request->file('featured_image'), 'public', JobPosting::IMAGE_DIRECTORY, ImageProfile::Website, 'featured_image')->path;

            $job->update(['featured_image_path' => $path]);
            $this->uploads->delete('public', $previous);

            return;
        }

        if ($request->boolean('remove_featured_image') && $previous) {
            $job->update(['featured_image_path' => null]);
            $this->uploads->delete('public', $previous);
        }
    }

    /**
     * Replace the vacancy's questions with the submitted list, in order.
     *
     * Editing a question in place keeps its id; removing one deletes it. Neither
     * changes an application already received, each answer carries a copy of
     * the question it answered.
     *
     * @param  array<int, array<string, mixed>>  $questions
     */
    private function syncQuestions(JobPosting $job, array $questions): void
    {
        $kept = [];

        foreach (array_values($questions) as $order => $input) {
            $type = JobQuestionType::from($input['type']);

            $attributes = [
                'question' => trim($input['question']),
                'type' => $type,
                'options' => $type === JobQuestionType::Choice
                    ? array_values(array_unique(array_filter(array_map('trim', preg_split('/[,\n]+/', (string) ($input['options'] ?? '')) ?: []))))
                    : null,
                'is_required' => (bool) ($input['required'] ?? false),
                'sort_order' => $order,
            ];

            $existing = filled($input['id'] ?? null) ? $job->questions()->whereKey($input['id'])->first() : null;

            if ($existing) {
                $existing->update($attributes);
                $kept[] = $existing->id;

                continue;
            }

            $kept[] = $job->questions()->create($attributes)->id;
        }

        $job->questions()->whereNotIn('id', $kept)->delete();
    }
}
