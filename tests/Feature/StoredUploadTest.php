<?php

use App\Support\StoredUpload;
use Illuminate\Http\UploadedFile;

test('the extension comes from the content, not the name', function () {
    // A real PNG that claims to be something else. What it IS decides.
    $png = UploadedFile::fake()->image('photo.png');
    $misnamed = new UploadedFile($png->getRealPath(), 'photo.webm', null, null, true);

    expect(StoredUpload::extension($misnamed))->toBe('png');
});

test('a script can never be written to disk under an executable name', function () {
    // The whole point of the finding. Not currently reachable - the mimes
    // rules block it first - but this must hold with no validation at all,
    // because a future upload site may forget one.
    foreach (['shell.php', 'shell.phtml', 'shell.phar', 'shell.pht', 'shell.cgi'] as $name) {
        $file = UploadedFile::fake()->createWithContent($name, '<?php echo "run"; ?>');

        expect(StoredUpload::extension($file))
            ->toBe('bin', "{$name} must not keep an executable extension");
    }
});

test('a script disguised as an image is not stored as a script', function () {
    $file = UploadedFile::fake()->createWithContent('avatar.jpg', '<?php echo "run"; ?>');

    // It may keep .jpg - nothing executes that - but it can never become .php.
    expect(StoredUpload::extension($file))->not->toBe('php');
});

test('an unrecognised file keeps an allowed name from its own extension', function () {
    // finfo cannot identify everything: an empty file, or a format it has no
    // magic for. A correctly uploaded .docx should not lose its name for that.
    $file = UploadedFile::fake()->create('paper.docx', 20);

    expect(StoredUpload::extension($file))->toBe('docx');
});

test('an unrecognised file with an extension we do not accept becomes .bin', function () {
    $file = UploadedFile::fake()->create('archive.rar', 20);

    expect(StoredUpload::extension($file))->toBe('bin');
});

test('the extension is lower-cased', function () {
    $file = UploadedFile::fake()->create('SCAN.PDF', 20);

    expect(StoredUpload::extension($file))->toBe('pdf');
});

test('the stored name is a uuid unless a stem is given', function () {
    $file = UploadedFile::fake()->image('photo.png');

    expect(StoredUpload::name($file))
        ->toMatch('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}\.png$/')
        ->and(StoredUpload::name($file, 'logo-abc123'))
        ->toBe('logo-abc123.png');
});

test('no upload site builds a filename from the client extension any more', function () {
    // The finding was that 19 sites pasted the untrusted extension back on. If
    // one comes back, it should come back as a failing test rather than as a
    // line nobody notices in review.
    $offenders = [];

    $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator(app_path()));

    foreach ($files as $file) {
        if ($file->getExtension() !== 'php') {
            continue;
        }

        $path = $file->getPathname();

        // StoredUpload itself is where the client extension is allowed to be
        // read, as the fallback finfo cannot always replace.
        if (str_ends_with($path, 'StoredUpload.php')) {
            continue;
        }

        $source = file_get_contents($path);

        // Reading it to classify a file is fine; concatenating it into a name
        // is not. The dot before it is what distinguishes the two.
        if (preg_match("/'\\.'\\s*\\.\\s*\\\$\\w+->getClientOriginalExtension\\(\\)/", $source)) {
            $offenders[] = str_replace(base_path().DIRECTORY_SEPARATOR, '', $path);
        }
    }

    expect($offenders)->toBe([]);
});
