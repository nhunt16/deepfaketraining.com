<?php
declare(strict_types=1);

const DEFENSE_MODULES = [
    'audio' => [
        'title' => 'Deepfake Audio Defense Cheatsheet',
        'subtitle' => 'Recognize, verify, and respond to voice cloning threats.',
    ],
    'video' => [
        'title' => 'Deepfake Video Defense Cheatsheet',
        'subtitle' => 'Spot synthetic visuals and protect your teams from manipulated footage.',
    ],
];

function defense_progress_ensure_table(): void
{
    static $initialized = false;
    if ($initialized) {
        return;
    }

    $sql = <<<SQL
CREATE TABLE IF NOT EXISTS defense_progress (
    user_id INT NOT NULL,
    module_key VARCHAR(64) NOT NULL,
    completed_at TIMESTAMP NULL,
    PRIMARY KEY (user_id, module_key),
    CONSTRAINT fk_defense_progress_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
SQL;

    db()->exec($sql);
    $initialized = true;
}

function defense_modules(): array
{
    return DEFENSE_MODULES;
}

function defense_progress_get_all(int $userId): array
{
    defense_progress_ensure_table();
    $stmt = db()->prepare('SELECT module_key, completed_at FROM defense_progress WHERE user_id = ?');
    $stmt->execute([$userId]);
    $rows = $stmt->fetchAll();
    $result = [];
    foreach ($rows as $row) {
        $result[$row['module_key']] = $row['completed_at'];
    }
    return $result;
}

function defense_progress_mark_complete(int $userId, string $moduleKey): void
{
    defense_progress_ensure_table();
    $stmt = db()->prepare(
        'INSERT INTO defense_progress (user_id, module_key, completed_at)
         VALUES (:user_id, :module_key, :ts)
         ON DUPLICATE KEY UPDATE completed_at = VALUES(completed_at)'
    );
    $stmt->execute([
        'user_id' => $userId,
        'module_key' => $moduleKey,
        'ts' => date('Y-m-d H:i:s'),
    ]);
}

function defense_progress_is_complete(array $progress, string $moduleKey): bool
{
    return !empty($progress[$moduleKey]);
}

