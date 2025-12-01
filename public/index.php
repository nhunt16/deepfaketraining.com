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
            This lab walks defenders through offensive and defensive tradecraft: spotting AI-generated media,
            running simulated phishing, and hardening response playbooks.
        </p>
        <ul>
            <li><strong>Part 1 · Deepfake Challenge Game:</strong> Identify synthetic audio/video across live scenarios.</li>
            <li><strong>Part 2 · Deepfake Offense:</strong> Grok &amp; Sora walkthroughs with embedded quizzes.</li>
            <li><strong>Part 3 · Simulation Lab:</strong> Full kill-chain training with payload build, listener, and Meterpreter.</li>
            <li><strong>Part 4 · Defense:</strong> Audio and video cheat sheets with completion tracking.</li>
        </ul>
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
        <p><strong>Part 1:</strong> Challenge Game</p>
        <p><strong>Part 2:</strong> Deepfake Offense (Grok + Sora)</p>
        <p><strong>Part 3:</strong> Simulation Lab</p>
        <p><strong>Part 4:</strong> Defense Resources</p>
    </div>
</section>
<?php
render_footer();

