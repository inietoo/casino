-- phpMyAdmin SQL Dump
SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";

CREATE TABLE `users` (
  `id` INT PRIMARY KEY AUTO_INCREMENT,
  `username` VARCHAR(50) UNIQUE NOT NULL,
  `password` VARCHAR(255) NOT NULL,
  `balance` DECIMAL(10,2) DEFAULT 1000.00,
  `avatar` VARCHAR(10) DEFAULT '🎲',
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE `rooms` (
  `id` INT PRIMARY KEY AUTO_INCREMENT,
  `name` VARCHAR(100),
  `game_type` ENUM('blackjack','poker'),
  `max_players` INT DEFAULT 6,
  `min_bet` DECIMAL(10,2) DEFAULT 10.00,
  `max_bet` DECIMAL(10,2) DEFAULT 500.00,
  `status` ENUM('waiting','playing','finished') DEFAULT 'waiting',
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE `room_players` (
  `id` INT PRIMARY KEY AUTO_INCREMENT,
  `room_id` INT,
  `user_id` INT,
  `seat` INT,
  `status` ENUM('active','folded','bust','spectator'),
  `joined_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`room_id`) REFERENCES rooms(`id`),
  FOREIGN KEY (`user_id`) REFERENCES users(`id`)
);

CREATE TABLE `game_state` (
  `id` INT PRIMARY KEY AUTO_INCREMENT,
  `room_id` INT UNIQUE,
  `state_json` LONGTEXT,
  `current_turn` INT NULL,
  `phase` VARCHAR(50),
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (`room_id`) REFERENCES rooms(`id`)
);

CREATE TABLE `chat_messages` (
  `id` INT PRIMARY KEY AUTO_INCREMENT,
  `room_id` INT,
  `user_id` INT,
  `message` TEXT,
  `sent_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE `transactions` (
  `id` INT PRIMARY KEY AUTO_INCREMENT,
  `user_id` INT,
  `room_id` INT,
  `type` ENUM('bet','win','refund','reload'),
  `amount` DECIMAL(10,2),
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

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
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

CREATE TABLE `blackjack_hand_log` (
  `id` INT PRIMARY KEY AUTO_INCREMENT,
  `user_id` INT,
  `room_id` INT,
  `player_cards` VARCHAR(100),
  `dealer_cards` VARCHAR(100),
  `player_final_value` INT,
  `dealer_final_value` INT,
  `actions_taken` VARCHAR(200),
  `result` ENUM('win','loss','push','blackjack','bust'),
  `amount_bet` DECIMAL(10,2),
  `amount_won` DECIMAL(10,2),
  `played_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE `poker_stats` (
  `id` INT PRIMARY KEY AUTO_INCREMENT,
  `user_id` INT UNIQUE,
  `hands_played` INT DEFAULT 0,
  `hands_won` INT DEFAULT 0,
  `hands_won_showdown` INT DEFAULT 0,
  `hands_won_fold` INT DEFAULT 0,
  `times_folded` INT DEFAULT 0,
  `times_allin` INT DEFAULT 0,
  `flops_seen` INT DEFAULT 0,
  `vpip` DECIMAL(5,2) DEFAULT 0.00,
  `total_wagered` DECIMAL(12,2) DEFAULT 0.00,
  `total_won` DECIMAL(12,2) DEFAULT 0.00,
  `biggest_pot_won` DECIMAL(10,2) DEFAULT 0.00,
  `biggest_loss` DECIMAL(10,2) DEFAULT 0.00,
  `current_win_streak` INT DEFAULT 0,
  `best_win_streak` INT DEFAULT 0,
  `royal_flushes` INT DEFAULT 0,
  `straight_flushes` INT DEFAULT 0,
  `four_of_a_kinds` INT DEFAULT 0,
  `full_houses` INT DEFAULT 0,
  `flushes` INT DEFAULT 0,
  `straights` INT DEFAULT 0,
  `three_of_a_kinds` INT DEFAULT 0,
  `two_pairs` INT DEFAULT 0,
  `pairs` INT DEFAULT 0,
  `high_cards` INT DEFAULT 0,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

CREATE TABLE `poker_hand_log` (
  `id` INT PRIMARY KEY AUTO_INCREMENT,
  `user_id` INT,
  `room_id` INT,
  `hole_cards` VARCHAR(20),
  `community_cards` VARCHAR(50),
  `best_hand_cards` VARCHAR(50),
  `hand_rank` ENUM('royal_flush','straight_flush','four_of_a_kind','full_house','flush','straight','three_of_a_kind','two_pair','pair','high_card'),
  `actions_taken` VARCHAR(300),
  `result` ENUM('win','loss','fold'),
  `pot_size` DECIMAL(10,2),
  `amount_won` DECIMAL(10,2),
  `played_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE `ranking_snapshot` (
  `id` INT PRIMARY KEY AUTO_INCREMENT,
  `user_id` INT,
  `rank_position` INT,
  `score` DECIMAL(12,2),
  `snapshot_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

COMMIT;
