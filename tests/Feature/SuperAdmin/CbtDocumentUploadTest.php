<?php

use App\Enums\CbtDocumentUploadStatus;
use App\Enums\UserRole;
use App\Jobs\ProcessCbtDocumentUpload;
use App\Models\AdminRole;
use App\Models\CbtDocumentUpload;
use App\Models\CbtExamBody;
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
