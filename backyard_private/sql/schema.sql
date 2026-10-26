CREATE TABLE IF NOT EXISTS users (
 id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 username VARCHAR(40) NOT NULL UNIQUE,
 display_name VARCHAR(80) NOT NULL,
 password_hash VARCHAR(255) NOT NULL,
 start_date DATE NOT NULL,
 created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE IF NOT EXISTS athletes (
 user_id INT UNSIGNED PRIMARY KEY,
 athlete_id BIGINT UNSIGNED NOT NULL UNIQUE,
 access_token TEXT NOT NULL,
 refresh_token TEXT NOT NULL,
 expires_at BIGINT NOT NULL,
 last_sync DATETIME NULL,
 last_full_sync DATETIME NULL,
 sync_error VARCHAR(255) NULL,
 FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE IF NOT EXISTS activities (
 id BIGINT UNSIGNED PRIMARY KEY,
 user_id INT UNSIGNED NOT NULL,
 name VARCHAR(255) NOT NULL,
 activity_date DATETIME NOT NULL,
 distance_m DOUBLE NOT NULL,
 moving_seconds INT NOT NULL,
 elevation_m DOUBLE NOT NULL,
 avg_hr DOUBLE NULL,
 max_hr DOUBLE NULL,
 cache_updated_at DATETIME NOT NULL,
 INDEX idx_user_date (user_id,activity_date),
 INDEX idx_cache (cache_updated_at),
 FOREIGN KEY(user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE IF NOT EXISTS weekly_goals (
 user_id INT UNSIGNED NOT NULL,
 iso_year SMALLINT UNSIGNED NOT NULL,
 iso_week TINYINT UNSIGNED NOT NULL,
 unit ENUM('km','minutes','hours','loops') NOT NULL,
 target_value DECIMAL(10,2) UNSIGNED NOT NULL,
 progress_value DECIMAL(12,2) UNSIGNED NOT NULL DEFAULT 0,
 achieved_at DATETIME NULL,
 updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 PRIMARY KEY(user_id,iso_year,iso_week),
 INDEX(iso_year,iso_week),
 FOREIGN KEY(user_id) REFERENCES users(id) ON DELETE CASCADE,
 CONSTRAINT chk_weekly_goal_target CHECK(target_value>0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE IF NOT EXISTS team_weekly_goals (
 iso_year SMALLINT UNSIGNED NOT NULL,
 iso_week TINYINT UNSIGNED NOT NULL,
 unit ENUM('km','minutes','hours','loops') NOT NULL,
 target_value DECIMAL(10,2) UNSIGNED NOT NULL,
 progress_value DECIMAL(12,2) UNSIGNED NOT NULL DEFAULT 0,
 achieved_at DATETIME NULL,
 updated_by INT UNSIGNED NULL,
 updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 PRIMARY KEY(iso_year,iso_week),
 FOREIGN KEY(updated_by) REFERENCES users(id) ON DELETE SET NULL,
 CONSTRAINT chk_team_weekly_goal_target CHECK(target_value>0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
