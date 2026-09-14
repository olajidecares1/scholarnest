<?php

use App\Enums\PlanKey;
use App\Enums\StaffRole;
use App\Enums\UserRole;
use App\Models\ClassNote;
use App\Models\Guardian;
use App\Models\NewsPost;
use App\Models\School;
use App\Models\SchoolClass;
use App\Models\Staff;
use App\Models\StoredFile;
use App\Models\Student;
use App\Models\User;
use App\Services\Uploads\ImageProcessor;
use App\Services\Uploads\UploadStorage;
use App\Support\Uploads\ImageProfile;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Illuminate\Testing\TestResponse;
use PhpOffice\PhpWord\IOFactory;
use PhpOffice\PhpWord\PhpWord;
use Tests\Support\UploadFixtures;

/**
 * Every kind of upload, end to end:
 *
 *   form -> validation -> processing -> storage -> database -> address -> browser
 *
 * Each test drives the real route, then follows the file all the way to what
 * a browser would receive, not just "a file exists somewhere".
 */
beforeEach(function () {
    Storage::fake('public');
    Storage::fake('local');

    $this->school = activateSchool(School::factory()->create(), PlanKey::Standard);
    $this->admin = User::factory()->create(['role' => UserRole::SchoolAdmin, 'school_id' => $this->school->id]);
});

afterEach(function () {
    unset($_SERVER['LARAVEL_CLOUD'], $_ENV['LARAVEL_CLOUD']);
});

function uploadSettings(School $school, array $extra = []): array
{
    return ['name' => $school->name, 'timezone' => 'Africa/Lagos', ...$extra];
}

function uploadStudentFields(array $extra = []): array
{
    return ['admission_number' => 'ADM-'.random_int(1000, 9999), 'first_name' => 'Chidi', 'last_name' => 'Okeke', 'gender' => 'male', 'class_name' => 'JSS 1A', ...$extra];
}

function uploadWordDocument(): UploadedFile
{
    $path = tempnam(sys_get_temp_dir(), 'upload_note_').'.docx';
    $word = new PhpWord;
    $word->addSection()->addText('Photosynthesis is how a plant makes food.');
    IOFactory::createWriter($word, 'Word2007')->save($path);

    return new UploadedFile($path, 'Week 3 notes.docx', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document', null, true);
}

/** The signed photograph address a rendered page actually contains. */
function photoAddressOn(TestResponse $page, string $subject, string $uuid): string
{
    preg_match('#https?://[^"\s]*/media/'.$subject.'/'.preg_quote($uuid, '#').'\?[^"\s]+#', $page->getContent(), $match);

    expect($match)->not->toBeEmpty();

    return html_entity_decode($match[0]);
}

describe('school logo', function () {
    test('upload, save, refresh the page: the logo is there and stays transparent', function () {
        $this->actingAs($this->admin)
            ->put(route('settings.update'), uploadSettings($this->school, ['logo' => UploadFixtures::transparentPng()]))
            ->assertSessionHasNoErrors();

        $path = $this->school->fresh()->logo_path;

        // The database holds a path on a disk, never an address.
        expect($path)->toStartWith('school-logos/')->toEndWith('.png')
            ->and($path)->not->toContain('http')
            ->and($path)->not->toContain('/storage/');

        Storage::disk('public')->assertExists($path);

        $stored = Storage::disk('public')->get($path);
        expect(imagecolorsforindex($img = imagecreatefromstring($stored), imagecolorat($img, 2, 2))['alpha'])->toBe(127);

        // The page renders the address the disk gives for that path.
        $this->actingAs($this->admin)
            ->get(route('settings.index'))
            ->assertOk()
            ->assertSee(Storage::disk('public')->url($path), false);
    });

    test('replacing the logo removes the one it replaced', function () {
        $this->actingAs($this->admin)->put(route('settings.update'), uploadSettings($this->school, ['logo' => UploadFixtures::transparentPng()]));
        $first = $this->school->fresh()->logo_path;

        $this->actingAs($this->admin)->put(route('settings.update'), uploadSettings($this->school, ['logo' => UploadFixtures::cameraJpeg(1)]))
            ->assertSessionHasNoErrors();

        $second = $this->school->fresh()->logo_path;

        expect($second)->not->toBe($first);
        Storage::disk('public')->assertMissing($first);
        Storage::disk('public')->assertExists($second);
    });

    test('saving other settings keeps the logo', function () {
        $this->actingAs($this->admin)->put(route('settings.update'), uploadSettings($this->school, ['logo' => UploadFixtures::transparentPng()]));
        $path = $this->school->fresh()->logo_path;

        $this->actingAs($this->admin)->put(route('settings.update'), uploadSettings($this->school, ['principal_name' => 'Mrs Ada Obi']))
            ->assertSessionHasNoErrors();

        expect($this->school->fresh()->logo_path)->toBe($path);
        Storage::disk('public')->assertExists($path);
    });
});

describe('photographs of people', function () {
    test('a staff photo taken on a phone is stored upright and served to the browser', function () {
        $this->actingAs($this->admin)
            ->post(route('staff.store'), [
                'first_name' => 'Ngozi', 'last_name' => 'Adeyemi', 'gender' => 'female', 'role' => StaffRole::Teacher->value,
                'photo' => UploadFixtures::cameraJpeg(6, 4032, 3024),
            ])
            ->assertSessionHasNoErrors();

        $member = Staff::where('school_id', $this->school->id)->where('first_name', 'Ngozi')->sole();

        Storage::disk('local')->assertExists($member->photo_path);
        Storage::disk('public')->assertMissing($member->photo_path);

        $stored = Storage::disk('local')->get($member->photo_path);
        [$width, $height] = getimagesizefromstring($stored);

        expect([$width, $height])->toBe([1200, 1600])
            ->and(app(ImageProcessor::class)->jpegOrientation($stored))->toBe(1)
            ->and($stored)->not->toContain('GPS-6.5244N');

        $response = $this->get($member->photoUrl())->assertOk();

        expect($response->headers->get('Content-Type'))->toBe('image/jpeg')
            ->and($response->headers->get('X-Content-Type-Options'))->toBe('nosniff')
            ->and($response->streamedContent())->toBe($stored);
    });

    test('a student photo: upload, save, refresh the page, and the image on it loads', function () {
        $this->actingAs($this->admin)
            ->post(route('students.store'), uploadStudentFields(['photo' => UploadFixtures::cameraJpeg(8)]))
            ->assertSessionHasNoErrors();

        $student = Student::where('school_id', $this->school->id)->sole();

        $page = $this->actingAs($this->admin)->get(route('students.show', $student))->assertOk();

        $this->get(photoAddressOn($page, 'student', $student->uuid))
            ->assertOk()
            ->assertHeader('Content-Type', 'image/jpeg');
    });

    test('replacing a student photo removes the previous one', function () {
        $this->actingAs($this->admin)->post(route('students.store'), uploadStudentFields(['photo' => UploadFixtures::cameraJpeg(1)]));
        $student = Student::where('school_id', $this->school->id)->sole();
        $first = $student->photo_path;

        $this->actingAs($this->admin)
            ->put(route('students.update', $student), uploadStudentFields([
                'admission_number' => $student->admission_number,
                'photo' => UploadFixtures::cameraJpeg(6),
            ]))
            ->assertSessionHasNoErrors();

        expect($student->fresh()->photo_path)->not->toBe($first);
        Storage::disk('local')->assertMissing($first);
    });

    test('an account photo uploads and loads', function () {
        $this->actingAs($this->admin)
            ->patch(route('profile.update'), ['name' => $this->admin->name, 'email' => $this->admin->email, 'photo' => UploadFixtures::cameraJpeg(3)])
            ->assertSessionHasNoErrors();

        $user = $this->admin->fresh();

        Storage::disk('local')->assertExists($user->photo_path);
        $this->get($user->photoUrl())->assertOk()->assertHeader('Content-Type', 'image/jpeg');
    });

    test('a guardian photograph on file is served through the same protected route', function () {
        // There is no guardian photo upload anywhere in the application; this
        // proves the display path is ready for one.
        $guardian = Guardian::factory()->create(['school_id' => $this->school->id]);
        $bytes = app(ImageProcessor::class)->process(UploadFixtures::cameraJpeg(1)->getRealPath(), ImageProfile::Portrait)->bytes;
        Storage::disk('local')->put('guardians/parent.jpg', $bytes);
        $guardian->update(['photo_path' => 'guardians/parent.jpg']);

        expect($this->get($guardian->photoUrl())->assertOk()->streamedContent())->toBe($bytes);
    });
});

describe('website images', function () {
    test('the header image is processed, stored publicly and addressed from the disk', function () {
        $this->actingAs($this->admin)
            ->put(route('website.update-header-hero-fields'), ['show_whats_happening' => '1', 'hero_image' => UploadFixtures::cameraJpeg(1, 5000, 2500)])
            ->assertSessionHasNoErrors();

        $website = $this->school->fresh()->website;

        Storage::disk('public')->assertExists($website->hero_image_path);
        expect(getimagesizefromstring(Storage::disk('public')->get($website->hero_image_path))[0])->toBe(2560)
            ->and($website->heroImageUrl())->toBe(Storage::disk('public')->url($website->hero_image_path));
    });

    test('a gallery image is stored and recorded', function () {
        $this->actingAs($this->admin)
            ->post(route('website.gallery.store'), ['image' => UploadFixtures::transparentWebp(), 'caption' => 'Sports day'])
            ->assertSessionHasNoErrors();

        $image = $this->school->fresh()->galleryImages()->sole();

        Storage::disk('public')->assertExists($image->image_path);
        expect($image->image_path)->toEndWith('.webp')
            ->and($image->imageUrl())->toBe(Storage::disk('public')->url($image->image_path));
    });

    test('a news image is stored and recorded', function () {
        $this->actingAs($this->admin)
            ->post(route('news.store'), ['title' => 'Inter-house sports', 'body' => 'Blue house won.', 'image' => UploadFixtures::cameraJpeg(6)])
            ->assertSessionHasNoErrors();

        $post = NewsPost::where('school_id', $this->school->id)->sole();

        Storage::disk('public')->assertExists($post->image_path);
    });
});

describe('documents', function () {
    test('a class note is stored privately under a generated name and downloads intact', function () {
        SchoolClass::factory()->create(['school_id' => $this->school->id, 'name' => 'JSS 1A']);
        $staff = Staff::factory()->create(['school_id' => $this->school->id, 'role' => StaffRole::Teacher, 'is_active' => true]);
        $document = uploadWordDocument();
        $original = file_get_contents($document->getRealPath());

        $this->actingAs($staff, 'staff')
            ->post(route('staff.class-notes.store', $this->school), ['title' => 'Photosynthesis', 'class_names' => ['JSS 1A'], 'document' => $document])
            ->assertSessionHasNoErrors();

        $note = ClassNote::sole();

        // Generated name; the teacher's own filename is only metadata.
        expect($note->path)->toStartWith(ClassNote::DIRECTORY.'/')->toEndWith('.docx')
            ->and($note->path)->not->toContain('Week 3')
            ->and($note->original_name)->toBe('Week 3 notes.docx');

        Storage::disk('local')->assertExists($note->path);

        $download = $this->actingAs($staff, 'staff')->get(route('staff.class-notes.download', [$this->school, $note]))->assertOk();

        expect($download->streamedContent())->toBe($original);
    });
});

describe('what is refused, with a reason, and nothing stored', function () {
    test('a file that is not an image', function (UploadedFile $file, string $message) {
        $this->actingAs($this->admin)
            ->post(route('students.store'), uploadStudentFields(['photo' => $file]))
            ->assertSessionHasErrors('photo');

        expect(session('errors')->first('photo'))->toContain($message);

        expect(Storage::disk('local')->allFiles())->toBe([]);
    })->with([
        'a PHP script named photo.jpg' => [fn () => UploadFixtures::phpDisguisedAsJpeg(), 'not an image this site accepts'],
        'a corrupt JPEG' => [fn () => UploadFixtures::corruptJpeg(), 'damaged'],
        'a 400-megapixel PNG' => [fn () => UploadFixtures::pixelBombPng(), 'too large to process'],
    ]);

    test('a photo over the size limit', function () {
        $this->actingAs($this->admin)
            ->post(route('students.store'), uploadStudentFields(['photo' => UploadedFile::fake()->image('huge.jpg', 100, 100)->size(11000)]))
            ->assertSessionHasErrors('photo');

        expect(session('errors')->first('photo'))->toContain('larger than 10MB');
    });

    test('an iPhone HEIC photo this server cannot convert, with instructions', function () {
        if (class_exists(Imagick::class) && Imagick::queryFormats('HEI*') !== []) {
            $this->markTestSkipped('This server can convert HEIC.');
        }

        $this->actingAs($this->admin)
            ->post(route('students.store'), uploadStudentFields(['photo' => UploadFixtures::heic()]))
            ->assertSessionHasErrors('photo');

        expect(session('errors')->first('photo'))->toContain('Most Compatible');
    });

    test('a logo that is not an image leaves the existing logo untouched', function () {
        $this->actingAs($this->admin)->put(route('settings.update'), uploadSettings($this->school, ['logo' => UploadFixtures::transparentPng()]));
        $path = $this->school->fresh()->logo_path;

        $this->actingAs($this->admin)
            ->put(route('settings.update'), uploadSettings($this->school, ['logo' => UploadFixtures::phpDisguisedAsJpeg()]))
            ->assertSessionHasErrors('logo');

        expect($this->school->fresh()->logo_path)->toBe($path);
        Storage::disk('public')->assertExists($path);
    });
});

describe('one school can never reach another school\'s files', function () {
    beforeEach(function () {
        $this->otherSchool = activateSchool(School::factory()->create(), PlanKey::Standard);
        $this->otherAdmin = User::factory()->create(['role' => UserRole::SchoolAdmin, 'school_id' => $this->otherSchool->id]);

        $this->actingAs($this->admin)->post(route('students.store'), uploadStudentFields(['photo' => UploadFixtures::cameraJpeg(1)]));
        $this->student = Student::where('school_id', $this->school->id)->sole();
    });

    test('another school cannot replace a student\'s photo by addressing the record', function () {
        $before = $this->student->photo_path;

        $this->actingAs($this->otherAdmin)
            ->put(route('students.update', $this->student), uploadStudentFields([
                'admission_number' => $this->student->admission_number,
                'photo' => UploadFixtures::cameraJpeg(6),
            ]))
            ->assertForbidden();

        expect($this->student->fresh()->photo_path)->toBe($before);
        Storage::disk('local')->assertExists($before);
    });

    test('a photograph link cannot be re-pointed at another pupil, or used unsigned', function () {
        $otherStudent = Student::factory()->create(['school_id' => $this->otherSchool->id, 'photo_path' => 'students/other.jpg']);
        Storage::disk('local')->put('students/other.jpg', 'other school pupil');

        $signed = $this->student->photoUrl();

        $this->get(str_replace($this->student->uuid, $otherStudent->uuid, $signed))->assertForbidden();
        $this->get(route('media.photo', ['subject' => 'student', 'uuid' => $otherStudent->uuid]))->assertForbidden();
    });

    test('another school\'s teacher cannot download a class note', function () {
        SchoolClass::factory()->create(['school_id' => $this->school->id, 'name' => 'JSS 1A']);
        $author = Staff::factory()->create(['school_id' => $this->school->id, 'role' => StaffRole::Teacher, 'is_active' => true]);
        $this->actingAs($author, 'staff')->post(route('staff.class-notes.store', $this->school), ['title' => 'Private', 'class_names' => ['JSS 1A'], 'document' => uploadWordDocument()]);
        $note = ClassNote::sole();

        $outsider = Staff::factory()->create(['school_id' => $this->otherSchool->id, 'role' => StaffRole::Teacher, 'is_active' => true]);

        // Their own school in the address, the other school's note.
        $response = $this->actingAs($outsider, 'staff')->get(route('staff.class-notes.download', [$this->otherSchool, $note]));

        expect($response->status())->toBeIn([403, 404]);
    });
});

describe('production storage', function () {
    test('on Laravel Cloud a disk still a local directory at the moment of upload is switched to the database, not refused', function () {
        // What a school admin met in production on the stamp and the signature:
        // "File uploads are not available yet". Here the disks are left as
        // local directories on Laravel Cloud, as that request found them, and
        // the upload must land in the database instead.
        $_SERVER['LARAVEL_CLOUD'] = '1';
        config(['filesystems.disks.public.driver' => 'local', 'filesystems.disks.local.driver' => 'local']);

        $this->actingAs($this->admin)
            ->put(route('settings.update'), uploadSettings($this->school, ['logo' => UploadFixtures::transparentPng()]))
            ->assertSessionHasNoErrors();

        expect(config('filesystems.disks.public.driver'))->toBe('database')
            ->and(StoredFile::locate('public', $this->school->fresh()->logo_path))->not->toBeNull();
    });

    test('on Laravel Cloud every upload disk counts as persistent, because none is left a directory', function () {
        $_SERVER['LARAVEL_CLOUD'] = '1';

        config(['filesystems.disks.public.driver' => 's3', 'filesystems.disks.local.driver' => 'local']);

        expect(UploadStorage::isPersistent('public'))->toBeTrue()
            ->and(UploadStorage::isPersistent('local'))->toBeTrue()
            ->and(config('filesystems.disks.public.driver'))->toBe('s3')
            ->and(config('filesystems.disks.local.driver'))->toBe('database');
    });

    test('uploads:check passes here and on Laravel Cloud', function () {
        $this->artisan('uploads:check')->assertExitCode(0);

        $_SERVER['LARAVEL_CLOUD'] = '1';

        $this->artisan('uploads:check')->assertExitCode(0);
    });

    test('addresses are built from the disk configuration at display time, never stored', function () {
        $this->actingAs($this->admin)->put(route('settings.update'), uploadSettings($this->school, ['logo' => UploadFixtures::transparentPng()]));
        $school = $this->school->fresh();

        config(['filesystems.disks.public.url' => 'https://cdn.example.test']);
        Storage::forgetDisk('public');
        Storage::fake('public', ['url' => 'https://cdn.example.test']);

        expect($school->logoUrl())->toBe('https://cdn.example.test/'.$school->logo_path);
    });
});

describe('on object storage, which is what production needs', function () {
    beforeEach(function () {
        $this->buckets = UploadFixtures::useObjectStorageLikeDisks();
    });

    test('uploads land in the buckets, not on the server, and still load after a redeploy', function () {
        $this->actingAs($this->admin)->put(route('settings.update'), uploadSettings($this->school, ['logo' => UploadFixtures::transparentPng()]))
            ->assertSessionHasNoErrors();
        $this->actingAs($this->admin)->post(route('students.store'), uploadStudentFields(['photo' => UploadFixtures::cameraJpeg(6)]))
            ->assertSessionHasNoErrors();

        $school = $this->school->fresh();
        $student = Student::where('school_id', $this->school->id)->sole();

        expect(is_file($this->buckets['public'].'/'.$school->logo_path))->toBeTrue()
            ->and(is_file($this->buckets['local'].'/'.$student->photo_path))->toBeTrue()
            // Nothing written to the server's own, deploy-wiped storage.
            ->and(is_file(storage_path('app/public/'.$school->logo_path)))->toBeFalse()
            ->and(is_file(storage_path('app/private/'.$student->photo_path)))->toBeFalse()
            ->and($school->logoUrl())->toBe('https://bucket.example.test/public/'.$school->logo_path);

        // A redeploy: every local copy and every resolved disk is gone.
        UploadStorage::releaseLocalCopies();
        File::deleteDirectory(storage_path('framework/cache/stored-files'));
        Storage::forgetDisk(['public', 'local']);

        $page = $this->actingAs($this->admin)->get(route('students.show', $student))->assertOk();

        $this->get(photoAddressOn($page, 'student', $student->uuid))
            ->assertOk()
            ->assertHeader('Content-Type', 'image/jpeg');
    });

    test('PDFs still get real image files, so report cards and ID cards keep their photos', function () {
        $this->actingAs($this->admin)->put(route('settings.update'), uploadSettings($this->school, ['logo' => UploadFixtures::transparentPng()]));
        $this->actingAs($this->admin)->post(route('students.store'), uploadStudentFields(['photo' => UploadFixtures::cameraJpeg(1)]));

        $student = Student::where('school_id', $this->school->id)->sole();

        $photo = $student->photoAbsolutePath();
        $logo = $this->school->fresh()->logoAbsolutePath();

        // Real files, inside dompdf's chroot (the application directory), and
        // identical to what is in the bucket.
        expect(is_file($photo))->toBeTrue()
            ->and(is_file($logo))->toBeTrue()
            ->and(str_starts_with(realpath($photo), realpath(base_path())))->toBeTrue()
            ->and(file_get_contents($photo))->toBe(file_get_contents($this->buckets['local'].'/'.$student->photo_path));

        $pdf = Pdf::loadHTML('<img src="'.$photo.'" style="width:100px"><img src="'.$logo.'" style="width:60px">')->output();

        expect(substr_count($pdf, '/Subtype /Image'))->toBeGreaterThanOrEqual(2);

        UploadStorage::releaseLocalCopies();

        expect(is_file($photo))->toBeFalse();
    });

    test('a class note stored in the bucket still downloads and still has its text read', function () {
        SchoolClass::factory()->create(['school_id' => $this->school->id, 'name' => 'JSS 1A']);
        $staff = Staff::factory()->create(['school_id' => $this->school->id, 'role' => StaffRole::Teacher, 'is_active' => true]);

        $this->actingAs($staff, 'staff')
            ->post(route('staff.class-notes.store', $this->school), ['title' => 'Photosynthesis', 'class_names' => ['JSS 1A'], 'document' => uploadWordDocument()])
            ->assertSessionHasNoErrors();

        $note = ClassNote::sole();

        expect(is_file($this->buckets['local'].'/'.$note->path))->toBeTrue()
            ->and($note->body_text)->toContain('Photosynthesis');

        $this->actingAs($staff, 'staff')->get(route('staff.class-notes.download', [$this->school, $note]))->assertOk();
    });
});
