-- scripts/init.sql
-- Runs automatically when the MySQL container first starts
-- Creates any extra tables Fleetbase doesn't create itself

SET NAMES utf8mb4;

-- Rider capacity tracking (also created by Laravel migration,
-- this ensures it exists even before migrations run)
CREATE TABLE IF NOT EXISTS rider_capacities (
  id            BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  rider_id      VARCHAR(255) NOT NULL UNIQUE,
  active_count  TINYINT UNSIGNED NOT NULL DEFAULT 0,
  order_ids     JSON,
  created_at    TIMESTAMP NULL,
  updated_at    TIMESTAMP NULL,
  INDEX idx_rider_id (rider_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Payout records (for your own records, not Fleetbase's)
CREATE TABLE IF NOT EXISTS rider_payouts (
  id            BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  rider_id      VARCHAR(255) NOT NULL,
  week_start    DATE NOT NULL,
  deliveries    INT UNSIGNED NOT NULL DEFAULT 0,
  gross_amount  BIGINT NOT NULL DEFAULT 0,
  rider_amount  BIGINT NOT NULL DEFAULT 0,
  platform_fee  BIGINT NOT NULL DEFAULT 0,
  paid_at       TIMESTAMP NULL,
  payment_ref   VARCHAR(255) NULL,  -- Mobile money transaction reference
  created_at    TIMESTAMP NULL,
  updated_at    TIMESTAMP NULL,
  INDEX idx_rider_week (rider_id, week_start)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
