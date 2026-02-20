-- ============================================================
-- Casino Royal — Schema completo corregido
-- ============================================================
SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";

DROP TABLE IF EXISTS
    `notifications`,
    `bingo_log`,
    `bingo_stats`,
    `poker_hand_log`,
    `poker_stats`,
    `blackjack_hand_log`,
    `blackjack_stats`,
    `transactions`,
    `chat_messages`,
    `game_state`,
    `room_players`,
    `rooms`,
    `users`;

-- ── USUARIOS ────────────────────────────────────────────────
CREATE TABLE `users` (
  `id`         INT PRIMARY KEY AUTO_INCREMENT,
  `username`   VARCHAR(50) UNIQUE NOT NULL,
  `password`   VARCHAR(255) NOT NULL,
  `balance`    DECIMAL(10,2) DEFAULT 1000.00,
  `avatar`     VARCHAR(10) DEFAULT '🎲',
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── NOTIFICACIONES ──────────────────────────────────────────
CREATE TABLE `notifications` (
  `id`         INT AUTO_INCREMENT PRIMARY KEY,
  `user_id`    INT NOT NULL,
  `message`    VARCHAR(255) NOT NULL,
  `is_read`    TINYINT(1) DEFAULT 0,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── SALAS ────────────────────────────────────────────────────
-- CORRECCIÓN: añadido 'bingo' al ENUM
CREATE TABLE `rooms` (
  `id`          INT PRIMARY KEY AUTO_INCREMENT,
  `name`        VARCHAR(100),
  `game_type`   ENUM('blackjack','poker','bingo') NOT NULL,
  `max_players` INT DEFAULT 6,
  `min_bet`     DECIMAL(10,2) DEFAULT 10.00,
  `max_bet`     DECIMAL(10,2) DEFAULT 500.00,
  `status`      ENUM('waiting','playing','finished') DEFAULT 'waiting',
  `created_at`  TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── JUGADORES EN SALA ────────────────────────────────────────
CREATE TABLE `room_players` (
  `id`        INT PRIMARY KEY AUTO_INCREMENT,
  `room_id`   INT,
  `user_id`   INT,
  `seat`      INT,
  `status`    VARCHAR(20),
  `joined_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`room_id`) REFERENCES `rooms`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── ESTADO DEL JUEGO ─────────────────────────────────────────
CREATE TABLE `game_state` (
  `id`           INT PRIMARY KEY AUTO_INCREMENT,
  `room_id`      INT UNIQUE,
  `state_json`   LONGTEXT,
  `current_turn` INT NULL,
  `phase`        VARCHAR(50),
  `updated_at`   TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (`room_id`) REFERENCES `rooms`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── MENSAJES DE CHAT ─────────────────────────────────────────
CREATE TABLE `chat_messages` (
  `id`      INT PRIMARY KEY AUTO_INCREMENT,
  `room_id` INT,
  `user_id` INT,
  `message` TEXT,
  `sent_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`room_id`) REFERENCES `rooms`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── TRANSACCIONES ────────────────────────────────────────────
CREATE TABLE `transactions` (
  `id`         INT PRIMARY KEY AUTO_INCREMENT,
  `user_id`    INT,
  `room_id`    INT DEFAULT 0,
  `type`       ENUM('bet','win','refund','reload','transfer_out','transfer_in'),
  `amount`     DECIMAL(10,2),
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── HISTORIAL DE MANOS — BLACKJACK ──────────────────────────
-- CORRECCIÓN: añadida columna actions_taken que profile.php intenta leer
CREATE TABLE `blackjack_hand_log` (
  `id`                  INT PRIMARY KEY AUTO_INCREMENT,
  `user_id`             INT,
  `room_id`             INT,
  `player_cards`        VARCHAR(255),
  `dealer_cards`        VARCHAR(255),
  `player_final_value`  INT,
  `dealer_final_value`  INT,
  `actions_taken`       VARCHAR(100) DEFAULT NULL,
  `result`              VARCHAR(20),
  `amount_bet`          DECIMAL(10,2),
  `amount_won`          DECIMAL(10,2),
  `played_at`           TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── HISTORIAL DE MANOS — POKER ───────────────────────────────
CREATE TABLE `poker_hand_log` (
  `id`              INT PRIMARY KEY AUTO_INCREMENT,
  `user_id`         INT,
  `room_id`         INT,
  `hole_cards`      VARCHAR(100),
  `community_cards` VARCHAR(200),
  `hand_rank`       VARCHAR(50),
  `result`          VARCHAR(20),
  `pot_size`        DECIMAL(10,2),
  `amount_won`      DECIMAL(10,2),
  `played_at`       TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── ESTADÍSTICAS — BLACKJACK ─────────────────────────────────
CREATE TABLE `blackjack_stats` (
  `id`                  INT PRIMARY KEY AUTO_INCREMENT,
  `user_id`             INT UNIQUE,
  `hands_played`        INT DEFAULT 0,
  `hands_won`           INT DEFAULT 0,
  `hands_lost`          INT DEFAULT 0,
  `hands_push`          INT DEFAULT 0,
  `blackjacks_hit`      INT DEFAULT 0,
  `times_busted`        INT DEFAULT 0,
  `total_wagered`       DECIMAL(12,2) DEFAULT 0.00,
  `total_won`           DECIMAL(12,2) DEFAULT 0.00,
  `biggest_win`         DECIMAL(10,2) DEFAULT 0.00,
  `current_win_streak`  INT DEFAULT 0,
  `best_win_streak`     INT DEFAULT 0,
  `updated_at`          TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── ESTADÍSTICAS — POKER ─────────────────────────────────────
-- CORRECCIÓN: añadidas columnas que profile.php intenta leer
CREATE TABLE `poker_stats` (
  `id`                  INT PRIMARY KEY AUTO_INCREMENT,
  `user_id`             INT UNIQUE,
  `hands_played`        INT DEFAULT 0,
  `hands_won`           INT DEFAULT 0,
  `hands_won_showdown`  INT DEFAULT 0,
  `hands_won_fold`      INT DEFAULT 0,
  `times_folded`        INT DEFAULT 0,
  `times_allin`         INT DEFAULT 0,
  `vpip`                DECIMAL(5,2) DEFAULT 0.00,
  `biggest_pot_won`     DECIMAL(10,2) DEFAULT 0.00,
  `total_wagered`       DECIMAL(12,2) DEFAULT 0.00,
  `total_won`           DECIMAL(12,2) DEFAULT 0.00,
  `current_win_streak`  INT DEFAULT 0,
  `best_win_streak`     INT DEFAULT 0,
  `updated_at`          TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── ESTADÍSTICAS — BINGO ─────────────────────────────────────
-- CORRECCIÓN: tabla antes ausente del schema
CREATE TABLE `bingo_stats` (
  `id`            INT PRIMARY KEY AUTO_INCREMENT,
  `user_id`       INT UNIQUE,
  `games_played`  INT DEFAULT 0,
  `games_won`     INT DEFAULT 0,
  `cards_bought`  INT DEFAULT 0,
  `total_wagered` DECIMAL(12,2) DEFAULT 0.00,
  `total_won`     DECIMAL(12,2) DEFAULT 0.00,
  `updated_at`    TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── HISTORIAL DE PARTIDAS — BINGO ────────────────────────────
-- CORRECCIÓN: tabla antes ausente del schema
CREATE TABLE `bingo_log` (
  `id`           INT PRIMARY KEY AUTO_INCREMENT,
  `user_id`      INT,
  `room_id`      INT,
  `cards_bought` INT DEFAULT 0,
  `result`       VARCHAR(20),
  `amount_bet`   DECIMAL(10,2),
  `amount_won`   DECIMAL(10,2),
  `played_at`    TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

COMMIT;
