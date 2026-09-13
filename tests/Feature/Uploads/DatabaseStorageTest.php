<?php

use App\Enums\PlanKey;
use App\Enums\StaffRole;
use App\Enums\UserRole;
use App\Models\ClassNote;
use App\Models\NewsPost;
use App\Models\PageView;
use App\Models\School;
use App\Models\SchoolClass;
use App\Models\Staff;
use App\Models\StoredFile;
use App\Models\Student;
use App\Models\User;
use App\Services\Uploads\UploadStorage;
use App\Support\Storage\DatabaseStorageFallback;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use PhpOffice\PhpWord\IOFactory;
use PhpOffice\PhpWord\PhpWord;
use Tests\Support\UploadFixtures;

/**
 * Uploads on Laravel Cloud with no object storage attached.
 *
 * That is production today: the disks are directories every deploy wipes, so
 * every upload was lost and every school logo was a broken image. Uploads are
 * now kept in the database instead. These tests run the application exactly
 * as it runs there - Laravel Cloud, no buckets - and follow each file to what
 * the browser receives, including after a simulated redeploy.
 */
function onLaravelCloudWithoutBuckets(): void
{
    $_SERVER['LARAVEL_CLOUD'] = '1';

    foreach (['public', 'local'] as $disk) {
        config(["filesystems.disks.{$disk}" => ['driver' => 'local', 'root' => storage_path("framework/testing/cloud-{$disk}")]]);
    }

    DatabaseStorageFallback::apply();
    Storage::forgetDisk(['public', 'local']);
}

/** What a redeploy does: every in-process disk and local copy is gone. */
function simulateRedeploy(): void
{
    UploadStorage::releaseLocalCopies();
    File::deleteDirectory(storage_path('framework/cache/stored-files'));
    Storage::forgetDisk(['public', 'local']);
}

function dbWordDocument(): UploadedFile
{
    $path = tempnam(sys_get_temp_dir(), 'db_note_').'.docx';
    $word = new PhpWord;
    $word->addSection()->addText('Photosynthesis is how a plant makes food.');
    IOFactory::createWriter($word, 'Word2007')->save($path);

    return new UploadedFile($path, 'notes.docx', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document', null, true);
}

beforeEach(function () {
    $this->school = activateSchool(School::factory()->create(), PlanKey::Standard);
    $this->admin = User::factory()->create(['role' => UserRole::SchoolAdmin, 'school_id' => $this->school->id]);
});

afterEach(function () {
    unset($_SERVER['LARAVEL_CLOUD'], $_ENV['LARAVEL_CLOUD']);
});

describe('when the database takes over', function () {
    test('on Laravel Cloud, every disk that is still a local directory', function () {
        onLaravelCloudWithoutBuckets();

        expect(config('filesystems.disks.public.driver'))->toBe('database')
            ->and(config('filesystems.disks.local.driver'))->toBe('database')
            ->and(UploadStorage::isPersistent('public'))->toBeTrue()
            ->and(UploadStorage::isPersistent('local'))->toBeTrue();
    });

    test('never for a disk with a bucket attached', function () {
        $_SERVER['LARAVEL_CLOUD'] = '1';
        config(['filesystems.disks.public' => ['driver' => 's3', 'bucket' => 'b'], 'filesystems.disks.local' => ['driver' => 'local', 'root' => '/tmp']]);

        expect(DatabaseStorageFallback::apply())->toBe(['local'])
            ->and(config('filesystems.disks.public.driver'))->toBe('s3');
    });

    test('never off Laravel Cloud', function () {
        config(['filesystems.disks.public.driver' => 'local']);

        expect(DatabaseStorageFallback::apply())->toBe([])
            ->and(config('filesystems.disks.public.driver'))->toBe('local');
    });
});

describe('the database disk behaves like any other', function () {
    beforeEach(fn () => onLaravelCloudWithoutBuckets());

    test('a file larger than several chunks round-trips byte for byte', function () {
        $bytes = random_bytes((int) (StoredFile::CHUNK_BYTES * 2.5));
        $disk = Storage::disk('local');

        expect($disk->put('documents/big.bin', $bytes))->toBeTrue()
            ->and($disk->exists('documents/big.bin'))->toBeTrue()
            ->and($disk->size('documents/big.bin'))->toBe(strlen($bytes))
            ->and($disk->get('documents/big.bin'))->toBe($bytes)
            ->and(StoredFile::locate('local', 'documents/big.bin')->chunk_count)->toBe(3)
            ->and(stream_get_contents($disk->readStream('documents/big.bin')))->toBe($bytes);
    });

    test('every chunk fits in a 1MB database packet once encoded', function () {
        // SQLite, which this suite runs on, has no packet limit - so without
        // this the chunk size could creep back up and fail only on MySQL, as a
        // 1MB chunk did against XAMPP's MariaDB (max_allowed_packet = 1MB).
        Storage::disk('local')->put('documents/sized.bin', random_bytes(StoredFile::CHUNK_BYTES * 2));

        $largest = DB::table('stored_file_chunks')->selectRaw('max(length(data)) as largest')->value('largest');

        // Headroom for the INSERT statement around the value.
        expect((int) $largest)->toBeLessThan(StoredFile::MINIMUM_PACKET_BYTES - 64 * 1024);
    });

    test('streams in, reports its type, lists, copies, moves and deletes', function () {
        $disk = Storage::disk('public');
        $png = file_get_contents(UploadFixtures::transparentPng()->getRealPath());

        $stream = fopen('php://temp', 'w+b');
        fwrite($stream, $png);
        rewind($stream);
        $disk->writeStream('gallery/a.png', $stream);

        expect($disk->mimeType('gallery/a.png'))->toBe('image/png')
            ->and($disk->allFiles())->toBe(['gallery/a.png'])
            ->and($disk->directories())->toBe(['gallery']);

        $disk->copy('gallery/a.png', 'gallery/b.png');
        $disk->move('gallery/b.png', 'news/c.png');

        expect($disk->allFiles())->toBe(['gallery/a.png', 'news/c.png'])
            ->and($disk->get('news/c.png'))->toBe($png);

        $disk->delete('gallery/a.png');
        $disk->deleteDirectory('news');

        expect($disk->allFiles())->toBe([])
            ->and(DB::table('stored_file_chunks')->count())->toBe(0);
    });

    test('writing to an existing path replaces it', function () {
        Storage::disk('local')->put('x/file.txt', 'first');
        Storage::disk('local')->put('x/file.txt', 'second');

        expect(Storage::disk('local')->get('x/file.txt'))->toBe('second')
            ->and(StoredFile::where('path', 'x/file.txt')->count())->toBe(1);
    });

    test('a missing file reads as absent, not as an error', function () {
        expect(Storage::disk('local')->exists('nope.png'))->toBeFalse()
            ->and(Storage::disk('local')->get('nope.png'))->toBeNull();
    });

    test('public files have an address; private files never do', function () {
        expect(Storage::disk('public')->url('school-logos/a b.png'))->toBe('http://localhost/files/school-logos/a%20b.png');

        Storage::disk('local')->url('students/a.jpg');
    })->throws(RuntimeException::class, 'private');
});

describe('every kind of upload works end to end with no bucket', function () {
    beforeEach(fn () => onLaravelCloudWithoutBuckets());

    test('a school logo: upload, save, redeploy, and the browser still gets the image', function () {
        $upload = UploadFixtures::transparentPng();

        $this->actingAs($this->admin)
            ->put(route('settings.update'), ['name' => $this->school->name, 'timezone' => 'Africa/Lagos', 'logo' => $upload])
            ->assertSessionHasNoErrors();

        $school = $this->school->fresh();
        $stored = StoredFile::locate('public', $school->logo_path);

        expect($stored)->not->toBeNull()
            ->and($school->logoUrl())->toBe('http://localhost/files/'.$school->logo_path)
            // Nothing on the filesystem a deploy would wipe.
            ->and(File::exists(storage_path('framework/testing/cloud-public/'.$school->logo_path)))->toBeFalse();

        simulateRedeploy();

        $this->actingAs($this->admin)->get(route('settings.index'))->assertOk()->assertSee($school->logoUrl(), false);

        $response = $this->get($school->logoUrl())->assertOk();

        expect($response->headers->get('Content-Type'))->toBe('image/png')
            ->and($response->headers->get('Cache-Control'))->toContain('immutable')
            ->and($response->headers->get('X-Content-Type-Options'))->toBe('nosniff')
            ->and($response->streamedContent())->toBe(Storage::disk('public')->get($school->logo_path));

        // Transparent still.
        $image = imagecreatefromstring($response->streamedContent());
        expect(imagecolorsforindex($image, imagecolorat($image, 2, 2))['alpha'])->toBe(127);
    });

    test('a student photo taken on a phone is served upright through its protected link, and never publicly', function () {
        $this->actingAs($this->admin)
            ->post(route('students.store'), ['admission_number' => 'ADM-1', 'first_name' => 'Ada', 'last_name' => 'Obi', 'gender' => 'female', 'class_name' => 'JSS 1', 'photo' => UploadFixtures::cameraJpeg(6)])
            ->assertSessionHasNoErrors();

        $student = Student::where('school_id', $this->school->id)->sole();

        simulateRedeploy();

        $photo = $this->get($student->photoUrl())->assertOk();
        [$width, $height] = getimagesizefromstring($photo->streamedContent());

        expect($photo->headers->get('Content-Type'))->toBe('image/jpeg')
            ->and($height)->toBeGreaterThan($width);

        // The private disk is not reachable through the public file route.
        $this->get('/files/'.$student->photo_path)->assertNotFound();
    });

    test('website, gallery and news images', function () {
        $this->actingAs($this->admin)->post(route('website.gallery.store'), ['image' => UploadFixtures::cameraJpeg(1, 3000, 2000)])->assertSessionHasNoErrors();
        $this->actingAs($this->admin)->post(route('news.store'), ['title' => 'Sports', 'body' => 'Blue house won.', 'image' => UploadFixtures::transparentWebp()])->assertSessionHasNoErrors();

        $gallery = $this->school->fresh()->galleryImages()->sole();
        $news = NewsPost::where('school_id', $this->school->id)->sole();

        simulateRedeploy();

        $this->get($gallery->imageUrl())->assertOk()->assertHeader('Content-Type', 'image/jpeg');
        $this->get($news->imageUrl())->assertOk()->assertHeader('Content-Type', 'image/webp');
    });

    test('a class note downloads intact and its text is still read', function () {
        SchoolClass::factory()->create(['school_id' => $this->school->id, 'name' => 'JSS 1A']);
        $staff = Staff::factory()->create(['school_id' => $this->school->id, 'role' => StaffRole::Teacher, 'is_active' => true]);
        $document = dbWordDocument();
        $original = file_get_contents($document->getRealPath());

        $this->actingAs($staff, 'staff')
            ->post(route('staff.class-notes.store', $this->school), ['title' => 'Plants', 'class_names' => ['JSS 1A'], 'document' => $document])
            ->assertSessionHasNoErrors();

        $note = ClassNote::sole();

        expect($note->body_text)->toContain('Photosynthesis');

        simulateRedeploy();

        expect($this->actingAs($staff, 'staff')->get(route('staff.class-notes.download', [$this->school, $note]))->assertOk()->streamedContent())->toBe($original);
    });

    test('report cards and ID cards still get a real image file for the PDF', function () {
        $this->actingAs($this->admin)->post(route('students.store'), ['admission_number' => 'ADM-2', 'first_name' => 'Ada', 'last_name' => 'Obi', 'gender' => 'female', 'class_name' => 'JSS 1', 'photo' => UploadFixtures::cameraJpeg(1)]);
        $student = Student::where('school_id', $this->school->id)->sole();

        $path = $student->photoAbsolutePath();

        expect(is_file($path))->toBeTrue()
            ->and(file_get_contents($path))->toBe(Storage::disk('local')->get($student->photo_path));
    });

    test('uploads:check passes', function () {
        $this->artisan('uploads:check')->assertExitCode(0);
    });
});

describe('serving public files', function () {
    beforeEach(fn () => onLaravelCloudWithoutBuckets());

    test('a byte range is honoured, so Safari can play a video', function () {
        $bytes = random_bytes(StoredFile::CHUNK_BYTES + 5000);
        Storage::disk('public')->put('media/clip.mp4', $bytes);
        StoredFile::where('path', 'media/clip.mp4')->update(['mime_type' => 'video/mp4']);

        $start = StoredFile::CHUNK_BYTES - 10;
        $response = $this->get('/files/media/clip.mp4', ['Range' => 'bytes='.$start.'-'.($start + 99)]);

        expect($response->getStatusCode())->toBe(206)
            ->and($response->headers->get('Content-Range'))->toBe('bytes '.$start.'-'.($start + 99).'/'.strlen($bytes))
            ->and($response->streamedContent())->toBe(substr($bytes, $start, 100));

        $this->get('/files/media/clip.mp4', ['Range' => 'bytes=999999999-'])->assertStatus(416);
    });

    test('anything that is not an image or video is never rendered', function () {
        Storage::disk('public')->put('gallery/evil.png', '<html><script>alert(1)</script></html>');

        $this->get('/files/gallery/evil.png')->assertNotFound();
    });

    test('the route serves nothing once the public disk is a real bucket', function () {
        Storage::disk('public')->put('gallery/a.png', file_get_contents(UploadFixtures::transparentPng()->getRealPath()));

        config(['filesystems.disks.public.driver' => 's3']);

        $this->get('/files/gallery/a.png')->assertNotFound();
    });

    test('image requests are not counted as page views', function () {
        Storage::disk('public')->put('gallery/a.png', file_get_contents(UploadFixtures::transparentPng()->getRealPath()));

        $this->get('/files/gallery/a.png')->assertOk();

        expect(PageView::count())->toBe(0);
    });
});

describe('attaching buckets later', function () {
    test('uploads:move-to-buckets carries every database file across and the images keep loading', function () {
        onLaravelCloudWithoutBuckets();

        $this->actingAs($this->admin)
            ->put(route('settings.update'), ['name' => $this->school->name, 'timezone' => 'Africa/Lagos', 'logo' => UploadFixtures::transparentPng()])
            ->assertSessionHasNoErrors();

        $path = $this->school->fresh()->logo_path;
        $bytes = Storage::disk('public')->get($path);

        // Not yet: without buckets it leaves everything where it is.
        $this->artisan('uploads:move-to-buckets')->expectsOutputToContain('not a bucket yet')->assertExitCode(0);
        expect(StoredFile::count())->toBe(1);

        // The buckets are attached and the app redeployed.
        $buckets = UploadFixtures::useObjectStorageLikeDisks();

        $this->artisan('uploads:move-to-buckets')->assertExitCode(0);

        expect(StoredFile::count())->toBe(0)
            ->and(file_get_contents($buckets['public'].'/'.$path))->toBe($bytes)
            ->and($this->school->fresh()->logoUrl())->toBe('https://bucket.example.test/public/'.$path);
    });
});
