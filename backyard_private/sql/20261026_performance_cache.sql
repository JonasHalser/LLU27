-- Short-lived cache for Strava performance details and retry state.
-- Activities and their analysis records expire together.
CREATE TABLE IF NOT EXISTS activity_performance (
 activity_id BIGINT UNSIGNED NOT NULL PRIMARY KEY,
 user_id INT UNSIGNED NOT NULL,
 detail_json LONGTEXT NULL,
 fetched_at DATETIME NOT NULL,
 INDEX idx_activity_performance_user_fetched(user_id,fetched_at),
 CONSTRAINT fk_activity_performance_activity FOREIGN KEY(activity_id) REFERENCES activities(id) ON DELETE CASCADE,
 CONSTRAINT fk_activity_performance_user FOREIGN KEY(user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS activity_performance_errors (
 activity_id BIGINT UNSIGNED NOT NULL PRIMARY KEY,
 user_id INT UNSIGNED NOT NULL,
 attempts INT UNSIGNED NOT NULL DEFAULT 0,
 last_error VARCHAR(255) NOT NULL,
 next_retry_at DATETIME NOT NULL,
 updated_at DATETIME NOT NULL,
 INDEX idx_activity_performance_errors_updated(updated_at),
 INDEX idx_activity_performance_errors_retry(next_retry_at),
 CONSTRAINT fk_activity_performance_errors_activity FOREIGN KEY(activity_id) REFERENCES activities(id) ON DELETE CASCADE,
 CONSTRAINT fk_activity_performance_errors_user FOREIGN KEY(user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
