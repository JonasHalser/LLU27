-- Daily login rewards and a 20% win bonus for correct supporter bets.
-- Safe to run repeatedly after 20261026_supporter_coins.sql.

SET @llu_bet_bonus_exists = (
 SELECT COUNT(*)
 FROM INFORMATION_SCHEMA.COLUMNS
 WHERE TABLE_SCHEMA = DATABASE()
   AND TABLE_NAME = 'supporter_coin_bets'
   AND COLUMN_NAME = 'bonus'
);
SET @llu_bet_bonus_ddl = IF(
 @llu_bet_bonus_exists = 0,
 'ALTER TABLE supporter_coin_bets ADD COLUMN bonus INT UNSIGNED NOT NULL DEFAULT 0 AFTER payout',
 'SELECT 1'
);
PREPARE llu_bet_bonus_stmt FROM @llu_bet_bonus_ddl;
EXECUTE llu_bet_bonus_stmt;
DEALLOCATE PREPARE llu_bet_bonus_stmt;

ALTER TABLE supporter_coin_ledger
 MODIFY COLUMN entry_type ENUM('signup_grant','wager','payout','refund','daily_login','bonus') NOT NULL;
