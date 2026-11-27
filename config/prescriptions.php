<?php

return [
    // Enable only for transient reservation copies, with required pharmacy records retained separately.
    'purge_enabled' => env('PRESCRIPTION_IMAGE_PURGE_ENABLED', false),
    'retention_days' => max(3, (int) env('PRESCRIPTION_IMAGE_RETENTION_DAYS', 3)),
];
