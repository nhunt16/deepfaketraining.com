<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/bootstrap.php';

require_login();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

header('Content-Type: application/json');

$script = trim($_POST['script'] ?? '');
$preset = $_POST['voice_preset'] ?? null;

if ($script === '') {
    http_response_code(422);
    echo json_encode(['error' => 'Please enter the voicemail transcript.']);
    exit;
}

if (mb_strlen($script) > 1500) {
    http_response_code(422);
    echo json_encode(['error' => 'Voicemail text is too long. Keep it under 1,500 characters.']);
    exit;
}

try {
    if (!tts_is_available()) {
        throw new RuntimeException('Text-to-Speech SDK not installed. Run composer install.');
    }

    $speakingRate = isset($_POST['speaking_rate']) ? max(0.5, min(2.0, (float)$_POST['speaking_rate'])) : null;
    $pitch = isset($_POST['pitch']) ? max(-10.0, min(10.0, (float)$_POST['pitch'])) : null;
    $audioEncoding = strtoupper(trim($_POST['audio_encoding'] ?? ''));
    $allowedEncodings = ['MP3', 'OGG_OPUS', 'LINEAR16'];
    if (!in_array($audioEncoding, $allowedEncodings, true)) {
        $audioEncoding = null;
    }
    $effectsProfile = trim($_POST['effects_profile'] ?? '');
    $overrides = [
        'speaking_rate' => $speakingRate,
        'pitch' => $pitch,
        'audio_encoding' => $audioEncoding,
        'effects_profile' => $effectsProfile,
    ];

    $audio = generate_voicemail_audio($script, $preset, $overrides);
    echo json_encode([
        'mime' => $audio['mime'],
        'audio' => $audio['audio'],
    ]);
} catch (Throwable $e) {
    http_response_code(500);
    error_log('TTS generation failed: ' . $e->getMessage() . "\n" . $e->getTraceAsString());
    echo json_encode(['error' => 'Unable to synthesize audio. Check server logs for details.']);
}

