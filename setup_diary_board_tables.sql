-- Tabelas para Diário e Board no LifeOS

-- Tabela para o Diário (apenas humor e resumo)
CREATE TABLE IF NOT EXISTS `diary_entries` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL DEFAULT 1,
  `entry_date` date NOT NULL,
  `mood` varchar(10) DEFAULT '🙂',
  `content` text NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`),
  KEY `entry_date` (`entry_date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Tabela para o Memory Board (apenas data e foto)
CREATE TABLE IF NOT EXISTS `board_photos` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL DEFAULT 1,
  `photo_date` date NOT NULL,
  `image_path` varchar(500) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`),
  KEY `photo_date` (`photo_date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Remover coluna caption se existir (migração para ambiente remoto)
ALTER TABLE board_photos DROP COLUMN IF EXISTS caption;
