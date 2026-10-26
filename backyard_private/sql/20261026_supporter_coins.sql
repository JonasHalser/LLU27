-- LLU Coin wallet and pari-mutuel supporter markets.
-- Safe to run more than once. Run after the supporter and mission migrations.
-- Adds only missing schema and never resets an existing coin balance.

SET @llu_coin_balance_exists = (
 SELECT COUNT(*)
 FROM INFORMATION_SCHEMA.COLUMNS
 WHERE TABLE_SCHEMA = DATABASE()
   AND TABLE_NAME = 'supporters'
   AND COLUMN_NAME = 'coin_balance'
);
SET @llu_coin_balance_ddl = IF(
 @llu_coin_balance_exists = 0,
 'ALTER TABLE supporters ADD COLUMN coin_balance INT UNSIGNED NOT NULL DEFAULT 0',
 'SELECT 1'
);
PREPARE llu_coin_schema_stmt FROM @llu_coin_balance_ddl;
EXECUTE llu_coin_schema_stmt;
DEALLOCATE PREPARE llu_coin_schema_stmt;

CREATE TABLE IF NOT EXISTS supporter_coin_markets (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 market_type ENUM('weekly_winner','mission_accept','mission_complete') NOT NULL,
 reference_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
 subject_id INT UNSIGNED NOT NULL DEFAULT 0,
 iso_year SMALLINT UNSIGNED NOT NULL DEFAULT 0,
 iso_week TINYINT UNSIGNED NOT NULL DEFAULT 0,
 closes_at DATETIME NOT NULL,
 winning_option VARCHAR(32) NULL,
 settled_at DATETIME NULL,
 created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 UNIQUE KEY market_identity(market_type,reference_id,subject_id,iso_year,iso_week),
 INDEX market_due(settled_at,closes_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS supporter_coin_bets (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 market_id BIGINT UNSIGNED NOT NULL,
 supporter_id INT UNSIGNED NULL,
 option_key VARCHAR(32) NOT NULL,
 stake INT UNSIGNED NOT NULL,
 payout INT UNSIGNED NULL,
 placed_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 INDEX bets_market(market_id,option_key),
 INDEX bets_supporter(supporter_id,placed_at),
 FOREIGN KEY(market_id) REFERENCES supporter_coin_markets(id) ON DELETE CASCADE,
 FOREIGN KEY(supporter_id) REFERENCES supporters(id) ON DELETE SET NULL,
 CONSTRAINT chk_supporter_coin_bet_stake CHECK(stake>0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS supporter_coin_ledger (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 supporter_id INT UNSIGNED NULL,
 amount INT NOT NULL,
 entry_type ENUM('signup_grant','wager','payout','refund') NOT NULL,
 reference_key VARCHAR(100) NOT NULL DEFAULT '',
 created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 UNIQUE KEY ledger_once(supporter_id,entry_type,reference_key),
 INDEX ledger_history(supporter_id,created_at),
 FOREIGN KEY(supporter_id) REFERENCES supporters(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Credit only supporters without an initial grant. The grant record and balance
-- update commit together, so a rerun cannot replenish a spent balance.
DROP TEMPORARY TABLE IF EXISTS supporter_coin_new_grants;
CREATE TEMPORARY TABLE supporter_coin_new_grants (
 supporter_id INT UNSIGNED NOT NULL PRIMARY KEY
) ENGINE=InnoDB;

START TRANSACTION;
INSERT INTO supporter_coin_new_grants(supporter_id)
 SELECT s.id
 FROM supporters s
 WHERE s.coin_balance=0
   AND NOT EXISTS (
  SELECT 1 FROM supporter_coin_ledger l
  WHERE l.supporter_id=s.id
    AND l.entry_type='signup_grant'
    AND l.reference_key='initial'
 );

INSERT INTO supporter_coin_ledger(supporter_id,amount,entry_type,reference_key)
 SELECT supporter_id,100,'signup_grant','initial'
 FROM supporter_coin_new_grants;

UPDATE supporters s
 JOIN supporter_coin_new_grants g ON g.supporter_id=s.id
 SET s.coin_balance=s.coin_balance+100;
COMMIT;

DROP TEMPORARY TABLE supporter_coin_new_grants;
