<?php
declare(strict_types=1);

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start([
        'cookie_secure' => false,
        'cookie_httponly' => true,
        'use_strict_mode' => true,
        'cookie_samesite' => 'Lax',
    ]);
}

$config = require __DIR__ . '/../config.php';

$vendorAutoload = __DIR__ . '/../vendor/autoload.php';
if (file_exists($vendorAutoload)) {
    require_once $vendorAutoload;
}

$credentialsPath = $config['app']['google_credentials_path'] ?? null;
if ($credentialsPath && is_readable($credentialsPath) && empty(getenv('GOOGLE_APPLICATION_CREDENTIALS'))) {
    putenv("GOOGLE_APPLICATION_CREDENTIALS={$credentialsPath}");
}

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/layout.php';
require_once __DIR__ . '/tts.php';
require_once __DIR__ . '/beta_gate.php';

enforce_beta_access();

