<?php
declare(strict_types=1);

function game_progress_ensure_table(): void
{
    static $initialized = false;
    if ($initialized) {
        return;
    }

    $sql = <<<SQL
CREATE TABLE IF NOT EXISTS game_progress (
    user_id INT NOT NULL,
    scenario_id INT NOT NULL,
    completed_at TIMESTAMP NULL,
    PRIMARY KEY (user_id, scenario_id),
    CONSTRAINT fk_game_progress_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    CONSTRAINT fk_game_progress_scenario FOREIGN KEY (scenario_id) REFERENCES scenarios(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
SQL;

    db()->exec($sql);
    $initialized = true;
}

function game_progress_get(int $userId): array
{
    game_progress_ensure_table();
    $stmt = db()->prepare('SELECT scenario_id, completed_at FROM game_progress WHERE user_id = ?');
    $stmt->execute([$userId]);
    $rows = $stmt->fetchAll(PDO::FETCH_KEY_PAIR) ?: [];
    return array_map(static fn($value) => $value, $rows);
}

function game_progress_mark_complete(int $userId, int $scenarioId): void
{
    game_progress_ensure_table();
    $stmt = db()->prepare(
        'INSERT INTO game_progress (user_id, scenario_id, completed_at)
         VALUES (:user_id, :scenario_id, :ts)
         ON DUPLICATE KEY UPDATE completed_at = VALUES(completed_at)'
    );
    $stmt->execute([
        'user_id' => $userId,
        'scenario_id' => $scenarioId,
        'ts' => date('Y-m-d H:i:s'),
    ]);
}

function game_progress_is_complete(array $progress, int $scenarioId): bool
{
    return !empty($progress[$scenarioId]);
}

