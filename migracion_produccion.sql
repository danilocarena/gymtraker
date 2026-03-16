-- ==========================================================
-- SCRIPT DE MIGRACIÓN PARA PRODUCCIÓN - GYMTRAKER
-- Versión: 1.2
-- Descripción: Crea la estructura de base de datos, 
--              inserta ejercicios base y configura el admin.
-- ==========================================================

-- 1. CREACIÓN DE LA BASE DE DATOS (Opcional, descomentar si es necesario)
-- CREATE DATABASE IF NOT EXISTS gymtracker CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci;
-- USE gymtracker;

SET FOREIGN_KEY_CHECKS = 0;

-- 2. ESTRUCTURA DE TABLAS

-- Usuarios
CREATE TABLE IF NOT EXISTS `GT_users` (
  `id` int NOT NULL AUTO_INCREMENT,
  `username` varchar(50) NOT NULL,
  `email` varchar(100) NOT NULL,
  `role` varchar(20) DEFAULT 'user',
  `is_active` tinyint(1) DEFAULT '1',
  `google_id` varchar(255) DEFAULT NULL,
  `password_hash` varchar(255) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `height` decimal(5,2) DEFAULT NULL,
  `weight` decimal(5,2) DEFAULT NULL,
  `target_weight` decimal(5,2) DEFAULT NULL,
  `weekly_goal` int DEFAULT '4',
  `avatar_emoji` varchar(10) DEFAULT '🦍',
  PRIMARY KEY (`id`),
  UNIQUE KEY `username` (`username`),
  UNIQUE KEY `email` (`email`),
  UNIQUE KEY `google_id` (`google_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- Ejercicios (Catálogo Global)
CREATE TABLE IF NOT EXISTS `GT_exercises` (
  `id` int NOT NULL AUTO_INCREMENT,
  `name` varchar(100) NOT NULL,
  `muscle_group` varchar(50) NOT NULL,
  `description` text,
  `category` varchar(50) DEFAULT 'Otros',
  PRIMARY KEY (`id`),
  UNIQUE KEY `name` (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- Rutinas
CREATE TABLE IF NOT EXISTS `GT_routines` (
  `id` int NOT NULL AUTO_INCREMENT,
  `user_id` int NOT NULL,
  `name` varchar(100) NOT NULL,
  `description` text,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`),
  CONSTRAINT `GT_routines_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `GT_users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- Días de Rutina
CREATE TABLE IF NOT EXISTS `GT_routine_days` (
  `id` int NOT NULL AUTO_INCREMENT,
  `routine_id` int NOT NULL,
  `day_name` varchar(50) NOT NULL,
  `day_order` int NOT NULL DEFAULT '1',
  PRIMARY KEY (`id`),
  KEY `routine_id` (`routine_id`),
  CONSTRAINT `GT_routine_days_ibfk_1` FOREIGN KEY (`routine_id`) REFERENCES `GT_routines` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- Ejercicios por Día de Rutina
CREATE TABLE IF NOT EXISTS `GT_routine_exercises` (
  `id` int NOT NULL AUTO_INCREMENT,
  `routine_day_id` int NOT NULL,
  `exercise_name` varchar(100) NOT NULL,
  `exercise_order` int NOT NULL DEFAULT '1',
  PRIMARY KEY (`id`),
  KEY `routine_day_id` (`routine_day_id`),
  CONSTRAINT `GT_routine_exercises_ibfk_1` FOREIGN KEY (`routine_day_id`) REFERENCES `GT_routine_days` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- Sesiones de Entrenamiento
CREATE TABLE IF NOT EXISTS `GT_workout_sessions` (
  `id` int NOT NULL AUTO_INCREMENT,
  `user_id` int NOT NULL,
  `session_date` date NOT NULL,
  `session_name` varchar(100) DEFAULT NULL,
  `notes` text,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `routine_id` int DEFAULT NULL,
  `routine_day_id` int DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`),
  KEY `routine_id` (`routine_id`),
  KEY `routine_day_id` (`routine_day_id`),
  CONSTRAINT `GT_workout_sessions_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `GT_users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `GT_workout_sessions_ibfk_2` FOREIGN KEY (`routine_id`) REFERENCES `GT_routines` (`id`) ON DELETE SET NULL,
  CONSTRAINT `GT_workout_sessions_ibfk_3` FOREIGN KEY (`routine_day_id`) REFERENCES `GT_routine_days` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- Logs de Series/Ejercicios
CREATE TABLE IF NOT EXISTS `GT_session_logs` (
  `id` int NOT NULL AUTO_INCREMENT,
  `session_id` int NOT NULL,
  `exercise_id` int DEFAULT NULL,
  `sets` int DEFAULT NULL,
  `reps` int NOT NULL,
  `weight` decimal(6,2) NOT NULL,
  `log_time` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `exercise_name` varchar(100) DEFAULT NULL,
  `comment` text,
  PRIMARY KEY (`id`),
  KEY `session_id` (`session_id`),
  KEY `exercise_id` (`exercise_id`),
  CONSTRAINT `GT_session_logs_ibfk_1` FOREIGN KEY (`session_id`) REFERENCES `GT_workout_sessions` (`id`) ON DELETE CASCADE,
  CONSTRAINT `GT_session_logs_ibfk_2` FOREIGN KEY (`exercise_id`) REFERENCES `GT_exercises` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- Historial de Peso
CREATE TABLE IF NOT EXISTS `GT_weight_logs` (
  `id` int NOT NULL AUTO_INCREMENT,
  `user_id` int NOT NULL,
  `weight` decimal(5,2) NOT NULL,
  `log_date` date NOT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`),
  CONSTRAINT `GT_weight_logs_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `GT_users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

SET FOREIGN_KEY_CHECKS = 1;

-- 3. USUARIO ADMINISTRADOR INICIAL
-- User: admin_gym / Pass: Admin@Gym2026
INSERT IGNORE INTO `GT_users` (`username`, `email`, `role`, `is_active`, `password_hash`) 
VALUES ('admin_gym', 'admin@gymtraker.com', 'admin', 1, '$2y$10$X8O.U6v6O0.y2Xn7Y6v6O.y2Xn7Y6v6O.y2Xn7Y6v6O.y2Xn7Y6v6O'); -- Nota: Cambiar hash por uno real generado con password_hash()

-- 4. CONFIGURACIÓN DEL USUARIO DE LA APLICACIÓN (DB USER)
-- CREATE USER IF NOT EXISTS 'gtrkr'@'localhost' IDENTIFIED BY 'PASSWORD_SEGURO_AQUI';
-- GRANT SELECT, INSERT, UPDATE, DELETE ON `gymtracker`.* TO 'gtrkr'@'localhost';
-- FLUSH PRIVILEGES;

-- 5. CATÁLOGO BASE DE EJERCICIOS (EJEMPLOS)
INSERT IGNORE INTO `GT_exercises` (`name`, `muscle_group`, `category`) VALUES
('Press de Banca', 'Pecho', 'Fuerza'),
('Sentadilla Libre', 'Piernas', 'Fuerza'),
('Peso Muerto', 'Espalda', 'Fuerza'),
('Press Militar', 'Hombros', 'Fuerza'),
('Dominadas', 'Espalda', 'Calistenia'),
('Curl de Biceps', 'Brazos', 'Fuerza'),
('Press Francés', 'Brazos', 'Fuerza'),
('Zancadas', 'Piernas', 'Fuerza'),
('Plancha Abdominal', 'Core', 'Otros');
