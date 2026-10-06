<?php

return [
    // In kilobytes, as expected by Laravel's file size validation.
    'max_upload_kb' => env('BC_DOCUMENT_MAX_UPLOAD_KB', 5120),
];
