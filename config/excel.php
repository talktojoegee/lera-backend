<?php

return [
    'temporary_files' => [
        // Use a writable OS temp path for queue workers on shared servers.
        'local_path' => env('EXCEL_TEMP_PATH', sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'laravel-excel'),
        'local_permissions' => [
            'dir' => 0775,
            'file' => 0664,
        ],
        'remote_disk' => null,
        'remote_prefix' => null,
        'force_resync_remote' => null,
    ],
];

