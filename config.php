<?php
declare(strict_types=1);

$config = [
    'app' => [
        'name' => 'Deepfake Defense Training',
        'default_video_url' => 'https://videos.pexels.com/video-files/3130449/3130449-uhd_2560_1440_25fps.mp4',
        'beta_key' => 'BetaKeyChangeMe',
        'tts_voice' => [
            'language_code' => 'en-US',
            'name' => 'en-US-Neural2-C',
            'audio_encoding' => 'MP3',
            'speaking_rate' => 1.0,
            'pitch' => 0.0,
            'effects_profile' => 'telephony-class-application',
        ],
        'google_credentials_path' => '/opt/keys/deepfake-tts.json',
        'tts_presets' => [
            'neural2c' => [
                'label' => 'Neural2-C · Calm female',
                'name' => 'en-US-Neural2-C',
                'speaking_rate' => 0.98,
                'pitch' => -1.0,
                'effects_profile' => 'telephony-class-application',
            ],
            'neural2i' => [
                'label' => 'Neural2-I · Confident female',
                'name' => 'en-US-Neural2-I',
                'speaking_rate' => 1.05,
                'pitch' => 0.0,
                'effects_profile' => 'telephony-class-application',
            ],
            'wavenetd' => [
                'label' => 'WaveNet-D · Neutral male',
                'name' => 'en-US-Wavenet-D',
                'speaking_rate' => 0.96,
                'pitch' => -2.0,
                'effects_profile' => 'telephony-class-application',
                'audio_encoding' => 'MP3',
            ],
            'chirp3-leda' => [
                'label' => 'Chirp3 HD · Leda',
                'name' => 'en-US-Chirp3-HD-Leda',
                'speaking_rate' => 1.0,
                'pitch' => 0.0,
                'effects_profile' => 'telephony-class-application',
                'audio_encoding' => 'LINEAR16',
            ],
        ],
    ],
    'db' => [
        'host' => getenv('DB_HOST') ?: '127.0.0.1',
        'port' => getenv('DB_PORT') ?: '3306',
        'name' => getenv('DB_NAME') ?: 'deepfake_training',
        'user' => getenv('DB_USER') ?: 'root',
        'pass' => getenv('DB_PASS') ?: '',
        'charset' => 'utf8mb4',
    ],
];

$localConfigPath = __DIR__ . '/config.local.php';
if (is_file($localConfigPath)) {
    $overrides = require $localConfigPath;
    if (is_array($overrides)) {
        $config = array_replace_recursive($config, $overrides);
    }
}

return $config;

