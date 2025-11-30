CREATE DATABASE IF NOT EXISTS deepfake_training CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE deepfake_training;

CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    is_admin TINYINT(1) NOT NULL DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS scenarios (
    id INT AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(150) NOT NULL,
    description TEXT,
    created_by INT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_scenarios_user FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
);

CREATE TABLE IF NOT EXISTS scenario_media (
    id INT AUTO_INCREMENT PRIMARY KEY,
    scenario_id INT NOT NULL,
    label VARCHAR(150) NOT NULL,
    media_type ENUM('audio', 'video') NOT NULL,
    mime_type VARCHAR(64) NOT NULL,
    media_data LONGBLOB NOT NULL,
    is_deepfake TINYINT(1) NOT NULL DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_media_scenario FOREIGN KEY (scenario_id) REFERENCES scenarios(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS user_scenario_attempts (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    scenario_id INT NOT NULL,
    media_id INT NOT NULL,
    is_correct TINYINT(1) NOT NULL,
    attempted_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_attempt_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    CONSTRAINT fk_attempt_scenario FOREIGN KEY (scenario_id) REFERENCES scenarios(id) ON DELETE CASCADE,
    CONSTRAINT fk_attempt_media FOREIGN KEY (media_id) REFERENCES scenario_media(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS user_progress (
    user_id INT PRIMARY KEY,
    part2_completed TINYINT(1) NOT NULL DEFAULT 0,
    last_video_view TIMESTAMP NULL,
    CONSTRAINT fk_progress_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS simulation_progress (
    user_id INT PRIMARY KEY,
    payload_prepared_at TIMESTAMP NULL,
    voicemail_generated_at TIMESTAMP NULL,
    listener_started_at TIMESTAMP NULL,
    phish_delivered_at TIMESTAMP NULL,
    shell_caught_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_simulation_progress_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS defense_progress (
    user_id INT NOT NULL,
    module_key VARCHAR(64) NOT NULL,
    completed_at TIMESTAMP NULL,
    PRIMARY KEY (user_id, module_key),
    CONSTRAINT fk_defense_progress_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS game_progress (
    user_id INT NOT NULL,
    scenario_id INT NOT NULL,
    completed_at TIMESTAMP NULL,
    PRIMARY KEY (user_id, scenario_id),
    CONSTRAINT fk_game_progress_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    CONSTRAINT fk_game_progress_scenario FOREIGN KEY (scenario_id) REFERENCES scenarios(id) ON DELETE CASCADE
);

