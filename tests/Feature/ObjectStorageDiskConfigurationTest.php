<?php

/**
 * The "public" and "local" disks are named directly in roughly eighty places.
 * Production runs on an ephemeral filesystem and has to back both with object
 * storage; a laptop and this test suite must keep writing to storage/.
 *
 * These tests read config/filesystems.php the way the framework does, so a
 * change that silently sends local uploads to S3 - or, far worse, leaves
 * production writing to a disk that is wiped on every deploy - fails here.
 */

/**
 * Read config/filesystems.php with a specific set of environment variables in
 * place, and restore the environment afterwards.
 *
 * @param  array<string, string>  $environment
 * @return array{disks: array<string, array<string, mixed>>}
 */
function filesystemsConfiguredWith(array $environment): array
{
    $original = [];

    foreach ($environment as $key => $value) {
        $original[$key] = $_ENV[$key] ?? null;
        $_ENV[$key] = $_SERVER[$key] = $value;
        putenv("{$key}={$value}");
    }

    try {
        return require base_path('config/filesystems.php');
    } finally {
        foreach ($original as $key => $value) {
            if ($value === null) {
                unset($_ENV[$key], $_SERVER[$key]);
                putenv($key);

                continue;
            }

            $_ENV[$key] = $_SERVER[$key] = $value;
            putenv("{$key}={$value}");
        }
    }
}

it('keeps both application disks on the local filesystem when nothing is configured', function () {
    $disks = filesystemsConfiguredWith([])['disks'];

    expect($disks['local']['driver'])->toBe('local')
        ->and($disks['local']['root'])->toBe(storage_path('app/private'))
        ->and($disks['local']['serve'])->toBeTrue()
        ->and($disks['public']['driver'])->toBe('local')
        ->and($disks['public']['root'])->toBe(storage_path('app/public'))
        ->and($disks['public']['visibility'])->toBe('public');
});

it('moves the public disk to object storage when its driver says so', function () {
    $disks = filesystemsConfiguredWith([
        'PUBLIC_FILESYSTEM_DRIVER' => 's3',
        'PUBLIC_AWS_ACCESS_KEY_ID' => 'public-key',
        'PUBLIC_AWS_SECRET_ACCESS_KEY' => 'public-secret',
        'PUBLIC_AWS_BUCKET' => 'akademicnest-public',
        'PUBLIC_AWS_URL' => 'https://public.example.r2.dev',
        'PUBLIC_AWS_ENDPOINT' => 'https://account.r2.cloudflarestorage.com',
    ])['disks'];

    expect($disks['public']['driver'])->toBe('s3')
        ->and($disks['public']['bucket'])->toBe('akademicnest-public')
        ->and($disks['public']['url'])->toBe('https://public.example.r2.dev')
        ->and($disks['public']['endpoint'])->toBe('https://account.r2.cloudflarestorage.com')
        ->and($disks['public']['region'])->toBe('auto');
});

it('moves the private disk to object storage independently of the public one', function () {
    $disks = filesystemsConfiguredWith([
        'PRIVATE_FILESYSTEM_DRIVER' => 's3',
        'PRIVATE_AWS_BUCKET' => 'akademicnest-private',
        'PRIVATE_AWS_ENDPOINT' => 'https://account.r2.cloudflarestorage.com',
    ])['disks'];

    expect($disks['local']['driver'])->toBe('s3')
        ->and($disks['local']['bucket'])->toBe('akademicnest-private')
        ->and($disks['public']['driver'])->toBe('local');
});

/**
 * R2 decides visibility per bucket and answers a per-object ACL with
 * "NotImplemented", so an upload to a disk carrying this key fails outright.
 */
it('never sends a per-object visibility to object storage', function () {
    $disks = filesystemsConfiguredWith([
        'PUBLIC_FILESYSTEM_DRIVER' => 's3',
        'PRIVATE_FILESYSTEM_DRIVER' => 's3',
    ])['disks'];

    expect($disks['public'])->not->toHaveKey('visibility')
        ->and($disks['local'])->not->toHaveKey('visibility');
});

/**
 * Every disk definition has to survive var_export() or `config:cache` - which
 * PRODUCTION.md tells you to run after deploying - fatals on a closure.
 */
it('stays safe to cache', function () {
    $config = filesystemsConfiguredWith([
        'PUBLIC_FILESYSTEM_DRIVER' => 's3',
        'PRIVATE_FILESYSTEM_DRIVER' => 's3',
    ]);

    expect(fn () => var_export($config, true))->not->toThrow(Throwable::class)
        ->and(var_export($config, true))->not->toContain('Closure');
});
