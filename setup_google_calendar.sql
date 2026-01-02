-- Tabela para armazenar tokens OAuth2 do Google Calendar
CREATE TABLE IF NOT EXISTS `google_calendar_tokens` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `access_token` text NOT NULL,
  `refresh_token` text,
  `expires_at` datetime NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `user_id` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Adicionar coluna google_event_id na tabela events se não existir
ALTER TABLE `events` 
ADD COLUMN `google_event_id` varchar(255) DEFAULT NULL,
ADD UNIQUE KEY `google_event_id` (`google_event_id`);
