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

CREATE TABLE IF NOT EXISTS rooms
(
    id          BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    invite_code CHAR( 24 ) CHARACTER SET ascii COLLATE ascii_bin NOT NULL UNIQUE,
    owner_id    BIGINT UNSIGNED                                  NOT NULL,
    tmdb_id     INT UNSIGNED                                     NOT NULL,
    movie_title VARCHAR( 255 )                                   NOT NULL,
    created_at  TIMESTAMP                                        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY ( owner_id ) REFERENCES users ( id ) ON DELETE CASCADE
) ENGINE = InnoDB;

CREATE TABLE IF NOT EXISTS room_messages
(
    id      BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    room_id BIGINT UNSIGNED NOT NULL,
    user_id BIGINT UNSIGNED NOT NULL,
    message VARCHAR( 1000 ) NOT NULL,
    INDEX ( room_id, id ),
    FOREIGN KEY ( room_id ) REFERENCES rooms ( id ) ON DELETE CASCADE,
    FOREIGN KEY ( user_id ) REFERENCES users ( id ) ON DELETE CASCADE
) ENGINE = InnoDB;

CREATE TABLE IF NOT EXISTS room_playback
(
    room_id    BIGINT UNSIGNED PRIMARY KEY,
    position   DOUBLE       NOT NULL DEFAULT 0,
    playing    TINYINT( 1 ) NOT NULL DEFAULT 0,
    updated_at DOUBLE       NOT NULL,
    FOREIGN KEY ( room_id ) REFERENCES rooms ( id ) ON DELETE CASCADE
) ENGINE = InnoDB;

CREATE TABLE IF NOT EXISTS auth_attempts
(
    bucket       CHAR( 64 ) CHARACTER SET ascii PRIMARY KEY,
    attempts     INT UNSIGNED    NOT NULL,
    window_start BIGINT UNSIGNED NOT NULL,
    INDEX ( window_start )
) ENGINE = InnoDB;
