<?php

/*
|--------------------------------------------------------------------------
| Where the two application disks actually live
|--------------------------------------------------------------------------
|
| This application writes to exactly two disks by name. "public" holds what a
| browser is meant to see - school logos, ID card photographs, favicons.
| "local" holds what it must never see - payment receipts carrying payer names
| and bank details, CBT source documents, passport photographs.
|
| On a single server both are directories under storage/. On a platform whose
| filesystem is ephemeral - Laravel Cloud, where the disk is reset by every
| deployment and each replica has its own copy - a directory under storage/
| means every upload is gone at the next deploy. There they must be object
| storage instead.
|
| The switch is per disk and defaults to "local", so a laptop and the test
| suite behave exactly as they did before with none of these variables set.
|
| Cloudflare R2 - what Laravel Object Storage is built on - decides visibility
| per bucket, not per object, and answers a per-object ACL with
| "NotImplemented". That is why neither disk below sends a "visibility" key,
| and why the two disks need two separate buckets rather than one.
|
*/

$publicDisk = env('PUBLIC_FILESYSTEM_DRIVER', 'local') === 's3'
    ? [
        'driver' => 's3',
        'key' => env('PUBLIC_AWS_ACCESS_KEY_ID'),
        'secret' => env('PUBLIC_AWS_SECRET_ACCESS_KEY'),
        'region' => env('PUBLIC_AWS_DEFAULT_REGION', 'auto'),
        'bucket' => env('PUBLIC_AWS_BUCKET'),
        'url' => env('PUBLIC_AWS_URL'),
        'endpoint' => env('PUBLIC_AWS_ENDPOINT'),
        'use_path_style_endpoint' => env('PUBLIC_AWS_USE_PATH_STYLE_ENDPOINT', false),
        'throw' => false,
        'report' => false,
    ]
    : [
        'driver' => 'local',
        'root' => storage_path('app/public'),
        'url' => rtrim(env('APP_URL', 'http://localhost'), '/').'/storage',
        'visibility' => 'public',
        'throw' => false,
        'report' => false,
    ];

$privateDisk = env('PRIVATE_FILESYSTEM_DRIVER', 'local') === 's3'
    ? [
        'driver' => 's3',
        'key' => env('PRIVATE_AWS_ACCESS_KEY_ID'),
        'secret' => env('PRIVATE_AWS_SECRET_ACCESS_KEY'),
        'region' => env('PRIVATE_AWS_DEFAULT_REGION', 'auto'),
        'bucket' => env('PRIVATE_AWS_BUCKET'),
        'endpoint' => env('PRIVATE_AWS_ENDPOINT'),
        'use_path_style_endpoint' => env('PRIVATE_AWS_USE_PATH_STYLE_ENDPOINT', false),
        'throw' => false,
        'report' => false,
    ]
    : [
        'driver' => 'local',
        'root' => storage_path('app/private'),
        'serve' => true,
        'throw' => false,
        'report' => false,
    ];

return [

    /*
    |--------------------------------------------------------------------------
    | Default Filesystem Disk
    |--------------------------------------------------------------------------
    |
    | Here you may specify the default filesystem disk that should be used
    | by the framework. The "local" disk, as well as a variety of cloud
    | based disks are available to your application for file storage.
    |
    */

    'default' => env('FILESYSTEM_DISK', 'local'),

    /*
    |--------------------------------------------------------------------------
    | Filesystem Disks
    |--------------------------------------------------------------------------
    |
    | Below you may configure as many filesystem disks as necessary, and you
    | may even configure multiple disks for the same driver. Examples for
    | most supported storage drivers are configured here for reference.
    |
    | Supported drivers: "local", "ftp", "sftp", "s3"
    |
    */

    'disks' => [

        'local' => $privateDisk,

        'public' => $publicDisk,

        's3' => [
            'driver' => 's3',
            'key' => env('AWS_ACCESS_KEY_ID'),
            'secret' => env('AWS_SECRET_ACCESS_KEY'),
            'region' => env('AWS_DEFAULT_REGION'),
            'bucket' => env('AWS_BUCKET'),
            'url' => env('AWS_URL'),
            'endpoint' => env('AWS_ENDPOINT'),
            'use_path_style_endpoint' => env('AWS_USE_PATH_STYLE_ENDPOINT', false),
            'throw' => false,
            'report' => false,
        ],

    ],

    /*
    |--------------------------------------------------------------------------
    | Symbolic Links
    |--------------------------------------------------------------------------
    |
    | Here you may configure the symbolic links that will be created when the
    | `storage:link` Artisan command is executed. The array keys should be
    | the locations of the links and the values should be their targets.
    |
    | Only meaningful when the public disk is a local directory. Object storage
    | is reached over HTTP and has nothing to link to - which is why Laravel
    | Cloud's own documentation says not to run `storage:link` there.
    |
    */

    'links' => [
        public_path('storage') => storage_path('app/public'),
    ],

];
