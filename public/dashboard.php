<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/bootstrap.php';

require_login();

$user = current_user();
$pdo = db();

$scenarioRows = $pdo->query('SELECT id, title FROM scenarios ORDER BY created_at ASC')->fetchAll();
$totalScenarios = count($scenarioRows);

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
$defenseCompleted = count(array_filter($defenseProgress));
$defenseTotal = count($defenseModules);
$defensePercent = $defenseTotal > 0 ? (int)round(($defenseCompleted / $defenseTotal) * 100) : 0;
$gameProgress = game_progress_get((int)$user['id']);
$gameCompleted = count($gameProgress);
$gamePercent = $totalScenarios > 0 ? (int)round(($gameCompleted / $totalScenarios) * 100) : 0;

$completionLog = [];
foreach ($scenarioRows as $index => $scenario) {
    $scenarioId = (int)$scenario['id'];
    if (!empty($gameProgress[$scenarioId])) {
        $completionLog[] = [
            'label' => sprintf('Scenario %d · %s', $index + 1, $scenario['title']),
            'timestamp' => $gameProgress[$scenarioId],
        ];
    }
}
foreach ($simulationTaskLabels as $taskKey => $label) {
    $timestamp = simulation_progress_task_timestamp($simulationProgress, $taskKey);
    if ($timestamp) {
        $completionLog[] = [
            'label' => $label,
            'timestamp' => $timestamp,
        ];
    }
}
foreach ($defenseModules as $key => $meta) {
    $timestamp = $defenseProgress[$key] ?? null;
    if ($timestamp) {
        $completionLog[] = [
            'label' => $meta['title'],
            'timestamp' => $timestamp,
        ];
    }
}
usort($completionLog, static fn($a, $b) => strtotime($b['timestamp']) <=> strtotime($a['timestamp']));

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
        <p><strong>Overall progress:</strong> <?= h((string)$gamePercent) ?>% (<?= h((string)$gameCompleted) ?> / <?= h((string)$totalScenarios) ?> scenarios)</p>
        <ul class="task-progress-list" style="list-style:none; padding-left:0; margin:0 0 1rem;">
            <?php foreach ($scenarioRows as $index => $scenario): ?>
                <?php $scenarioId = (int)$scenario['id']; ?>
                <?php $complete = game_progress_is_complete($gameProgress, $scenarioId); ?>
                <?php $timestamp = $gameProgress[$scenarioId] ?? null; ?>
                <li style="margin-bottom:0.4rem; display:flex; justify-content:space-between; gap:0.5rem;">
                    <span><?= $complete ? '✅' : '⬜' ?> Scenario <?= $index + 1 ?> · <?= h($scenario['title']) ?></span>
                    <?php if ($complete && $timestamp): ?>
                        <small style="color:var(--muted, #5f6b7a); white-space:nowrap;"><?= h($timestamp) ?></small>
                    <?php endif; ?>
                </li>
            <?php endforeach; ?>
            <?php if (!$scenarioRows): ?>
                <li>No scenarios uploaded yet.</li>
            <?php endif; ?>
        </ul>
        <a class="btn" href="/game.php">Enter the arena</a>
    </div>
    <div class="score-card">
        <h2>Deepfake Offense</h2>
        <p class="muted small-label">Part 2 · Grok Exploitation Tutorial</p>
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
        <p><strong>Overall progress:</strong> <?= h((string)$defensePercent) ?>% (<?= h((string)$defenseCompleted) ?> / <?= h((string)$defenseTotal) ?> modules)</p>
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
    <h2>Completed tasks</h2>
    <?php if ($completionLog): ?>
        <ul style="list-style:none; padding-left:0; margin:0;">
            <?php foreach ($completionLog as $entry): ?>
                <li style="display:flex; justify-content:space-between; gap:0.5rem; border-bottom:1px solid rgba(255,255,255,0.08); padding:0.6rem 0;">
                    <span>✅ <?= h($entry['label']) ?></span>
                    <small style="color:var(--muted, #5f6b7a); white-space:nowrap;"><?= h($entry['timestamp']) ?></small>
                </li>
            <?php endforeach; ?>
        </ul>
    <?php else: ?>
        <p>No tasks completed yet. Progress updates will appear here once you finish a module.</p>
    <?php endif; ?>
</section>
<?php
render_footer();

