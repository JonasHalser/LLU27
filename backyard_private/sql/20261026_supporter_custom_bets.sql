-- Automatically settled supporter-created running goal markets.
-- Run after the coin and daily-bonus migrations.

ALTER TABLE supporter_coin_markets
 MODIFY COLUMN market_type ENUM('weekly_winner','mission_accept','mission_complete','custom_target') NOT NULL;

CREATE TABLE IF NOT EXISTS supporter_custom_markets (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 creator_id INT UNSIGNED NULL,
 runner_id INT UNSIGNED NOT NULL,
 title VARCHAR(180) NOT NULL,
 metric ENUM('distance_km','loops') NOT NULL,
 target_value DECIMAL(10,2) UNSIGNED NOT NULL,
 starts_at DATETIME NOT NULL,
 deadline_at DATETIME NOT NULL,
 status ENUM('open','succeeded','failed','cancelled') NOT NULL DEFAULT 'open',
 resolved_at DATETIME NULL,
 created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 INDEX custom_status_deadline(status,deadline_at),
 INDEX custom_runner(runner_id,status),
 FOREIGN KEY(creator_id) REFERENCES supporters(id) ON DELETE SET NULL,
 FOREIGN KEY(runner_id) REFERENCES users(id) ON DELETE CASCADE,
 CONSTRAINT chk_supporter_custom_target CHECK(target_value>0),
 CONSTRAINT chk_supporter_custom_deadline CHECK(deadline_at>starts_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Stores only aggregate run values needed to settle the goal. Rows survive the
-- regular seven-day activities-cache cleanup until the market is resolved.
CREATE TABLE IF NOT EXISTS supporter_custom_market_activities (
 custom_market_id BIGINT UNSIGNED NOT NULL,
 activity_id BIGINT UNSIGNED NOT NULL,
 activity_date DATETIME NOT NULL,
 distance_m DECIMAL(12,2) UNSIGNED NOT NULL,
 completed_loops INT UNSIGNED NOT NULL,
 observed_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 PRIMARY KEY(custom_market_id,activity_id),
 INDEX custom_activity_date(custom_market_id,activity_date),
 FOREIGN KEY(custom_market_id) REFERENCES supporter_custom_markets(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
