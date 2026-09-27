CREATE DATABASE IF NOT EXISTS watch_party CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE watch_party;
CREATE TABLE IF NOT EXISTS users
(
    id            BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name          VARCHAR( 100 )                                              NOT NULL,
    email         VARCHAR( 254 ) CHARACTER SET ascii COLLATE ascii_general_ci NOT NULL UNIQUE,
    password_hash VARCHAR( 255 )                                              NOT NULL,
    created_at    TIMESTAMP                                                   NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE = InnoDB;
CREATE TABLE IF NOT EXISTS auth_attempts
(
    bucket       CHAR( 64 ) CHARACTER SET ascii PRIMARY KEY,
    attempts     INT UNSIGNED    NOT NULL,
    window_start BIGINT UNSIGNED NOT NULL,
    INDEX ( window_start )
) ENGINE = InnoDB;
