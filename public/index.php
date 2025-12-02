<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/bootstrap.php';

$user = current_user();
render_header('Welcome');
?>
<section class="panel grid grid-2">
    <div>
        <h1>Deepfake Social Engineering</h1>
        <p>
            This taining application seeks to address the escalating threat of deepfake social engineering attacks with interactive security training.
        </p>
        <?php if ($user): ?>
            <a class="btn" href="/dashboard.php">Go to dashboard</a>
        <?php else: ?>
            <div style="display:flex; gap:1rem; flex-wrap:wrap; margin-top:1rem;">
                <a class="btn" href="/register.php">Create account</a>
                <a class="btn" href="/login.php" style="background:rgba(0,255,198,0.2); color:var(--primary); border:1px solid var(--primary);">
                    Sign in
                </a>
            </div>
        <?php endif; ?>
    </div>
    <div class="score-card">
        <h2>Training Modules</h2>
        <p><strong>Part 1 · Deepfake Challenge Game:</strong> <br>Identify synthetic audio/video across live scenarios.</p>
        <p><strong>Part 2 · Deepfake Offense:</strong> <br>Grok &amp; Sora walkthroughs with embedded quizzes.</p>
        <p><strong>Part 3 · Simulation Lab:</strong> <br>Full kill-chain training with payload build, listener, and Meterpreter.</p>
        <p><strong>Part 4 · Defense:</strong> <br>Audio and video cheat sheets with completion tracking.</p>
    </div>
</section>
<div class="intro-scale-note" style="display:flex; justify-content:center;">
    <span role="img" aria-label="warning">⚠️</span>
    Tested on Firefox and Chrome only. We strongly recommend using one of these browsers for the best experience.
</div>
<div class="intro-scale-note" style="margin-top:0.5rem; display:flex; justify-content:center;">
    <span role="img" aria-label="warning">⚠️</span>
    If you encounter a server crash or HTTP 500 error, please email synth3ticl4bs@gmail.com so we can investigate quickly.
</div>
<?php
render_footer();

