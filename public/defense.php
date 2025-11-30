<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/bootstrap.php';

require_login();

$user = current_user();
$modules = defense_modules();
$progress = defense_progress_get_all((int)$user['id']);

if (!isset($modules['audio'], $modules['video'])) {
    throw new RuntimeException('Defense modules missing expected entries.');
}

$moduleKeyAudio = 'audio';
$moduleKeyVideo = 'video';
$audioMeta = $modules[$moduleKeyAudio];
$videoMeta = $modules[$moduleKeyVideo];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $requestedModule = $_POST['module_key'] ?? '';
    if (!isset($modules[$requestedModule])) {
        set_flash('Unknown module.', 'danger');
        redirect('/defense.php');
    }

    defense_progress_mark_complete((int)$user['id'], $requestedModule);
    set_flash('Marked as complete. Review the material anytime to stay sharp.', 'success');
    redirect('/defense.php');
}

$audioComplete = defense_progress_is_complete($progress, $moduleKeyAudio);
$audioCompletedAt = $progress[$moduleKeyAudio] ?? null;
$videoComplete = defense_progress_is_complete($progress, $moduleKeyVideo);
$videoCompletedAt = $progress[$moduleKeyVideo] ?? null;

render_header('Defense Training');
?>
<style>
.tag-success {
    border-color: rgba(0, 255, 198, 0.5);
    color: var(--primary);
}
.tag-warning {
    border-color: rgba(255, 193, 7, 0.6);
    color: #ffdf7e;
}
.defense-module article + article {
    margin-top: 1.5rem;
}
.defense-progress-form {
    margin-top: 2rem;
    align-items: flex-start;
}
.defense-progress-form .muted {
    margin: 0;
}
</style>
<section class="panel">
    <h1>Deepfake Defense Resources</h1>
    <p>
        Build resilience against both synthetic audio and video. Work through the cheat sheets below and log completion once you’ve practiced the detection playbooks.
    </p>
</section>

<section class="panel defense-module task-status-anchor">
    <div class="task-status-chip" data-state="<?= $audioComplete ? 'completed' : 'pending' ?>">
        <span class="task-status-text"><?= $audioComplete ? 'Completed' : 'Pending' ?></span>
        <?php if ($audioCompletedAt): ?>
            <small><?= h(date('M j, Y g:i a T', strtotime($audioCompletedAt))) ?></small>
        <?php endif; ?>
    </div>
    <h2><?= h($audioMeta['title']) ?></h2>
    <p class="muted"><?= h($audioMeta['subtitle']) ?></p>

    <article>
        <h3>Manual methods</h3>
        <ul>
            <li><strong>Auditory observation:</strong> trained listeners flag monotone cadence, unusual breathing gaps, or fricatives that sound “hissy.”</li>
            <li><strong>Contextual verification:</strong> scrutinize financial or urgent requests by confirming through a second channel and pushing back on artificial urgency.</li>
            <li><strong>Multimodal analysis:</strong> when video is present, compare lip movements, micro-expressions, and tone for inconsistencies.</li>
        </ul>
    </article>

    <article>
        <h3>Core technologies for identifying deepfake audio</h3>
        <ol>
            <li><strong>Acoustic signal analysis:</strong> inspects pitch, tone, cadence, and breathing artifacts for robotic regularity.</li>
            <li><strong>Machine learning &amp; deep learning:</strong> CNN/RNN detectors trained on authentic vs. synthetic corpora to spot statistical “fingerprints.”</li>
            <li><strong>Spectrogram analysis:</strong> converts audio to visual spectrograms so CV models can highlight suspicious patterns.</li>
            <li><strong>Voice biometrics:</strong> compares incoming audio to a trusted voiceprint, blocking mismatches automatically.</li>
            <li><strong>Watermarking:</strong> embeds cryptographic signatures at creation time so receivers can validate provenance.</li>
            <li><strong>Metadata analysis:</strong> audits file metadata for edits, toolchains, or time stamps that don’t add up.</li>
        </ol>
    </article>

    <article>
        <h3>Software tools</h3>
        <ul>
            <li><strong>Pindrop:</strong> real-time acoustic fingerprinting for call centers with behavioral scoring.</li>
            <li><strong>Resemble Detect:</strong> API/console for spotting AI-generated speech across pipelines.</li>
            <li><strong>Sensity AI:</strong> enterprise multimodal forensic analysis across audio, video, and imagery.</li>
            <li><strong>Reality Defender:</strong> rapid API with detailed reports, widely used by media and public-sector teams.</li>
            <li><strong>Truepic:</strong> cryptographic provenance from capture through verification for legal workflows.</li>
            <li><strong>McAfee Deepfake Detector:</strong> privacy-preserving desktop scanning for individuals.</li>
            <li><strong>Hive AI:</strong> content-moderation scale detection with confidence scoring.</li>
            <li><strong>Veridas Voice Deepfake Detection:</strong> real-time liveness defense for contact centers.</li>
            <li><strong>ID R&amp;D Voice Shield:</strong> anti-spoofing biometrics tuned for cloned voice interception.</li>
        </ul>
    </article>

    <article>
        <h3>Detection tools by mission profile</h3>
        <h4>Real-time enterprise fraud prevention</h4>
        <p class="muted">Pindrop · Reality Defender · Sensity AI</p>
        <ul>
            <li>High accuracy (Pindrop advertises up to 99%) and low latency for live calls.</li>
            <li>API/SDK integrations with built-in liveness checks.</li>
        </ul>

        <h4>Banking, identity verification, KYC</h4>
        <p class="muted">Veridas · ID R&amp;D · Microsoft Azure Audio Deepfake Detection</p>
        <ul>
            <li>Regulatory-grade controls, hardened against TTS injection.</li>
            <li>Designed for step-up auth, account recovery, and government workflows.</li>
        </ul>

        <h4>Individual / media verification</h4>
        <p class="muted">AI Voice Detector · McAfee Deepfake Detector</p>
        <ul>
            <li>Browser extensions or lightweight clients for consumer use.</li>
            <li>Fast assessments for suspected scams or viral misinformation.</li>
        </ul>

        <h4>Content platforms &amp; large-scale moderation</h4>
        <p class="muted">Hive AI · Sensity AI · Reality Defender</p>
        <ul>
            <li>API-first, built for massive ingestion pipelines.</li>
            <li>Ideal for social media, newsrooms, or trust &amp; safety teams.</li>
        </ul>

        <h4>Digital forensics &amp; law enforcement</h4>
        <p class="muted">Sensity AI · Truepic</p>
        <ul>
            <li>Forensic reports with metadata timelines and tamper scoring.</li>
            <li>Cryptographic provenance to preserve evidentiary value.</li>
        </ul>
    </article>

    <form method="post" class="defense-progress-form">
        <input type="hidden" name="module_key" value="<?= h($moduleKeyAudio) ?>">
        <button type="submit" class="btn" <?= $audioComplete ? 'disabled' : '' ?>>
            <?= $audioComplete ? 'Module completed' : 'Mark as complete' ?>
        </button>
        <p class="muted"><?= $audioComplete ? 'Revisit anytime to refresh detection cues.' : 'Click once you’ve reviewed every section. You can revisit and update anytime.' ?></p>
    </form>
</section>

<section class="panel defense-module task-status-anchor" style="margin-top:2rem;">
    <div class="task-status-chip" data-state="<?= $videoComplete ? 'completed' : 'pending' ?>">
        <span class="task-status-text"><?= $videoComplete ? 'Completed' : 'Pending' ?></span>
        <?php if ($videoCompletedAt): ?>
            <small><?= h(date('M j, Y g:i a T', strtotime($videoCompletedAt))) ?></small>
        <?php endif; ?>
    </div>
    <h2><?= h($videoMeta['title']) ?></h2>
    <p class="muted"><?= h($videoMeta['subtitle']) ?></p>

    <article>
        <h3>Visual irregularities</h3>
        <ul>
            <li><strong>Faces &amp; bodies:</strong> inconsistent blinking, odd lighting, stiff motion, or overly smooth skin.</li>
            <li><strong>Background cues:</strong> warped objects, drifting shadows, or textures that “swim.”</li>
            <li><strong>Motion glitches:</strong> distorted hands, clothing merging with skin, lip-sync drift.</li>
        </ul>
    </article>

    <article>
        <h3>Audio + context clues</h3>
        <ul>
            <li>Emotion that feels flat, pacing that’s too even or erratic, or ambient noise that doesn’t match the scene.</li>
            <li>Ask whether the subject normally behaves this way and whether the source is trustworthy.</li>
            <li>Beware clips engineered for outrage—deepfakes often weaponize emotional triggers.</li>
        </ul>
    </article>

    <article>
        <h3>Source verification workflow</h3>
        <ol>
            <li>Trace the original uploader; cross-check with official channels or reputable outlets.</li>
            <li>Capture key frames and reverse-search them to find earlier versions.</li>
            <li>Scan comments and fact-check communities for early red flags.</li>
        </ol>
    </article>

    <article>
        <h3>Technical artifacts to scrutinize</h3>
        <ul>
            <li>Frame-by-frame flicker, sudden color or lighting shifts, haloed edges around faces.</li>
            <li>Unusual compression patterns or over-sharpened subjects relative to the background.</li>
            <li>Pause and scrub slowly—many glitches only appear on single frames.</li>
        </ul>
    </article>

    <article>
        <h3>Escalation guidance</h3>
        <ul>
            <li>If a clip could trigger action, hold distribution until authenticity is confirmed.</li>
            <li>Use trusted detection tools (Deepware Scanner, TrueSignal) when policy permits.</li>
            <li>Escalate to internal security or comms if doubt remains.</li>
        </ul>
    </article>

    <article>
        <h3>Reference links</h3>
        <ul>
            <li><a href="https://scanner.deepware.ai/" target="_blank" rel="noopener">Deepware Scanner</a></li>
            <li><a href="http://truesignal.online/upload" target="_blank" rel="noopener">TrueSignal</a></li>
            <li><a href="https://caniphish.com/blog/how-to-spot-ai-videos" target="_blank" rel="noopener">CanIPhish video guide</a></li>
            <li><a href="https://www.media.mit.edu/projects/detect-fakes/overview/" target="_blank" rel="noopener">MIT Detect Fakes</a></li>
        </ul>
    </article>

    <form method="post" class="defense-progress-form">
        <input type="hidden" name="module_key" value="<?= h($moduleKeyVideo) ?>">
        <button type="submit" class="btn" <?= $videoComplete ? 'disabled' : '' ?>>
            <?= $videoComplete ? 'Module completed' : 'Mark as complete' ?>
        </button>
        <p class="muted"><?= $videoComplete ? 'Review periodically to stay sharp on visual cues.' : 'Click once you’ve reviewed every section. You can revisit anytime.' ?></p>
    </form>
</section>
<?php
render_footer();

