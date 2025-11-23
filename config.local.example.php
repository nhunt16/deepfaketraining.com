<?php
declare(strict_types=1);

/**
 * Copy this file to config.local.php and update the values below
 * to override any environment-specific settings (database, beta key, etc.).
 */
return [
    'app' => [
        'beta_key' => 'ChangeBeforeProd',
    ],
    'db' => [
        'host' => '127.0.0.1',
        'port' => '3306',
        'name' => 'deepfake_training',
        'user' => 'deepfake_app',
        'pass' => 'super-secure-password',
    ],
];

