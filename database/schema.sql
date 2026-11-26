-- Sunset Brunch: database schema
-- Works with MySQL 8.0.16+ and MariaDB 10.4+ (both enforce CHECK constraints).
--
-- Import: mysql -u root -p < database/schema.sql
-- No admin account is created here. Create one with: php bin/create-admin.php <username>

CREATE DATABASE IF NOT EXISTS sunset_brunch
    CHARACTER SET utf8mb4
    COLLATE utf8mb4_unicode_ci;

USE sunset_brunch;

-- ---------------------------------------------------------------------------
-- Bookings made from the landing page
-- ---------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS reservations (
    id            INT UNSIGNED     NOT NULL AUTO_INCREMENT,
    full_name     VARCHAR(100)     NOT NULL,
    phone         VARCHAR(20)      NOT NULL,                 -- stored normalized, e.g. +212612345678
    email         VARCHAR(190)     NOT NULL,
    booking_date  DATE             NOT NULL,
    time_slot     ENUM('12:00', '13:30', '15:00') NOT NULL,  -- must match config/booking.php
    guests        TINYINT UNSIGNED NOT NULL,
    message       VARCHAR(500)     NULL,
    status        ENUM('new', 'confirmed', 'cancelled') NOT NULL DEFAULT 'new',
    ip_hash       CHAR(64)         NOT NULL,                 -- salted SHA-256, never the raw IP
    created_at    DATETIME         NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at    DATETIME         NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    PRIMARY KEY (id),
    -- Used by the capacity check: SUM(guests) for one date + slot, excluding cancelled.
    KEY idx_slot_capacity (booking_date, time_slot, status),
    -- Used by the back-office filters and "newest first" ordering.
    KEY idx_status (status),
    KEY idx_created_at (created_at),
    -- Used by the anti-spam rate limit (bookings per IP in the last minutes).
    KEY idx_ip_created (ip_hash, created_at),

    CONSTRAINT chk_reservations_guests CHECK (guests BETWEEN 1 AND 12)
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------------
-- Back-office accounts (passwords stored only as password_hash() output)
-- ---------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS admins (
    id             INT UNSIGNED NOT NULL AUTO_INCREMENT,
    username       VARCHAR(50)  NOT NULL,
    password_hash  VARCHAR(255) NOT NULL,
    last_login_at  DATETIME     NULL,
    created_at     DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,

    PRIMARY KEY (id),
    UNIQUE KEY uq_admins_username (username)
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------------
-- Failed login attempts, used to slow down password guessing
-- ---------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS login_attempts (
    id            BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    ip_hash       CHAR(64)        NOT NULL,
    attempted_at  DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,

    PRIMARY KEY (id),
    KEY idx_ip_attempted (ip_hash, attempted_at)
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;
