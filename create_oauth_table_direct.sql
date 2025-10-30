-- Script SQL directo para crear tabla yuju_oauth_tokens
-- Ejecutar este script directamente en la base de datos MySQL

-- REEMPLAZA 'ps_' por el prefijo de tu instalación de PrestaShop
-- Por ejemplo, si tu prefijo es 'ps_', deja como está
-- Si es diferente, cambia 'ps_' por tu prefijo real

CREATE TABLE IF NOT EXISTS `ps_yuju_oauth_tokens` (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `client_id` varchar(255) DEFAULT NULL,
    `client_secret` varchar(255) DEFAULT NULL,
    `access_token` varchar(500) DEFAULT NULL,
    `refresh_token` varchar(500) DEFAULT NULL,
    `token_type` varchar(50) DEFAULT NULL,
    `expires_in` int(11) DEFAULT NULL,
    `expires_at` datetime DEFAULT NULL,
    `token_expires` datetime DEFAULT NULL,
    `scope` varchar(255) DEFAULT NULL,
    `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
    `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Verificar que la tabla se creó correctamente
SELECT 'Tabla creada correctamente' as status;
SHOW COLUMNS FROM `ps_yuju_oauth_tokens`;
