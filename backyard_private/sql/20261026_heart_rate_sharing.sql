-- Heart-rate sharing consent for existing runner accounts.
-- Safe to rerun; existing consent values are preserved.

SET @llu_share_team_hr_exists = (
 SELECT COUNT(*)
 FROM INFORMATION_SCHEMA.COLUMNS
 WHERE TABLE_SCHEMA = DATABASE()
   AND TABLE_NAME = 'users'
   AND COLUMN_NAME = 'share_team_hr'
);
SET @llu_share_team_hr_ddl = IF(
 @llu_share_team_hr_exists = 0,
 'ALTER TABLE users ADD COLUMN share_team_hr TINYINT(1) NOT NULL DEFAULT 0',
 'SELECT 1'
);
PREPARE llu_share_team_hr_stmt FROM @llu_share_team_hr_ddl;
EXECUTE llu_share_team_hr_stmt;
DEALLOCATE PREPARE llu_share_team_hr_stmt;
