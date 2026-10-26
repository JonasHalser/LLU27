-- Supporter missions. Repeatable; requires the existing users and supporters tables.
CREATE TABLE IF NOT EXISTS supporter_missions (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 supporter_id INT UNSIGNED NULL,
 title VARCHAR(120) NOT NULL,
 description VARCHAR(500) NOT NULL DEFAULT '',
 unit ENUM('km','minutes','loops') NOT NULL,
 target_value DECIMAL(10,2) UNSIGNED NOT NULL,
 points TINYINT UNSIGNED NOT NULL,
 acceptance_mode ENUM('exclusive','all') NOT NULL,
 deadline_at DATETIME NOT NULL,
 cancelled_at DATETIME NULL,
 created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 INDEX(deadline_at,cancelled_at),
 INDEX(supporter_id,created_at),
 FOREIGN KEY(supporter_id) REFERENCES supporters(id) ON DELETE SET NULL,
 CONSTRAINT chk_supporter_mission_target CHECK(target_value>0),
 CONSTRAINT chk_supporter_mission_points CHECK(points BETWEEN 1 AND 5)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS supporter_mission_entries (
 mission_id BIGINT UNSIGNED NOT NULL,
 runner_id INT UNSIGNED NOT NULL,
 accepted_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 progress_value DECIMAL(12,2) UNSIGNED NOT NULL DEFAULT 0,
 completed_at DATETIME NULL,
 PRIMARY KEY(mission_id,runner_id),
 INDEX(runner_id,completed_at),
 FOREIGN KEY(mission_id) REFERENCES supporter_missions(id) ON DELETE CASCADE,
 FOREIGN KEY(runner_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- No FK to activities: the regular seven-day cache may delete a run after it
-- has been credited. The compact ledger keeps mission progress reproducible.
CREATE TABLE IF NOT EXISTS supporter_mission_activities (
 mission_id BIGINT UNSIGNED NOT NULL,
 runner_id INT UNSIGNED NOT NULL,
 activity_id BIGINT UNSIGNED NOT NULL,
 distance_m DOUBLE UNSIGNED NOT NULL,
 moving_seconds INT UNSIGNED NOT NULL,
 completed_loops INT UNSIGNED NOT NULL,
 observed_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 PRIMARY KEY(mission_id,runner_id,activity_id),
 FOREIGN KEY(mission_id,runner_id) REFERENCES supporter_mission_entries(mission_id,runner_id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
