<?php

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
    | Uploads Disk
    |--------------------------------------------------------------------------
    |
    | This disk is specifically used for file uploads (profile photos,
    | opportunity photos, etc.) via the FileUploadService.
    | Use 'cloud' for Laravel Cloud R2 storage, 'public' for local storage.
    |
    */

    'uploads_disk' => env('FILESYSTEM_UPLOADS_DISK', 'cloud'),

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

        'local' => [
            'driver' => 'local',
            'root' => storage_path('app/private'),
            'serve' => true,
            'throw' => false,
            'report' => false,
        ],

        'public' => [
            'driver' => 'local',
            'root' => storage_path('app/public'),
            'url' => rtrim(env('APP_URL', 'http://localhost'), '/').'/storage',
            'visibility' => 'public',
            'throw' => false,
            'report' => false,
        ],

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

        /*
        |--------------------------------------------------------------------------
        | Laravel Cloud R2 Storage (Cloudflare)
        |--------------------------------------------------------------------------
        |
        | This disk is configured for Laravel Cloud's R2 storage service.
        | Files are publicly accessible via the configured URL.
        |
        */
        'cloud' => [
            'driver' => 's3',
            'key' => env('LARAVEL_CLOUD_ACCESS_KEY_ID'),
            'secret' => env('LARAVEL_CLOUD_ACCESS_KEY_SECRET'),
            'region' => env('LARAVEL_CLOUD_DEFAULT_REGION', 'auto'),
            'bucket' => env('LARAVEL_CLOUD_BUCKET'),
            'url' => env('LARAVEL_CLOUD_URL'),
            'endpoint' => env('LARAVEL_CLOUD_ENDPOINT'),
            'use_path_style_endpoint' => env('LARAVEL_CLOUD_USE_PATH_STYLE_ENDPOINT', false),
            'visibility' => 'public',
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
    */

    'links' => [
        public_path('storage') => storage_path('app/public'),
    ],

];
