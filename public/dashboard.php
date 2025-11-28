<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/bootstrap.php';

require_login();

$user = current_user();
$pdo = db();

$totalScenarios = (int)$pdo->query('SELECT COUNT(*) FROM scenarios')->fetchColumn();

$attemptStats = $pdo->prepare(
    'SELECT COUNT(*) AS attempts, COALESCE(SUM(is_correct), 0) AS correct, COUNT(DISTINCT scenario_id) AS covered
     FROM user_scenario_attempts WHERE user_id = ?'
);
$attemptStats->execute([$user['id']]);
$stats = $attemptStats->fetch() ?: ['attempts' => 0, 'correct' => 0, 'covered' => 0];

$progressStmt = $pdo->prepare('SELECT part2_completed, last_video_view FROM user_progress WHERE user_id = ?');
$progressStmt->execute([$user['id']]);
$progress = $progressStmt->fetch() ?: ['part2_completed' => 0, 'last_video_view' => null];

$simulationProgress = simulation_progress_get((int)$user['id']);
$simulationTaskLabels = simulation_progress_task_labels();
$simulationCompleted = simulation_progress_completed_count($simulationProgress);
$simulationTotalTasks = simulation_progress_total_tasks();
$simulationPercent = (int)round(($simulationCompleted / max(1, $simulationTotalTasks)) * 100);
$defenseModules = defense_modules();
$defenseProgress = defense_progress_get_all((int)$user['id']);

$recentAttempts = $pdo->prepare(
    'SELECT s.title, sm.label, usa.is_correct, usa.attempted_at
     FROM user_scenario_attempts usa
     INNER JOIN scenarios s ON usa.scenario_id = s.id
     INNER JOIN scenario_media sm ON usa.media_id = sm.id
     WHERE usa.user_id = ?
     ORDER BY usa.attempted_at DESC
     LIMIT 5'
);
$recentAttempts->execute([$user['id']]);
$attemptRows = $recentAttempts->fetchAll();

render_header('Dashboard');
?>
<style>
.dashboard-columns {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 1.5rem;
}
@media (max-width: 900px) {
    .dashboard-columns {
        grid-template-columns: 1fr;
    }
}
.small-label {
    margin: -0.4rem 0 0.9rem;
    font-size: 0.85rem;
    color: var(--muted);
}
</style>
<section class="panel dashboard-columns">
    <div class="score-card">
        <h2>Deepfake Challenge Game</h2>
        <p class="muted small-label">Part 1 · Warmup</p>
        <p><strong>Total scenarios:</strong> <?= h((string)$totalScenarios) ?></p>
        <p><strong>Scenarios attempted:</strong> <?= h((string)$stats['covered']) ?></p>
        <p><strong>Accuracy:</strong>
            <?php
            if ($stats['attempts'] > 0) {
                $accuracy = round(($stats['correct'] / max(1, $stats['attempts'])) * 100);
                echo h("{$accuracy}% ({$stats['correct']} / {$stats['attempts']})");
            } else {
                echo 'No attempts yet';
            }
            ?>
        </p>
        <a class="btn" href="/game.php">Enter the arena</a>
    </div>
    <div class="score-card">
        <h2>Demonstration</h2>
        <p class="muted small-label">Part 2 · Social Engineering Demonstration Video</p>
        <p>Status:
            <?php if ($progress['part2_completed']): ?>
                <span class="tag" style="border-color:var(--primary); color:var(--primary)">Completed</span>
            <?php else: ?>
                <span class="tag" style="border-color:var(--danger); color:var(--danger)">Pending</span>
            <?php endif; ?>
        </p>
        <?php if ($progress['last_video_view']): ?>
            <p>Last viewed: <?= h($progress['last_video_view']) ?></p>
        <?php endif; ?>
        <a class="btn" href="/video.php">Watch the briefing</a>
    </div>
    <div class="score-card">
        <h2>Simulation Lab</h2>
        <p class="muted small-label">Part 3 · Deepfake Social Engineering Simulation</p>
        <p><strong>Overall progress:</strong> <?= h((string)$simulationPercent) ?>% (<?= h((string)$simulationCompleted) ?> / <?= h((string)$simulationTotalTasks) ?> tasks)</p>
        <ul class="task-progress-list" style="list-style:none; padding-left:0; margin:0 0 1rem;">
            <?php foreach ($simulationTaskLabels as $taskKey => $label): ?>
                <?php $taskComplete = simulation_progress_is_task_complete($simulationProgress, $taskKey); ?>
                <?php $timestamp = simulation_progress_task_timestamp($simulationProgress, $taskKey); ?>
                <li style="margin-bottom:0.4rem; display:flex; justify-content:space-between; gap:0.5rem;">
                    <span><?= $taskComplete ? '✅' : '⬜' ?> <?= h($label) ?></span>
                    <?php if ($taskComplete && $timestamp): ?>
                        <small style="color:var(--muted, #5f6b7a); white-space:nowrap;"><?= h($timestamp) ?></small>
                    <?php endif; ?>
                </li>
            <?php endforeach; ?>
        </ul>
        <a class="btn" href="/simulation.php">Resume simulation</a>
    </div>
    <div class="score-card">
        <h2>Deepfake Defense</h2>
        <p class="muted small-label">Part 4 · Deepfake Defense Resources</p>
        <ul class="task-progress-list" style="list-style:none; padding-left:0; margin:0 0 1rem;">
            <?php foreach ($defenseModules as $key => $meta): ?>
                <?php $complete = defense_progress_is_complete($defenseProgress, $key); ?>
                <?php $timestamp = $defenseProgress[$key] ?? null; ?>
                <li style="margin-bottom:0.4rem; display:flex; justify-content:space-between; gap:0.5rem;">
                    <span><?= $complete ? '✅' : '⬜' ?> <?= h($meta['title']) ?></span>
                    <?php if ($timestamp): ?>
                        <small style="color:var(--muted, #5f6b7a); white-space:nowrap;"><?= h($timestamp) ?></small>
                    <?php endif; ?>
                </li>
            <?php endforeach; ?>
        </ul>
        <a class="btn" href="/defense.php"><?= $defenseProgress ? 'Review modules' : 'Start defense training' ?></a>
    </div>
</section>

<section class="panel" style="margin-top:2rem;">
    <h2>Recent attempts</h2>
    <?php if ($attemptRows): ?>
        <table>
            <thead>
            <tr>
                <th>Scenario</th>
                <th>Clip</th>
                <th>Result</th>
                <th>When</th>
            </tr>
            </thead>
            <tbody>
            <?php foreach ($attemptRows as $row): ?>
                <tr>
                    <td><?= h($row['title']) ?></td>
                    <td><?= h($row['label']) ?></td>
                    <td><?= $row['is_correct'] ? '✅ Correct' : '⚠️ Incorrect' ?></td>
                    <td><?= h($row['attempted_at']) ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    <?php else: ?>
        <p>No attempts logged yet. Start the game to see your stats.</p>
    <?php endif; ?>
</section>
<?php
render_footer();

