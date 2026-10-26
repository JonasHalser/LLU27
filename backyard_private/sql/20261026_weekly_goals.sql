-- Run manually after schema.sql. Repeatable; preserves prior weekly goals.
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

-- Upgrade an installation that already imported the first weekly-goal version.
SET @weekly_progress_sql = IF(
 (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='weekly_goals' AND COLUMN_NAME='progress_value')=0,
 'ALTER TABLE weekly_goals ADD COLUMN progress_value DECIMAL(12,2) UNSIGNED NOT NULL DEFAULT 0 AFTER target_value',
 'SELECT 1'
);
PREPARE weekly_progress_stmt FROM @weekly_progress_sql;
EXECUTE weekly_progress_stmt;
DEALLOCATE PREPARE weekly_progress_stmt;

SET @weekly_achieved_sql = IF(
 (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='weekly_goals' AND COLUMN_NAME='achieved_at')=0,
 'ALTER TABLE weekly_goals ADD COLUMN achieved_at DATETIME NULL AFTER progress_value',
 'SELECT 1'
);
PREPARE weekly_achieved_stmt FROM @weekly_achieved_sql;
EXECUTE weekly_achieved_stmt;
DEALLOCATE PREPARE weekly_achieved_stmt;

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
