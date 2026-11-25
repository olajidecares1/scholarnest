<?php

use App\Services\Uploads\UploadStorage;
use App\Support\Storage\DatabaseStorageFallback;
use Illuminate\Support\Facades\Storage;

/**
 * On Laravel Cloud the upload disks are not folders, so a picture dompdf
 * needs is copied to a real file first (UploadStorage::localPath()). In a
 * long-running process those copies are remembered between requests. One
 * remembered for over an hour was handed out, then deleted by the clean-up
 * the next copy ran, before dompdf could open it: every report card in a
 * class failed with "unable to open image" on the school's crest.
 */
beforeEach(function () {
    config(['filesystems.disks.public' => DatabaseStorageFallback::configFor('public'), 'filesystems.disks.local' => DatabaseStorageFallback::configFor('local')]);
    Storage::forgetDisk(['public', 'local']);
    UploadStorage::releaseLocalCopies();
});

afterEach(fn () => UploadStorage::releaseLocalCopies());

test('a copy handed out again is not deleted by the next copy\'s clean-up, however old', function () {
    Storage::disk('public')->put('logos/crest.png', 'crest bytes');
    Storage::disk('local')->put('photos/pupil.jpg', 'photo bytes');
    $storage = app(UploadStorage::class);

    $crest = $storage->localPath('public', 'logos/crest.png');

    // Remembered from a request over an hour ago.
    touch($crest, time() - 7200);

    expect($storage->localPath('public', 'logos/crest.png'))->toBe($crest);

    // The pupil's photo is copied next; its clean-up must leave the crest.
    $storage->localPath('local', 'photos/pupil.jpg');

    expect(is_file($crest))->toBeTrue()
        ->and(file_get_contents($crest))->toBe('crest bytes');
});

test('an old copy nobody is using is still cleaned up', function () {
    Storage::disk('local')->put('photos/pupil.jpg', 'photo bytes');
    $directory = storage_path('framework/cache/stored-files');
    @mkdir($directory, 0777, true);
    $orphan = $directory.'/orphan-from-yesterday.png';
    file_put_contents($orphan, 'old');
    touch($orphan, time() - 7200);

    app(UploadStorage::class)->localPath('local', 'photos/pupil.jpg');

    expect(is_file($orphan))->toBeFalse();
});
