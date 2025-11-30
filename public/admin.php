<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/bootstrap.php';

require_login();
require_admin();

$pdo = db();

$redirectScenarioId = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'create_scenario') {
        $title = trim($_POST['title'] ?? '');
        $description = trim($_POST['description'] ?? '');

        if ($title === '') {
            set_flash('Scenario title is required.', 'danger');
        } else {
            $stmt = $pdo->prepare('INSERT INTO scenarios (title, description, created_by) VALUES (?, ?, ?)');
            $stmt->execute([$title, $description, current_user()['id']]);
            set_flash('Scenario created.', 'success');
        }
    } elseif ($action === 'update_scenario') {
        $scenarioId = (int)($_POST['scenario_id'] ?? 0);
        $redirectScenarioId = $scenarioId;
        $title = trim($_POST['title'] ?? '');
        $description = trim($_POST['description'] ?? '');

        if ($scenarioId <= 0) {
            set_flash('Select a scenario to update.', 'danger');
        } elseif ($title === '') {
            set_flash('Title cannot be empty.', 'danger');
        } else {
            $stmt = $pdo->prepare('UPDATE scenarios SET title = ?, description = ? WHERE id = ?');
            $stmt->execute([$title, $description, $scenarioId]);
            set_flash('Scenario updated.', 'success');
        }
    } elseif ($action === 'add_clip') {
        $scenarioId = (int)($_POST['scenario_id'] ?? 0);
        $redirectScenarioId = $scenarioId;
        $label = trim($_POST['label'] ?? '');
        $mediaType = $_POST['media_type'] ?? 'audio';
        $isDeepfake = isset($_POST['is_deepfake']) ? 1 : 0;
        $mediaFile = $_FILES['media'] ?? null;
        $uploadError = $mediaFile['error'] ?? UPLOAD_ERR_NO_FILE;

        if ($scenarioId <= 0 || $label === '') {
            set_flash('Scenario and label are required.', 'danger');
        } elseif ($uploadError !== UPLOAD_ERR_OK) {
            set_flash('Upload failed: ' . upload_error_message($uploadError), 'danger');
        } elseif (empty($mediaFile['tmp_name']) || !is_uploaded_file($mediaFile['tmp_name'])) {
            set_flash('Temporary upload file missing or invalid.', 'danger');
        } else {
            $filePath = $mediaFile['tmp_name'];
            $data = file_get_contents($filePath);
            if ($data === false) {
                set_flash('Unable to read the media file.', 'danger');
            } else {
                $mime = mime_content_type($filePath) ?: ($mediaFile['type'] ?? 'application/octet-stream');

                $stmt = $pdo->prepare(
                    'INSERT INTO scenario_media (scenario_id, label, media_type, mime_type, media_data, is_deepfake)
                     VALUES (:scenario_id, :label, :media_type, :mime_type, :media_data, :is_deepfake)'
                );
                $stmt->bindValue(':scenario_id', $scenarioId, PDO::PARAM_INT);
                $stmt->bindValue(':label', $label);
                $stmt->bindValue(':media_type', $mediaType === 'video' ? 'video' : 'audio');
                $stmt->bindValue(':mime_type', $mime);
                $stmt->bindValue(':media_data', $data, PDO::PARAM_LOB);
                $stmt->bindValue(':is_deepfake', $isDeepfake, PDO::PARAM_INT);
                $stmt->execute();

                set_flash('Media uploaded.', 'success');
            }
        }
    } elseif ($action === 'delete_clip') {
        $clipId = (int)($_POST['clip_id'] ?? 0);
        $scenarioId = (int)($_POST['scenario_id'] ?? 0);
        $redirectScenarioId = $scenarioId;

        if ($clipId <= 0 || $scenarioId <= 0) {
            set_flash('Clip not found.', 'danger');
        } else {
            $stmt = $pdo->prepare('DELETE FROM scenario_media WHERE id = ? AND scenario_id = ?');
            $stmt->execute([$clipId, $scenarioId]);
            if ($stmt->rowCount() > 0) {
                set_flash('Clip deleted.', 'success');
            } else {
                set_flash('Clip not found.', 'warning');
            }
        }
    } elseif ($action === 'update_clip_label') {
        $clipId = (int)($_POST['clip_id'] ?? 0);
        $scenarioId = (int)($_POST['scenario_id'] ?? 0);
        $redirectScenarioId = $scenarioId;
        $label = trim($_POST['label'] ?? '');

        if ($clipId <= 0 || $scenarioId <= 0 || $label === '') {
            set_flash('Clip ID, scenario, and label are required.', 'danger');
        } else {
            $stmt = $pdo->prepare('UPDATE scenario_media SET label = ? WHERE id = ? AND scenario_id = ?');
            $stmt->execute([$label, $clipId, $scenarioId]);
            if ($stmt->rowCount() > 0) {
                set_flash('Clip label updated.', 'success');
            } else {
                set_flash('Clip not found or label unchanged.', 'warning');
            }
        }
    } elseif ($action === 'delete_scenario') {
        $scenarioId = (int)($_POST['scenario_id'] ?? 0);
        if ($scenarioId <= 0) {
            set_flash('Scenario not found.', 'danger');
        } else {
            $delete = $pdo->prepare('DELETE FROM scenarios WHERE id = ?');
            $delete->execute([$scenarioId]);
            if ($delete->rowCount() > 0) {
                set_flash('Scenario deleted.', 'success');
            } else {
                set_flash('Scenario not found.', 'warning');
            }
        }
    }

    $target = '/admin.php';
    if ($redirectScenarioId) {
        $target .= '?scenario_id=' . urlencode((string)$redirectScenarioId);
    }
    redirect($target);
}

$scenarios = $pdo->query('SELECT id, title FROM scenarios ORDER BY created_at DESC')->fetchAll();
$activeScenarioId = isset($_GET['scenario_id']) ? (int)$_GET['scenario_id'] : (int)($scenarios[0]['id'] ?? 0);
$activeScenario = null;
$scenarioClips = [];

if ($activeScenarioId > 0) {
    $scenarioStmt = $pdo->prepare('SELECT id, title, description FROM scenarios WHERE id = ?');
    $scenarioStmt->execute([$activeScenarioId]);
    $activeScenario = $scenarioStmt->fetch();

    if ($activeScenario) {
        $clipsStmt = $pdo->prepare('SELECT id, label, media_type, is_deepfake, created_at FROM scenario_media WHERE scenario_id = ? ORDER BY id DESC');
        $clipsStmt->execute([$activeScenarioId]);
        $scenarioClips = $clipsStmt->fetchAll();
    } else {
        $activeScenarioId = 0;
    }
}

render_header('Admin Console');
?>
<section class="panel">
    <h2>Create Scenario</h2>
    <form method="post" class="form-scenario">
        <input type="hidden" name="action" value="create_scenario">
        <label>
            Title
            <input type="text" name="title" required>
        </label>
        <label>
            Description
            <textarea name="description" rows="6"></textarea>
        </label>
        <button type="submit">Create scenario</button>
    </form>
</section>

<section class="panel" style="margin-top:2rem;">
    <h2>Edit Scenario</h2>
    <?php if (!$scenarios): ?>
        <p>Create a scenario first.</p>
    <?php else: ?>
        <form method="get" style="margin-bottom:1rem;">
            <label style="display:flex; flex-direction:column; gap:0.5rem;">
                Select scenario
                <select name="scenario_id" onchange="this.form.submit()">
                    <?php foreach ($scenarios as $scenario): ?>
                        <option value="<?= h((string)$scenario['id']) ?>" <?= (int)$scenario['id'] === $activeScenarioId ? 'selected' : '' ?>>
                            <?= h($scenario['title']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </label>
        </form>
        <?php if ($activeScenario): ?>
            <form method="post" class="form-scenario">
                <input type="hidden" name="action" value="update_scenario">
                <input type="hidden" name="scenario_id" value="<?= h((string)$activeScenario['id']) ?>">
                <label>
                    Title
                    <input type="text" name="title" value="<?= h($activeScenario['title']) ?>" required>
                </label>
                <label>
                    Description
                    <textarea name="description" rows="6"><?= h($activeScenario['description'] ?? '') ?></textarea>
                </label>
                <button type="submit">Save changes</button>
            </form>
            <form method="post" onsubmit="return confirm('Delete this entire scenario? All media will be removed.');" style="margin-top:1rem;">
                <input type="hidden" name="action" value="delete_scenario">
                <input type="hidden" name="scenario_id" value="<?= h((string)$activeScenario['id']) ?>">
                <button type="submit" class="btn" style="background:#ff4d6d; color:#fff;">Delete scenario</button>
            </form>
            <hr style="margin:2rem 0; border:0; border-top:1px solid rgba(255,255,255,0.1);">
            <h3>Add Clip</h3>
            <form method="post" enctype="multipart/form-data">
                <input type="hidden" name="action" value="add_clip">
                <input type="hidden" name="scenario_id" value="<?= h((string)$activeScenario['id']) ?>">
                <div class="form-inline">
                    <label>
                        Clip label
                        <input type="text" name="label" required>
                    </label>
                    <label>
                        Media type
                        <select name="media_type">
                            <option value="audio">Audio</option>
                            <option value="video">Video</option>
                        </select>
                    </label>
                    <label>
                        File
                        <input type="file" name="media" accept="audio/*,video/*" required>
                    </label>
                </div>
                <label style="display:flex; align-items:center; gap:0.5rem;">
                    <input type="checkbox" name="is_deepfake">
                    Mark as deepfake
                </label>
                <button type="submit">Upload clip</button>
            </form>
            <hr style="margin:2rem 0; border:0; border-top:1px solid rgba(255,255,255,0.1);">
            <h3>Clips for "<?= h($activeScenario['title']) ?>"</h3>
            <?php if ($scenarioClips): ?>
                <table>
                    <thead>
                    <tr>
                        <th>Label</th>
                        <th>Type</th>
                        <th>Deepfake?</th>
                        <th>Length</th>
                        <th>Actions</th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($scenarioClips as $clip): ?>
                        <tr>
                            <td><?= h($clip['label']) ?></td>
                            <td><?= h(ucfirst($clip['media_type'])) ?></td>
                            <td><?= $clip['is_deepfake'] ? 'Yes' : 'No' ?></td>
                            <td>
                                <span id="clip-duration-<?= h((string)$clip['id']) ?>">--:--</span>
                                <?php if ($clip['media_type'] === 'video'): ?>
                                    <video preload="metadata" data-duration-for="<?= h((string)$clip['id']) ?>" style="display:none;">
                                        <source src="/media.php?id=<?= h((string)$clip['id']) ?>">
                                    </video>
                                <?php else: ?>
                                    <audio preload="metadata" data-duration-for="<?= h((string)$clip['id']) ?>" style="display:none;">
                                        <source src="/media.php?id=<?= h((string)$clip['id']) ?>">
                                    </audio>
                                <?php endif; ?>
                            </td>
                            <td>
                                <form method="post" style="display:inline-flex; gap:0.5rem; align-items:center;">
                                    <input type="hidden" name="action" value="update_clip_label">
                                    <input type="hidden" name="clip_id" value="<?= h((string)$clip['id']) ?>">
                                    <input type="hidden" name="scenario_id" value="<?= h((string)$activeScenario['id']) ?>">
                                    <input type="text" name="label" value="<?= h($clip['label']) ?>" style="width:150px;">
                                    <button type="submit" class="btn" style="padding:0.35rem 0.75rem;">Save</button>
                                </form>
                                <form method="post" onsubmit="return confirm('Delete this clip?');" style="display:inline;">
                                    <input type="hidden" name="action" value="delete_clip">
                                    <input type="hidden" name="clip_id" value="<?= h((string)$clip['id']) ?>">
                                    <input type="hidden" name="scenario_id" value="<?= h((string)$activeScenario['id']) ?>">
                                    <button type="submit" class="btn" style="background:#ff4d6d; color:#fff; padding:0.35rem 0.75rem;">Delete</button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
                <script>
                    document.addEventListener('DOMContentLoaded', () => {
                        document.querySelectorAll('[data-duration-for]').forEach(mediaEl => {
                            mediaEl.addEventListener('loadedmetadata', () => {
                                const id = mediaEl.dataset.durationFor;
                                const target = document.getElementById('clip-duration-' + id);
                                if (!target || Number.isNaN(mediaEl.duration) || !Number.isFinite(mediaEl.duration)) {
                                    return;
                                }
                                const totalSeconds = Math.max(0, mediaEl.duration);
                                const minutes = Math.floor(totalSeconds / 60);
                                const seconds = Math.round(totalSeconds % 60).toString().padStart(2, '0');
                                target.textContent = `${minutes}:${seconds}`;
                            }, { once: true });
                        });
                    });
                </script>
            <?php else: ?>
                <p>No clips uploaded yet.</p>
            <?php endif; ?>
        <?php endif; ?>
    <?php endif; ?>
</section>
<?php
render_footer();

