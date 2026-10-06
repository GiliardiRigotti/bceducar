<?php

return [
    'days' => env('BC_DOCUMENT_RETENTION_DAYS'),
    'approved' => env('BC_DOCUMENT_RETENTION_APPROVED', false),
    'reference' => env('BC_DOCUMENT_RETENTION_POLICY_REFERENCE'),
];
