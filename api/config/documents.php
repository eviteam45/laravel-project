<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Document storage disk
    |--------------------------------------------------------------------------
    |
    | The filesystem disk (config/filesystems.php) that uploaded documents are
    | stored on and streamed from. Defaults to the private "local" disk; set
    | DOCUMENTS_DISK=s3 (etc.) to move document storage without code changes.
    |
    */
    'disk' => env('DOCUMENTS_DISK', 'local'),
];
