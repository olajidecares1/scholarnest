<?php

use App\Enums\CbtDocumentUploadStatus;
use App\Enums\UserRole;
use App\Jobs\ProcessCbtDocumentUpload;
use App\Models\AdminRole;
use App\Models\CbtDocumentUpload;
use App\Models\CbtExam;
use App\Models\CbtExamBody;
use App\Models\CbtQuestion;
use App\Models\CbtSubject;
use App\Models\User;
use App\Services\CbtDocumentImportService;
use App\Services\DocumentExtraction\ExtractionResult;
use App\Services\DocumentExtraction\QuestionExtractionProvider;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    $this->superAdmin = User::factory()->create(['role' => UserRole::SuperAdmin, 'school_id' => null]);
});

test('super admin can view the uploads index', function () {
    CbtDocumentUpload::factory()->create(['original_filename' => 'waec-2015-2020.pdf']);

    $this->actingAs($this->superAdmin)
        ->get(route('super-admin.cbt.uploads.index'))
        ->assertStatus(200)
        ->assertSee('waec-2015-2020.pdf');
});

test('uploading a document stores the file and dispatches the extraction job', function () {
    Storage::fake('local');
    Queue::fake();

    $file = UploadedFile::fake()->create('waec-mathematics.pdf', 500, 'application/pdf');

    $response = $this->actingAs($this->superAdmin)->post(route('super-admin.cbt.uploads.store'), [
        'file' => $file,
    ]);

    $response->assertRedirect();

    $upload = CbtDocumentUpload::where('original_filename', 'waec-mathematics.pdf')->firstOrFail();
    expect($upload->status)->toBe(CbtDocumentUploadStatus::Pending);
    Storage::disk('local')->assertExists($upload->path);

    Queue::assertPushed(ProcessCbtDocumentUpload::class, fn ($job) => $job->upload->is($upload));
});

test('rejects a file type that is not pdf, doc, or docx', function () {
    $file = UploadedFile::fake()->create('notes.txt', 10, 'text/plain');

    $this->actingAs($this->superAdmin)
        ->post(route('super-admin.cbt.uploads.store'), ['file' => $file])
        ->assertSessionHasErrors('file');
});

test('the extraction job imports questions when exam body and subject are known', function () {
    Storage::fake('local');
    Storage::fake('public');

    // The uploader picks these on the form. A local parser does not guess an
    // examination board from prose, and inventing one would be worse than
    // asking.
    $examBody = CbtExamBody::factory()->create(['code' => 'WAEC']);
    $subject = CbtSubject::factory()->create(['name' => 'Mathematics']);

    $path = UploadedFile::fake()->create('waec.pdf', 10, 'application/pdf')
        ->storeAs('cbt-uploads/documents', 'waec.pdf', 'local');

    $upload = CbtDocumentUpload::factory()->create([
        'uploaded_by' => $this->superAdmin->id,
        'cbt_exam_body_id' => $examBody->id,
        'cbt_subject_id' => $subject->id,
        'path' => $path,
        'mime_type' => 'application/pdf',
        'status' => CbtDocumentUploadStatus::Pending,
    ]);

    $this->mock(QuestionExtractionProvider::class)
        ->shouldReceive('extract')->once()->andReturn(new ExtractionResult(questions: [
            [
                'number' => 1,
                'question_text' => 'What is 2 + 2?',
                'has_diagram' => false,
                'diagram_description' => null,
                'options' => [
                    ['label' => 'A', 'text' => '3'],
                    ['label' => 'B', 'text' => '4'],
                ],
                'correct_label' => 'B',
                'answer_source' => 'found_in_document',
                'year' => 2015,
            ],
        ]));

    (new ProcessCbtDocumentUpload($upload))->handle(
        app(QuestionExtractionProvider::class),
        app(CbtDocumentImportService::class),
    );

    $upload->refresh();

    expect($upload->status)->toBe(CbtDocumentUploadStatus::Completed)
        ->and($upload->questions_extracted_count)->toBe(1)
        ->and($upload->examBody->code)->toBe('WAEC')
        ->and($upload->detected_years)->toBe([2015]);
});

test('the extraction job flags the upload as needing mapping when no exam body was chosen', function () {
    Storage::fake('local');

    $path = UploadedFile::fake()->create('unknown.pdf', 10, 'application/pdf')
        ->storeAs('cbt-uploads/documents', 'unknown.pdf', 'local');

    $upload = CbtDocumentUpload::factory()->create([
        'uploaded_by' => $this->superAdmin->id,
        'cbt_exam_body_id' => null,
        'cbt_subject_id' => null,
        'path' => $path,
        'mime_type' => 'application/pdf',
        'status' => CbtDocumentUploadStatus::Pending,
    ]);

    $this->mock(QuestionExtractionProvider::class)
        ->shouldReceive('extract')->once()->andReturn(new ExtractionResult(questions: [
            [
                'number' => 1,
                'question_text' => 'What is 2 + 2?',
                'has_diagram' => false,
                'options' => [['label' => 'A', 'text' => '3'], ['label' => 'B', 'text' => '4']],
                'correct_label' => 'B',
                'answer_source' => 'found_in_document',
            ],
        ]));

    (new ProcessCbtDocumentUpload($upload))->handle(
        app(QuestionExtractionProvider::class),
        app(CbtDocumentImportService::class),
    );

    expect($upload->fresh()->status)->toBe(CbtDocumentUploadStatus::NeedsMapping);
});

test('the extraction job fails gracefully when the document yields nothing', function () {
    // A scan, a corrupt file, or a document with no questions in it. The
    // teacher gets a sentence, never an exception.
    Storage::fake('local');

    $path = UploadedFile::fake()->create('scan.pdf', 10, 'application/pdf')
        ->storeAs('cbt-uploads/documents', 'scan.pdf', 'local');

    $upload = CbtDocumentUpload::factory()->create([
        'uploaded_by' => $this->superAdmin->id,
        'path' => $path,
        'mime_type' => 'application/pdf',
        'status' => CbtDocumentUploadStatus::Pending,
    ]);

    $this->mock(QuestionExtractionProvider::class)
        ->shouldReceive('extract')->once()->andReturn(new ExtractionResult(
            questions: [],
            looksScanned: true,
        ));

    (new ProcessCbtDocumentUpload($upload))->handle(
        app(QuestionExtractionProvider::class),
        app(CbtDocumentImportService::class),
    );

    $upload->refresh();

    expect($upload->status)->toBe(CbtDocumentUploadStatus::Failed)
        ->and($upload->error_message)->toContain('scanned or image-based')
        ->and($upload->error_message)->not->toContain('Exception');
});

test('super admin can confirm exam body mapping and import the extracted questions', function () {
    $examBody = CbtExamBody::factory()->create();
    $subject = CbtSubject::factory()->create();

    $upload = CbtDocumentUpload::factory()->create([
        'status' => CbtDocumentUploadStatus::NeedsMapping,
        'ai_response' => [
            'exam_body' => 'Whatever',
            'subject' => 'Whatever',
            'years' => [
                [
                    'year' => 2018,
                    'questions' => [
                        [
                            'number' => 1,
                            'question_text' => 'Sample?',
                            'has_diagram' => false,
                            'diagram_description' => null,
                            'options' => [
                                ['label' => 'A', 'text' => 'Yes'],
                                ['label' => 'B', 'text' => 'No'],
                            ],
                            'correct_label' => 'A',
                            'answer_source' => 'found_in_document',
                        ],
                    ],
                ],
            ],
        ],
    ]);

    $this->actingAs($this->superAdmin)
        ->put(route('super-admin.cbt.uploads.mapping', $upload), [
            'cbt_exam_body_id' => $examBody->id,
            'cbt_subject_id' => $subject->id,
        ])
        ->assertRedirect();

    expect($upload->fresh()->status)->toBe(CbtDocumentUploadStatus::Completed);
    expect($upload->fresh()->questions_extracted_count)->toBe(1);
});

// -----------------------------------------------------------------------------
// Uploading the same paper twice, and the review stage before students see it.
// -----------------------------------------------------------------------------

test('the same document uploaded twice is refused, and allowed on a second try', function () {
    Storage::fake('local');
    Queue::fake();

    $contents = 'the same bytes both times';

    $this->actingAs($this->superAdmin)->post(route('super-admin.cbt.uploads.store'), [
        'file' => UploadedFile::fake()->createWithContent('jamb-english.pdf', $contents),
    ])->assertSessionHasNoErrors();

    // The same paper, even renamed, is the same paper.
    $refused = $this->actingAs($this->superAdmin)->post(route('super-admin.cbt.uploads.store'), [
        'file' => UploadedFile::fake()->createWithContent('jamb-english-copy.pdf', $contents),
    ])->assertSessionHasErrors('file');

    expect(session('errors')->first('file'))->toContain('already uploaded')
        ->and($refused->getTargetUrl())->not->toBeEmpty()
        ->and(CbtDocumentUpload::count())->toBe(1);

    // Deliberately, then: it goes through, and the importer is what stops the
    // questions themselves being duplicated.
    $this->actingAs($this->superAdmin)->post(route('super-admin.cbt.uploads.store'), [
        'file' => UploadedFile::fake()->createWithContent('jamb-english-copy.pdf', $contents),
        'upload_again' => '1',
    ])->assertSessionHasNoErrors();

    expect(CbtDocumentUpload::count())->toBe(2);
});

test('a repeated document is refused in a way the upload form can offer the override for', function () {
    // The upload form sends the file in the background and reads a JSON reply,
    // so the refusal has to say which kind it is, or the "Upload this document
    // again" box never appears.
    Storage::fake('local');
    Queue::fake();

    $this->actingAs($this->superAdmin)->post(route('super-admin.cbt.uploads.store'), [
        'file' => UploadedFile::fake()->createWithContent('jamb.pdf', 'identical bytes'),
    ]);

    $this->actingAs($this->superAdmin)
        ->postJson(route('super-admin.cbt.uploads.store'), [
            'file' => UploadedFile::fake()->createWithContent('jamb.pdf', 'identical bytes'),
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['file', 'upload_again']);

    // A file refused for any other reason does not offer it.
    $this->actingAs($this->superAdmin)
        ->postJson(route('super-admin.cbt.uploads.store'), [
            'file' => UploadedFile::fake()->create('notes.txt', 10, 'text/plain'),
        ])
        ->assertUnprocessable()
        ->assertJsonMissingValidationErrors('upload_again');
});

test('super admin can publish an upload and hide it again', function () {
    $upload = CbtDocumentUpload::factory()->create(['status' => CbtDocumentUploadStatus::Completed]);
    $exam = CbtExam::factory()->create();
    $question = CbtQuestion::factory()->create([
        'cbt_exam_id' => $exam->id,
        'cbt_document_upload_id' => $upload->id,
        'is_published' => false,
        'needs_review' => false,
    ]);

    $this->actingAs($this->superAdmin)
        ->post(route('super-admin.cbt.uploads.publish', $upload))
        ->assertRedirect();

    expect($question->fresh()->is_published)->toBeTrue()
        ->and($upload->fresh()->published_at)->not->toBeNull();

    $this->actingAs($this->superAdmin)
        ->post(route('super-admin.cbt.uploads.unpublish', $upload))
        ->assertRedirect();

    expect($question->fresh()->is_published)->toBeFalse()
        ->and($upload->fresh()->published_at)->toBeNull();
});

test('a published upload cannot be read again until it is unpublished', function () {
    Queue::fake();

    $upload = CbtDocumentUpload::factory()->create(['status' => CbtDocumentUploadStatus::Completed]);
    CbtQuestion::factory()->create([
        'cbt_exam_id' => CbtExam::factory()->create()->id,
        'cbt_document_upload_id' => $upload->id,
        'is_published' => true,
    ]);

    $this->actingAs($this->superAdmin)
        ->post(route('super-admin.cbt.uploads.retry', $upload))
        ->assertSessionHasErrors('upload');

    Queue::assertNotPushed(ProcessCbtDocumentUpload::class);
    expect($upload->fresh()->status)->toBe(CbtDocumentUploadStatus::Completed);
});

test('the review page offers to read a finished document again, once nothing is published', function () {
    $upload = CbtDocumentUpload::factory()->create(['status' => CbtDocumentUploadStatus::Completed]);
    $question = CbtQuestion::factory()->create([
        'cbt_exam_id' => CbtExam::factory()->create()->id,
        'cbt_document_upload_id' => $upload->id,
        'is_published' => false,
    ]);

    $this->actingAs($this->superAdmin)
        ->get(route('super-admin.cbt.uploads.show', $upload))
        ->assertOk()
        ->assertSee('Read This Document Again')
        ->assertDontSee('Unpublish first');

    $question->update(['is_published' => true]);

    $this->actingAs($this->superAdmin)
        ->get(route('super-admin.cbt.uploads.show', $upload))
        ->assertOk()
        ->assertSee('Unpublish first');
});

test('reading a document again clears the paper its first reading filed wrongly', function () {
    // What the old reader did with a nine-year compilation: no year headings
    // found, so every question went into one paper under the year of upload.
    $examBody = CbtExamBody::factory()->create(['code' => 'JAMB']);
    $subject = CbtSubject::factory()->create(['name' => 'Literature in English']);

    $upload = CbtDocumentUpload::factory()->create([
        'cbt_exam_body_id' => $examBody->id,
        'cbt_subject_id' => $subject->id,
        'ai_response' => ['years' => [['year' => 2026, 'questions' => [[
            'number' => 1,
            'question_text' => 'Everything welded into one paper?',
            'has_diagram' => false,
            'options' => [['label' => 'A', 'text' => 'Yes'], ['label' => 'B', 'text' => 'No']],
            'correct_label' => 'A',
            'answer_source' => 'found_in_document',
        ]]]]],
    ]);

    app(CbtDocumentImportService::class)->import($upload, $examBody, $subject);
    $wrong = CbtExam::where('year', 2026)->firstOrFail();

    // Read again, this time with the years the document actually names.
    $upload->update(['ai_response' => ['years' => [
        ['year' => 2010, 'questions' => [[
            'number' => 1,
            'question_text' => 'A 2010 question?',
            'has_diagram' => false,
            'options' => [['label' => 'A', 'text' => 'Yes'], ['label' => 'B', 'text' => 'No']],
            'correct_label' => 'A',
            'answer_source' => 'found_in_document',
        ]]],
        ['year' => 2011, 'questions' => [[
            'number' => 1,
            'question_text' => 'A 2011 question?',
            'has_diagram' => false,
            'options' => [['label' => 'A', 'text' => 'Yes'], ['label' => 'B', 'text' => 'No']],
            'correct_label' => 'A',
            'answer_source' => 'found_in_document',
        ]]],
    ]]]);

    app(CbtDocumentImportService::class)->import($upload->fresh(), $examBody, $subject);

    expect(CbtExam::find($wrong->id))->toBeNull()
        ->and(CbtExam::where('cbt_exam_body_id', $examBody->id)->pluck('year')->sort()->values()->all())->toBe([2010, 2011]);
});

test('deleting an upload keeps the questions students can already see', function () {
    Storage::fake('local');
    $path = UploadedFile::fake()->create('mixed.pdf', 10, 'application/pdf')->storeAs('cbt-uploads/documents', 'mixed.pdf', 'local');
    $upload = CbtDocumentUpload::factory()->create(['path' => $path]);
    $exam = CbtExam::factory()->create();

    $published = CbtQuestion::factory()->create(['cbt_exam_id' => $exam->id, 'cbt_document_upload_id' => $upload->id, 'is_published' => true]);
    $draft = CbtQuestion::factory()->create(['cbt_exam_id' => $exam->id, 'cbt_document_upload_id' => $upload->id, 'is_published' => false]);

    $this->actingAs($this->superAdmin)
        ->delete(route('super-admin.cbt.uploads.destroy', $upload))
        ->assertRedirect();

    expect(CbtQuestion::find($published->id))->not->toBeNull()
        ->and(CbtQuestion::find($draft->id))->toBeNull();
});

test('super admin can delete an upload', function () {
    Storage::fake('local');
    $path = UploadedFile::fake()->create('delete-me.pdf', 10, 'application/pdf')->storeAs('cbt-uploads/documents', 'delete-me.pdf', 'local');
    $upload = CbtDocumentUpload::factory()->create(['path' => $path]);

    $this->actingAs($this->superAdmin)
        ->delete(route('super-admin.cbt.uploads.destroy', $upload))
        ->assertRedirect();

    expect(CbtDocumentUpload::find($upload->id))->toBeNull();
    Storage::disk('local')->assertMissing($path);
});

test('a team member without manage_cbt permission is forbidden from uploads', function () {
    $role = AdminRole::factory()->create(['permissions' => ['manage_payments']]);
    $member = User::factory()->create([
        'role' => UserRole::SuperAdmin,
        'school_id' => null,
        'admin_role_id' => $role->id,
    ]);

    $this->actingAs($member)
        ->get(route('super-admin.cbt.uploads.index'))
        ->assertForbidden();
});
