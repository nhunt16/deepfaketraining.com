<?php
declare(strict_types=1);

use Google\Cloud\TextToSpeech\V1\AudioConfig;
use Google\Cloud\TextToSpeech\V1\AudioEncoding;
use Google\Cloud\TextToSpeech\V1\Client\TextToSpeechClient;
use Google\Cloud\TextToSpeech\V1\SynthesisInput;
use Google\Cloud\TextToSpeech\V1\SynthesizeSpeechRequest;
use Google\Cloud\TextToSpeech\V1\VoiceSelectionParams;

function tts_is_available(): bool
{
    return class_exists(TextToSpeechClient::class);
}

function tts_preset(string $key = null): array
{
    global $config;
    $base = $config['app']['tts_voice'] ?? [];
    $presets = $config['app']['tts_presets'] ?? [];

    if ($key && isset($presets[$key])) {
        return array_merge($base, $presets[$key]);
    }

    return $base;
}

function generate_voicemail_audio(string $text, ?string $presetKey = null, array $overrides = []): array
{
    if (!tts_is_available()) {
        throw new RuntimeException('Text-to-Speech SDK is not installed. Run composer install.');
    }

    $voiceConfig = tts_preset($presetKey);
    foreach ($overrides as $key => $value) {
        if ($value === null || $value === '') {
            continue;
        }
        $voiceConfig[$key] = $value;
    }

    $languageCode = $voiceConfig['language_code'] ?? 'en-US';
    $voiceName = $voiceConfig['name'] ?? '';
    $encoding = strtoupper($voiceConfig['audio_encoding'] ?? 'MP3');
    $speakingRate = (float)($voiceConfig['speaking_rate'] ?? 1.0);
    $pitch = isset($voiceConfig['pitch']) ? (float)$voiceConfig['pitch'] : 0.0;
    $effectsProfile = $voiceConfig['effects_profile'] ?? null;

    $encodingMap = [
        'MP3' => AudioEncoding::MP3,
        'OGG_OPUS' => AudioEncoding::OGG_OPUS,
        'LINEAR16' => AudioEncoding::LINEAR16,
    ];

    $audioEncoding = $encodingMap[$encoding] ?? AudioEncoding::MP3;

    $client = new TextToSpeechClient();
    $inputText = (new SynthesisInput())->setText($text);

    $voice = (new VoiceSelectionParams())
        ->setLanguageCode($languageCode);

    if ($voiceName !== '') {
        $voice->setName($voiceName);
    }

    $audioConfig = (new AudioConfig())
        ->setAudioEncoding($audioEncoding)
        ->setSpeakingRate($speakingRate)
        ->setPitch($pitch);

    if ($effectsProfile) {
        $audioConfig->setEffectsProfileId([$effectsProfile]);
    }

    $request = (new SynthesizeSpeechRequest())
        ->setInput($inputText)
        ->setVoice($voice)
        ->setAudioConfig($audioConfig);

    $response = $client->synthesizeSpeech($request);
    $client->close();

    $mime = match ($audioEncoding) {
        AudioEncoding::OGG_OPUS => 'audio/ogg',
        AudioEncoding::LINEAR16 => 'audio/wav',
        default => 'audio/mpeg',
    };

    return [
        'mime' => $mime,
        'audio' => base64_encode($response->getAudioContent()),
    ];
}

