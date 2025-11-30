<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/bootstrap.php';

require_login();

$pdo = db();
$user = current_user();

$scenarios = $pdo->query('SELECT id, title, description FROM scenarios ORDER BY created_at ASC')->fetchAll();

if (!$scenarios) {
    render_header('Deepfake Arena');
    ?>
    <section class="panel">
        <h1>No scenarios yet</h1>
        <p>The library is empty. An administrator can upload the first challenge from the admin console.</p>
    </section>
    <?php
    render_footer();
    exit;
}

$scenarioId = isset($_GET['scenario_id']) ? (int)$_GET['scenario_id'] : (int)$scenarios[0]['id'];
$result = null;
$progress = game_progress_get((int)$user['id']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $scenarioId = (int)($_POST['scenario_id'] ?? 0);
    $selectedIds = array_filter(
        array_map(static fn($value) => (int)$value, $_POST['media_ids'] ?? []),
        static fn($id) => $id > 0
    );
    $selectedIds = array_values(array_unique($selectedIds));

    if (!$selectedIds) {
        $result = [
            'is_correct' => false,
            'error' => 'Select every clip you believe is synthetic before submitting.',
        ];
    } else {
        $clipsStmt = $pdo->prepare('SELECT sm.id, sm.scenario_id, sm.is_deepfake, s.title FROM scenario_media sm INNER JOIN scenarios s ON sm.scenario_id = s.id WHERE sm.scenario_id = ?');
        $clipsStmt->execute([$scenarioId]);
        $clips = $clipsStmt->fetchAll(PDO::FETCH_ASSOC);

        $clipMap = [];
        $deepfakeIds = [];
        foreach ($clips as $clip) {
            $clipMap[(int)$clip['id']] = $clip;
            if ((int)$clip['is_deepfake'] === 1) {
                $deepfakeIds[] = (int)$clip['id'];
            }
        }

        $invalidSelection = array_diff($selectedIds, array_keys($clipMap));
        if ($invalidSelection) {
            $result = [
                'is_correct' => false,
                'error' => 'One or more selected clips are not part of this scenario. Refresh and try again.',
            ];
        } else {
            sort($deepfakeIds);
            $selectedSet = $selectedIds;
            sort($selectedSet);
            $missing = array_diff($deepfakeIds, $selectedSet);
            $extras = array_diff($selectedSet, $deepfakeIds);
            $isPerfect = empty($missing) && empty($extras);

            $insert = $pdo->prepare('INSERT INTO user_scenario_attempts (user_id, scenario_id, media_id, is_correct) VALUES (?, ?, ?, ?)');
            $representativeMediaId = $selectedIds[0];
            $insert->execute([$user['id'], $scenarioId, $representativeMediaId, $isPerfect ? 1 : 0]);

            $message = '';
            if (!$isPerfect) {
                $details = [];
                if ($missing) {
                    $details[] = count($missing) . ' deepfake clip' . (count($missing) > 1 ? 's' : '') . ' unselected';
                }
                if ($extras) {
                    $details[] = count($extras) . ' authentic clip' . (count($extras) > 1 ? 's' : '') . ' flagged by mistake';
                }
                $message = implode(' & ', $details);
            }

            $isPerfect = empty($missing) && empty($extras);

            if ($isPerfect) {
                game_progress_mark_complete((int)$user['id'], $scenarioId);
                $progress[$scenarioId] = date('Y-m-d H:i:s');
            }

            $result = [
                'is_correct' => $isPerfect,
                'scenario' => $clips[0]['title'] ?? 'Scenario',
                'detail' => $message,
            ];
        }
    }
}

$scenarioStmt = $pdo->prepare('SELECT id, title, description FROM scenarios WHERE id = ?');
$scenarioStmt->execute([$scenarioId]);
$activeScenario = $scenarioStmt->fetch();

if (!$activeScenario) {
    $activeScenario = $scenarios[0];
    $scenarioId = (int)$activeScenario['id'];
}

$mediaClipsStmt = $pdo->prepare('SELECT id, label, media_type FROM scenario_media WHERE scenario_id = ? ORDER BY id');
$mediaClipsStmt->execute([$scenarioId]);
$mediaClips = $mediaClipsStmt->fetchAll();

render_header('Deepfake Arena');
?>
<section class="panel">
    <h1>Deepfake Challenge Game</h1>
    <form method="get" style="margin-bottom:1.5rem;">
        <label>
            Scenario
            <select name="scenario_id" onchange="this.form.submit()">
                <?php foreach ($scenarios as $scenario): ?>
                    <option value="<?= h((string)$scenario['id']) ?>" <?= (int)$scenario['id'] === $scenarioId ? 'selected' : '' ?>>
                        <?= h($scenario['title']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </label>
    </form>

    <div class="score-card task-status-anchor" style="margin-bottom:1.5rem;">
        <div
            class="task-status-chip"
            data-task="challenge-progress"
            data-state="<?= game_progress_is_complete($progress, $scenarioId) ? 'completed' : 'pending' ?>"
        >
            <span class="task-status-text"><?= game_progress_is_complete($progress, $scenarioId) ? 'Completed' : 'Pending' ?></span>
        </div>
        <h2><?= h($activeScenario['title']) ?></h2>
        <p><?= h($activeScenario['description'] ?? '') ?></p>
        <div style="height:1.2rem;"></div>
        <div class="scenario-warning">
            <div class="intro-scale-note">
            <span role="img" aria-label="warning">⚠️</span>
            Select every clip you believe is synthetic. You must catch all deepfakes and avoid false positives to score the point.
            </div>
        </div>
    </div>

    <?php if ($result): ?>
        <div class="flash <?= $result['is_correct'] ? 'success' : 'danger' ?>">
            <?php if (!empty($result['error'])): ?>
                <?= h($result['error']) ?>
            <?php elseif ($result['is_correct']): ?>
                Nailed it! You spotted the synthetic voice.
            <?php else: ?>
                Not quite. <?= h($result['detail'] ?? 'Review the cues and try another scenario.') ?>
            <?php endif; ?>
        </div>
    <?php endif; ?>

    <?php if ($mediaClips): ?>
        <form method="post"
              class="media-grid media-grid-spacing"
              data-game-progress
              data-progress-state="<?= game_progress_is_complete($progress, $scenarioId) ? 'completed' : 'pending' ?>">
            <input type="hidden" name="scenario_id" value="<?= h((string)$scenarioId) ?>">
            <?php foreach ($mediaClips as $clip): ?>
                <label class="score-card media-card">
                    <strong><?= h($clip['label']) ?></strong>
                    <?php if ($clip['media_type'] === 'audio'): ?>
                        <audio controls preload="none">
                            <source src="/media.php?id=<?= h((string)$clip['id']) ?>">
                        </audio>
                    <?php else: ?>
                        <video controls preload="none">
                            <source src="/media.php?id=<?= h((string)$clip['id']) ?>">
                        </video>
                    <?php endif; ?>
                    <button type="button" class="ai-toggle" data-target="media-<?= h((string)$clip['id']) ?>">
                        <span class="ai-icon">🤖</span>
                        <span class="ai-label">Mark as deepfake</span>
                    </button>
                    <input type="checkbox" class="u-hidden" id="media-<?= h((string)$clip['id']) ?>" name="media_ids[]" value="<?= h((string)$clip['id']) ?>">
                </label>
            <?php endforeach; ?>
            <button type="submit">Submit answer</button>
        </form>
    <?php else: ?>
        <p>No media uploaded for this scenario yet.</p>
    <?php endif; ?>
</section>
<style>
.intro-scale-note {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 0.4rem;
    margin-top: 0.25rem;
    padding: 0.4rem 0.6rem;
    background: rgba(255, 236, 150, 0.35);
    border-radius: 6px;
    font-weight: 300;
    font-size: 0.9rem;
    color: #ffd500;
    line-height: 1.4;
    border: 1px solid #ffd500;
    text-align: center;
}
.scenario-warning {
    text-align: center;
}
.intro-scale-note span[role="img"] {
    font-size: 1.2rem;
}
.media-grid-spacing {
    gap: 1.5rem;
}
.task-status-anchor {
    position: relative;
}
.task-status-chip {
    position: absolute;
    top: 0.5rem;
    right: 0.5rem;
    display: inline-flex;
    align-items: center;
    gap: 0.3rem;
    padding: 0.2rem 0.6rem;
    font-size: 0.85rem;
    font-weight: 500;
    border-radius: 999px;
    background: rgba(5, 6, 10, 0.65);
    border: 1px solid currentColor;
}
.task-status-chip[data-state="completed"] {
    color: #14b886;
}
.task-status-chip[data-state="pending"] {
    color: #f97316;
}
.media-card {
    position: relative;
    border: 1px solid rgba(255, 255, 255, 0.08);
    transition: border-color 0.2s ease, box-shadow 0.2s ease;
    padding-bottom: 1.25rem;
}
.media-card.selected {
    border-color: var(--primary);
    box-shadow: 0 0 0 2px rgba(0, 255, 198, 0.2);
}
.ai-toggle {
    width: 100%;
    margin-top: 0.75rem;
    background: rgba(255, 255, 255, 0.05);
    border: 1px dashed rgba(255, 255, 255, 0.25);
    border-radius: 0.75rem;
    padding: 0.9rem;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 0.5rem;
    color: var(--text);
    cursor: pointer;
    transition: all 0.2s ease;
}
.ai-toggle.active {
    background: rgba(0, 255, 198, 0.12);
    border-color: transparent;
    color: var(--primary);
    text-shadow: 0 0 8px rgba(0, 255, 198, 0.5);
}
.ai-icon {
    font-size: 1.4rem;
}
.u-hidden {
    position: absolute;
    opacity: 0;
    pointer-events: none;
}
</style>
<script>
document.addEventListener('DOMContentLoaded', () => {
    const form = document.querySelector('form[data-game-progress]');
    const updateStatusChip = () => {
        if (!form) return;
        const state = form.dataset.progressState;
        if (!state) return;
        const chip = document.querySelector('.task-status-chip');
        if (!chip) return;
        chip.dataset.state = state;
        chip.querySelector('.task-status-text').textContent = state === 'completed' ? 'Completed' : 'Pending';
    };
    document.querySelectorAll('.ai-toggle').forEach((button) => {
        const targetId = button.dataset.target;
        const checkbox = document.getElementById(targetId);
        const card = button.closest('.media-card');
        const syncState = () => {
            if (!checkbox) return;
            const isChecked = checkbox.checked;
            button.classList.toggle('active', isChecked);
            card?.classList.toggle('selected', isChecked);
        };
        button.addEventListener('click', (event) => {
            event.preventDefault();
            if (!checkbox) return;
            checkbox.checked = !checkbox.checked;
            syncState();
        });
        syncState();
    });
    updateStatusChip();
});
</script>
<?php
render_footer();

