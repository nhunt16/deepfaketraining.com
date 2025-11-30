<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/bootstrap.php';

$user = current_user();
render_header('Welcome');
?>
<section class="panel grid grid-2">
    <div>
        <h1>Spot the Synthetic Threats</h1>
        <p>
            Deepfake Social Engineering Training Platform helps your team practice identifying manipulated
            voice and video messages before bad actors can weaponize them.
        </p>
        <ul>
            <li>Interactive, scenario-based gameplay with authentic clips.</li>
            <li>Admin console for uploading new challenges securely.</li>
            <li>Progress tracking for both the game and awareness briefing.</li>
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
        <p><strong>Part 1:</strong> Deepfake Challenge Game</p>
        <p><strong>Part 2:</strong> Cyber deception briefing video</p>
        <p>Earn points for accurate identifications and log your completion of the briefing.</p>
    </div>
</section>
<?php
render_footer();

