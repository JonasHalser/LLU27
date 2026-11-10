-- Personal HFmax; NULL retains the configured default. Safe to rerun.
SET @llu_max_hr_exists = (
 SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
 WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'users' AND COLUMN_NAME = 'max_hr'
);
SET @llu_max_hr_ddl = IF(@llu_max_hr_exists = 0,
 'ALTER TABLE users ADD COLUMN max_hr SMALLINT UNSIGNED NULL', 'SELECT 1');
PREPARE llu_max_hr_stmt FROM @llu_max_hr_ddl;
EXECUTE llu_max_hr_stmt;
DEALLOCATE PREPARE llu_max_hr_stmt;