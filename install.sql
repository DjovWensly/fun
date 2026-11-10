-- Game Party: database schema
-- Import this file once with phpMyAdmin (InfinityFree control panel > MySQL Databases > phpMyAdmin > Import).

CREATE TABLE IF NOT EXISTS gp_rooms (
  id          INT UNSIGNED NOT NULL AUTO_INCREMENT,
  code        CHAR(5)      NOT NULL,
  game        VARCHAR(20)  NOT NULL,
  status      VARCHAR(10)  NOT NULL DEFAULT 'lobby',  -- lobby | playing | closed
  options     TEXT         NULL,                      -- JSON
  state       MEDIUMTEXT   NULL,                      -- JSON game state (server-authoritative)
  version     INT UNSIGNED NOT NULL DEFAULT 1,        -- bumped on every change, clients poll on it
  created_at  DATETIME     NOT NULL,
  updated_at  DATETIME     NOT NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_code (code),
  KEY idx_updated (updated_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS gp_players (
  id         INT UNSIGNED NOT NULL AUTO_INCREMENT,
  room_id    INT UNSIGNED NOT NULL,
  token      CHAR(32)     NOT NULL,
  name       VARCHAR(40)  NOT NULL,
  seat       TINYINT UNSIGNED NOT NULL,
  is_bot     TINYINT(1)   NOT NULL DEFAULT 0,
  is_host    TINYINT(1)   NOT NULL DEFAULT 0,
  last_seen  DATETIME     NOT NULL,
  joined_at  DATETIME     NOT NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_token (token),
  UNIQUE KEY uq_room_seat (room_id, seat),
  CONSTRAINT fk_players_room FOREIGN KEY (room_id) REFERENCES gp_rooms (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Truth or Dare questions, managed from admin.php.
-- (If this table is empty, the game uses data/prompts.json instead.)
CREATE TABLE IF NOT EXISTS gp_prompts (
  id          INT UNSIGNED NOT NULL AUTO_INCREMENT,
  lang        VARCHAR(5)   NOT NULL,                  -- fr | en
  level       VARCHAR(10)  NOT NULL,                  -- soft | party
  type        VARCHAR(10)  NOT NULL,                  -- truth | dare
  text        VARCHAR(300) NOT NULL,
  active      TINYINT(1)   NOT NULL DEFAULT 1,
  created_at  DATETIME     NOT NULL,
  updated_at  DATETIME     NOT NULL,
  PRIMARY KEY (id),
  KEY idx_pick (lang, level, type, active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
