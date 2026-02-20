-- phpMyAdmin SQL Dump
SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";

-- Borramos las tablas si existen para poder reimportar sin errores
DROP TABLE IF EXISTS `notifications`, `ranking_snapshot`, `poker_hand_log`, `poker_stats`, `blackjack_hand_log`, `blackjack_stats`, `transactions`, `chat_messages`, `game_state`, `room_players`, `rooms`, `users`;

CREATE TABLE `users` (
  `id` INT PRIMARY KEY AUTO_INCREMENT,
  `username` VARCHAR(50) UNIQUE NOT NULL,
  `password` VARCHAR(255) NOT NULL,
  `balance` DECIMAL(10,2) DEFAULT 1000.00,
  `avatar` VARCHAR(10) DEFAULT '🎲',
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `notifications` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `user_id` INT NOT NULL,
    `message` VARCHAR(255) NOT NULL,
    `is_read` TINYINT(1) DEFAULT 0,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `rooms` (
  `id` INT PRIMARY KEY AUTO_INCREMENT,
  `name` VARCHAR(100),
  `game_type` ENUM('blackjack','poker'),
  `max_players` INT DEFAULT 6,
  `min_bet` DECIMAL(10,2) DEFAULT 10.00,
  `max_bet` DECIMAL(10,2) DEFAULT 500.00,
  `status` ENUM('waiting','playing','finished') DEFAULT 'waiting',
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `room_players` (
  `id` INT PRIMARY KEY AUTO_INCREMENT,
  `room_id` INT,
  `user_id` INT,
  `seat` INT,
  `status` ENUM('active','folded','bust','spectator'),
  `joined_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`room_id`) REFERENCES rooms(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`user_id`) REFERENCES users(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `game_state` (
  `id` INT PRIMARY KEY AUTO_INCREMENT,
  `room_id` INT UNIQUE,
  `state_json` LONGTEXT,
  `current_turn` INT NULL,
  `phase` VARCHAR(50),
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (`room_id`) REFERENCES rooms(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `chat_messages` (
  `id` INT PRIMARY KEY AUTO_INCREMENT,
  `room_id` INT,
  `user_id` INT,
  `message` TEXT,
  `sent_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`room_id`) REFERENCES rooms(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`user_id`) REFERENCES users(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `transactions` (
  `id` INT PRIMARY KEY AUTO_INCREMENT,
  `user_id` INT,
  `room_id` INT,
  `type` ENUM('bet','win','refund','reload'),
  `amount` DECIMAL(10,2),
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`user_id`) REFERENCES users(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `blackjack_stats` (
  `id` INT PRIMARY KEY AUTO_INCREMENT,
  `user_id` INT UNIQUE,
  `hands_played` INT DEFAULT 0,
  `hands_won` INT DEFAULT 0,
  `hands_lost` INT DEFAULT 0,
  `hands_push` INT DEFAULT 0,
  `blackjacks_hit` INT DEFAULT 0,
  `times_busted` INT DEFAULT 0,
  `times_doubled` INT DEFAULT 0,
  `times_split` INT DEFAULT 0,
  `total_wagered` DECIMAL(12,2) DEFAULT 0.00,
  `total_won` DECIMAL(12,2) DEFAULT 0.00,
  `biggest_win` DECIMAL(10,2) DEFAULT 0.00,
  `biggest_loss` DECIMAL(10,2) DEFAULT 0.00,
  `current_win_streak` INT DEFAULT 0,
  `best_win_streak` INT DEFAULT 0,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (`user_id`) REFERENCES users(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `poker_stats` (
  `id` INT PRIMARY KEY AUTO_INCREMENT,
  `user_id` INT UNIQUE,
  `hands_played` INT DEFAULT 0,
  `hands_won` INT DEFAULT 0,
  `total_wagered` DECIMAL(12,2) DEFAULT 0.00,
  `total_won` DECIMAL(12,2) DEFAULT 0.00,
  `current_win_streak` INT DEFAULT 0,
  `best_win_streak` INT DEFAULT 0,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (`user_id`) REFERENCES users(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

COMMIT;
