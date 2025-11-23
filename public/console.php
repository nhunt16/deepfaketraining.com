<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/bootstrap.php';

require_login();

const CONSOLE_STATE_KEY = 'mission_console';

function console_default_state(): array
{
    return [
        'cwd' => '/',
        'missions_unlocked' => 0,
        'intel_flag' => false,
    ];
}

function console_state(): array
{
    if (!isset($_SESSION[CONSOLE_STATE_KEY])) {
        $_SESSION[CONSOLE_STATE_KEY] = console_default_state();
    }

    return $_SESSION[CONSOLE_STATE_KEY];
}

function console_save_state(array $state): void
{
    $_SESSION[CONSOLE_STATE_KEY] = $state;
}

function console_reset(): array
{
    $state = console_default_state();
    console_save_state($state);
    return $state;
}

function handle_console_command(string $command, array $state): array
{
    $command = trim($command);
    $output = '';
    $clear = false;

    if ($command === '') {
        return ['state' => $state, 'output' => '', 'clear' => false];
    }

    $rootFiles = [
        'README.txt',
        'intel',
        'logs',
    ];

    $intelFiles = [
        'ops_report.txt',
        'payload.bin',
    ];

    $logFiles = [
        'beacon.log',
        'system.log',
    ];

    switch (true) {
        case $command === 'help':
            $output = "Available commands:
  help              Show this list
  ls / pwd          Inspect the simulated filesystem
  cd <dir>          Move between intel/ logs
  cat <file>        Read the contents of a file
  hint              Receive guidance from HQ
  reset             Restore the sandbox state
  clear             Wipe the terminal output";
            break;
        case $command === 'clear':
            $clear = true;
            $output = "Screen cleared.";
            break;
        case $command === 'reset':
            $state = console_reset();
            $output = "Sandbox reset. Starting from /";
            break;
        case $command === 'pwd':
            $output = $state['cwd'];
            break;
        case $command === 'ls':
            if ($state['cwd'] === '/') {
                $output = implode("\n", $rootFiles);
            } elseif ($state['cwd'] === '/intel') {
                $output = implode("\n", $intelFiles);
            } elseif ($state['cwd'] === '/logs') {
                $output = implode("\n", $logFiles);
            }
            break;
        case str_starts_with($command, 'cd'):
            $parts = preg_split('/\s+/', $command);
            $target = $parts[1] ?? '';
            if ($target === '' || $target === '/') {
                $state['cwd'] = '/';
                $output = 'Navigated to /';
            } elseif ($target === '..') {
                $state['cwd'] = '/';
                $output = 'Navigated to /';
            } elseif ($target === 'intel' || $target === '/intel') {
                $state['cwd'] = '/intel';
                $output = 'Navigated to /intel';
            } elseif ($target === 'logs' || $target === '/logs') {
                $state['cwd'] = '/logs';
                $output = 'Navigated to /logs';
            } else {
                $output = "cd: {$target}: No such directory";
            }
            break;
        case str_starts_with($command, 'cat'):
            $parts = preg_split('/\s+/', $command, 2);
            $file = $parts[1] ?? '';
            if ($file === '') {
                $output = 'Usage: cat <file>';
                break;
            }

            if ($state['cwd'] === '/' && strcasecmp($file, 'README.txt') === 0) {
                $output = ">>> NODE BRIEFING

Intercepted host is seeded with synthetic artifacts. Sweep directories intel/ and logs/ to find the signal phrase.";
            } elseif ($state['cwd'] === '/intel' && strcasecmp($file, 'ops_report.txt') === 0) {
                $output = "Field Team: Voice clone surfaced on executive bridge call.
Countermeasure: Deploy passphrase 'aurora vector' only after verifying multi-channel metadata.";
            } elseif ($state['cwd'] === '/intel' && strcasecmp($file, 'payload.bin') === 0) {
                $output = base64_encode('This is a harmless mock payload used for the exercise.');
            } elseif ($state['cwd'] === '/logs' && strcasecmp($file, 'beacon.log') === 0) {
                $state['intel_flag'] = true;
                $output = "[00:01] inbound-signal -> KEYWORD: AURORA VECTOR
[00:02] anomaly detected: request wire transfer
[00:03] action: escalate to human verification

## Mission Complete: You have identified the safeguard phrase.";
            } elseif ($state['cwd'] === '/logs' && strcasecmp($file, 'system.log') === 0) {
                $output = "systemd[1]: Starting synthetic comms recorder...
detector: probability of spoofed inflection spikes at 0.91";
            } else {
                $output = "cat: {$file}: No such file in {$state['cwd']}";
            }
            break;
        case $command === 'hint':
            if ($state['cwd'] === '/') {
                $output = 'Hint: pivot into intel/ first, then inspect logs/';
            } elseif ($state['cwd'] === '/intel') {
                $output = 'Hint: Copy the phrase from ops_report.txt and look for a confirmation inside logs/';
            } else {
                $output = 'Hint: Search for beacon activity. Anything referencing the keyword is valuable.';
            }
            break;
        default:
            $output = "bash: {$command}: command not found";
            break;
    }

    return ['state' => $state, 'output' => $output, 'clear' => $clear];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');
    $command = $_POST['command'] ?? '';
    $state = console_state();
    $result = handle_console_command($command, $state);
    console_save_state($result['state']);
    echo json_encode([
        'output' => $result['output'],
        'clear' => $result['clear'],
    ]);
    exit;
}

$state = console_state();
$voicePresets = $config['app']['tts_presets'] ?? [];
$defaultVoiceName = $config['app']['tts_voice']['name'] ?? '';
$defaultVoiceConfig = $config['app']['tts_voice'] ?? [];
$defaultPresetKey = null;
foreach ($voicePresets as $key => $preset) {
    if (!empty($preset['name']) && $preset['name'] === $defaultVoiceName) {
        $defaultPresetKey = $key;
        $defaultVoiceConfig = array_replace($defaultVoiceConfig, $preset);
        break;
    }
}
$defaultSpeakingRate = $defaultVoiceConfig['speaking_rate'] ?? 1.0;
$defaultPitch = $defaultVoiceConfig['pitch'] ?? 0.0;
$defaultEncoding = strtoupper($defaultVoiceConfig['audio_encoding'] ?? 'MP3');
$defaultEffectsProfile = $defaultVoiceConfig['effects_profile'] ?? 'telephony-class-application';

render_header('Mission Console');
?>
<section class="panel console-wrapper">
    <div>
        <h1>Mission Console</h1>
        <p>
            Practice reasoning through suspicious infrastructure by using this contained
            terminal. Nothing you type reaches a real machine&mdash;responses are scripted to
            reinforce investigative instincts.
        </p>
        <ul>
            <li>Use <code>help</code>, <code>ls</code>, <code>cat</code>, and <code>hint</code>.</li>
            <li>Find the safeguard phrase hidden in the logs.</li>
            <li><code>reset</code> will restore the sandbox.</li>
        </ul>
    </div>
    <div class="terminal-card">
        <div class="terminal-toolbar">
            <span class="indicator"></span>
            <span class="indicator yellow"></span>
            <span class="indicator green"></span>
            <span class="terminal-title">synthetic-node</span>
        </div>
        <div id="console-terminal" class="terminal-window" aria-label="Training terminal"></div>
    </div>
</section>
<section class="panel voicemail-card">
    <div>
        <h2>Generate Voicemail</h2>
        <p>
            Feed the AI voice actor with your own script to simulate phishing voicemails.
            We use Google Cloud Text-to-Speech, so make sure your account is permitted to call the API.
        </p>
    </div>
    <form id="voicemail-form" class="voicemail-form">
        <label for="voicemail-script">Voicemail transcript</label>
        <textarea id="voicemail-script" name="script" maxlength="1500" required placeholder="Example: This is Finance. I need that transfer executed in the next 15 minutes..."></textarea>
        <?php if ($voicePresets): ?>
            <label for="voicemail-voice">Voice model</label>
            <select id="voicemail-voice" name="voice_preset">
                <?php foreach ($voicePresets as $key => $preset): ?>
                    <?php
                    $selected = '';
                    if (!empty($preset['name']) && $preset['name'] === $defaultVoiceName) {
                        $selected = 'selected';
                    }
                    $presetRate = $preset['speaking_rate'] ?? $defaultSpeakingRate;
                    $presetPitch = $preset['pitch'] ?? $defaultPitch;
                    $presetEncoding = strtoupper($preset['audio_encoding'] ?? $defaultEncoding);
                    $presetEffects = $preset['effects_profile'] ?? $defaultEffectsProfile;
                    ?>
                    <option
                        value="<?= h($key) ?>"
                        data-description="<?= h($preset['label'] ?? $preset['name'] ?? $key) ?>"
                        data-rate="<?= h((string)$presetRate) ?>"
                        data-pitch="<?= h((string)$presetPitch) ?>"
                        data-encoding="<?= h($presetEncoding) ?>"
                        data-effects="<?= h($presetEffects) ?>"
                        <?= $selected ?>
                    >
                        <?= h($preset['label'] ?? $preset['name'] ?? $key) ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <p id="voicemail-voice-info" class="voicemail-status" aria-live="polite">
                <?= h(reset($voicePresets)['label'] ?? 'Voice preset applied.'); ?>
            </p>
        <?php endif; ?>
        <details class="voicemail-advanced">
            <summary>Advanced controls</summary>
            <div class="voicemail-advanced-grid">
                <label>
                    Speaking rate (0.7 - 1.3)
                    <input type="number" step="0.01" min="0.7" max="1.3" id="voicemail-rate" name="speaking_rate" value="<?= h((string)$defaultSpeakingRate) ?>">
                </label>
                <label>
                    Pitch (-10 to 10)
                    <input type="number" step="0.1" min="-10" max="10" id="voicemail-pitch" name="pitch" value="<?= h((string)$defaultPitch) ?>">
                </label>
                <label>
                    Audio encoding
                    <select id="voicemail-encoding" name="audio_encoding">
                        <option value="MP3" <?= $defaultEncoding === 'MP3' ? 'selected' : '' ?>>MP3</option>
                        <option value="OGG_OPUS" <?= $defaultEncoding === 'OGG_OPUS' ? 'selected' : '' ?>>OGG/Opus</option>
                        <option value="LINEAR16" <?= $defaultEncoding === 'LINEAR16' ? 'selected' : '' ?>>LINEAR16 (WAV)</option>
                    </select>
                </label>
                <label>
                    Effects profile
                    <select id="voicemail-effects" name="effects_profile">
                        <option value="" <?= $defaultEffectsProfile === '' ? 'selected' : '' ?>>Default</option>
                        <option value="telephony-class-application" <?= $defaultEffectsProfile === 'telephony-class-application' ? 'selected' : '' ?>>Telephony</option>
                        <option value="wearable-class-device" <?= $defaultEffectsProfile === 'wearable-class-device' ? 'selected' : '' ?>>Wearable</option>
                        <option value="handset-class-device" <?= $defaultEffectsProfile === 'handset-class-device' ? 'selected' : '' ?>>Handset</option>
                    </select>
                </label>
            </div>
        </details>
        <div class="voicemail-actions">
            <button type="submit">Generate voicemail</button>
            <span id="voicemail-status" class="voicemail-status"></span>
        </div>
        <audio id="voicemail-audio" controls hidden></audio>
    </form>
</section>
<script src="https://cdn.jsdelivr.net/npm/xterm@5.3.0/lib/xterm.min.js"></script>
<script>
(() => {
    const term = new window.Terminal({
        theme: {
            background: '#05060a',
            foreground: '#e2f3ff',
            cursor: '#00ffc6',
        },
        fontSize: 14,
        rows: 18,
        convertEol: true,
    });
    const container = document.getElementById('console-terminal');
    term.open(container);
    const intro = [
        'Deepfake Defense :: Synthetic Node 77-B',
        'Type "help" to list training commands.'
    ];
    intro.forEach(line => term.writeln(line));

    let buffer = '';
    const prompt = () => {
        term.write('\r\n> ');
        buffer = '';
    };
    prompt();

    term.onKey(({key, domEvent}) => {
        const printable = !domEvent.altKey && !domEvent.ctrlKey && !domEvent.metaKey;
        if (domEvent.key === 'Enter') {
            const command = buffer.trim();
            term.writeln('');
            if (command.length === 0) {
                prompt();
                return;
            }
            term.writeln('> ' + command);
            window.fetch('/console.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: new URLSearchParams({command}),
            })
                .then((resp) => resp.json())
                .then((data) => {
                    if (data.clear) {
                        term.clear();
                    }
                    if (data.output) {
                        term.writeln(data.output);
                    }
                    prompt();
                })
                .catch(() => {
                    term.writeln('Error: unable to reach the training host.');
                    prompt();
                });
        } else if (domEvent.key === 'Backspace') {
            if (buffer.length > 0) {
                term.write('\b \b');
                buffer = buffer.slice(0, -1);
            }
        } else if (printable && key.length === 1) {
            buffer += key;
            term.write(key);
        }
    });
})();
</script>
<script>
(() => {
    const form = document.getElementById('voicemail-form');
    if (!form) return;
    const textarea = document.getElementById('voicemail-script');
    const statusEl = document.getElementById('voicemail-status');
    const audioEl = document.getElementById('voicemail-audio');
    const submitBtn = form.querySelector('button[type="submit"]');
    const voiceSelect = document.getElementById('voicemail-voice');
    const voiceInfo = document.getElementById('voicemail-voice-info');
    const rateInput = document.getElementById('voicemail-rate');
    const pitchInput = document.getElementById('voicemail-pitch');
    const encodingSelect = document.getElementById('voicemail-encoding');
    const effectsSelect = document.getElementById('voicemail-effects');

    const applyPresetSettings = () => {
        if (!voiceSelect) return;
        const option = voiceSelect.options[voiceSelect.selectedIndex];
        if (!option) return;
        if (voiceInfo) {
            voiceInfo.textContent = option.dataset.description || '';
        }
        if (rateInput && option.dataset.rate) {
            rateInput.value = option.dataset.rate;
        }
        if (pitchInput && option.dataset.pitch) {
            pitchInput.value = option.dataset.pitch;
        }
        if (encodingSelect && option.dataset.encoding) {
            encodingSelect.value = option.dataset.encoding;
        }
        if (effectsSelect && typeof option.dataset.effects !== 'undefined') {
            effectsSelect.value = option.dataset.effects;
        }
    };

    if (voiceSelect) {
        applyPresetSettings();
        voiceSelect.addEventListener('change', applyPresetSettings);
    }

    form.addEventListener('submit', (event) => {
        event.preventDefault();
        const script = textarea.value.trim();
        if (!script) {
            statusEl.textContent = 'Please enter a voicemail script.';
            return;
        }
        submitBtn.disabled = true;
        statusEl.textContent = 'Synthesizing via Google Cloud Text-to-Speech...';
        audioEl.hidden = true;
        const payload = {
            script,
            speaking_rate: document.getElementById('voicemail-rate')?.value || '1.0',
            pitch: document.getElementById('voicemail-pitch')?.value || '0',
            audio_encoding: document.getElementById('voicemail-encoding')?.value || 'MP3',
            effects_profile: document.getElementById('voicemail-effects')?.value || '',
        };
        if (voiceSelect) {
            payload.voice_preset = voiceSelect.value;
        }
        window.fetch('/tts.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: new URLSearchParams(payload),
        })
            .then((resp) => resp.json())
            .then((data) => {
                if (data.error) {
                    statusEl.textContent = data.error;
                    return;
                }
                const src = `data:${data.mime};base64,${data.audio}`;
                audioEl.src = src;
                audioEl.hidden = false;
                audioEl.play().catch(() => {});
                statusEl.textContent = 'Voicemail ready.';
            })
            .catch(() => {
                statusEl.textContent = 'Unable to reach the TTS service.';
            })
            .finally(() => {
                submitBtn.disabled = false;
            });
    });
})();
</script>
<?php
render_footer();

