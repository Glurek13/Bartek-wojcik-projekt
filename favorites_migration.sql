-- Uruchom RAZ w istniejącej bazie gamezone (phpMyAdmin > SQL).
-- Nie usuwa istniejących użytkowników, gier ani recenzji.
CREATE TABLE IF NOT EXISTS `favorites` (
  `user_id` INT(11) NOT NULL,
  `game_id` INT(11) NOT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`user_id`, `game_id`),
  CONSTRAINT `fk_favorites_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_favorites_game` FOREIGN KEY (`game_id`) REFERENCES `games` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_polish_ci;
