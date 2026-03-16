-- Active: 1766764290861@@127.0.0.1@3306@daytracker
-- Base de datos para DayTracker (Fork de GymTracker)

CREATE DATABASE IF NOT EXISTS daytracker;

USE daytracker;

-- Tabla de Usuarios
CREATE TABLE IF NOT EXISTS DT_users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) NOT NULL UNIQUE,
    email VARCHAR(100) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    role VARCHAR(20) DEFAULT 'user',
    is_active TINYINT(1) DEFAULT 1,
    avatar_emoji VARCHAR(10) DEFAULT '🚀',
    weekly_goal INT DEFAULT 15,
    google_id VARCHAR(255) DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Tabla de Categorías de Tareas (Ej: Trabajo, Personal, Salud)
CREATE TABLE IF NOT EXISTS DT_categories (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL UNIQUE,
    color VARCHAR(20) DEFAULT '#007bff',
    description TEXT
);

-- Tabla de Tareas (Catálogo de tareas recurrentes o comunes)
CREATE TABLE IF NOT EXISTS DT_tasks (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL UNIQUE,
    category_id INT,
    description TEXT,
    FOREIGN KEY (category_id) REFERENCES DT_categories (id) ON DELETE SET NULL
);

-- Tabla de Planificación Diaria
CREATE TABLE IF NOT EXISTS DT_daily_plans (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    plan_date DATE NOT NULL,
    plan_name VARCHAR(100) DEFAULT 'Plan del Día',
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES DT_users (id) ON DELETE CASCADE
);

-- Tabla de Registros de Tareas por Día
CREATE TABLE IF NOT EXISTS DT_task_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    plan_id INT NOT NULL,
    task_id INT NOT NULL,
    status ENUM(
        'pending',
        'in_progress',
        'completed',
        'cancelled'
    ) DEFAULT 'pending',
    log_time TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (plan_id) REFERENCES DT_daily_plans (id) ON DELETE CASCADE,
    FOREIGN KEY (task_id) REFERENCES DT_tasks (id) ON DELETE CASCADE
);



-- Semillas de Datos

-- Insertar algunas categorías de ejemplo
INSERT IGNORE INTO
    DT_categories (name, color)
VALUES ('Trabajo', '#dc3545'),
    ('Personal', '#28a745'),
    ('Salud/Deporte', '#007bff'),
    ('Estudio', '#ffc107');

-- Insertar algunas tareas de ejemplo
INSERT IGNORE INTO
    DT_tasks (name, category_id)
VALUES ('Revisar correos', 1),
    ('Reunión de equipo', 1),
    ('Hacer ejercicio', 3),
    ('Meditar', 2),
    ('Leer 30 min', 2);

-- Tabla de Rutina Semanal (Base para el Horario)
CREATE TABLE IF NOT EXISTS DT_weekly_routine (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    day_of_week TINYINT NOT NULL COMMENT '0=Sunday, 1=Monday, ..., 6=Saturday',
    task_id INT NOT NULL,
    task_order INT DEFAULT 0,
    FOREIGN KEY (user_id) REFERENCES DT_users (id) ON DELETE CASCADE,
    FOREIGN KEY (task_id) REFERENCES DT_tasks (id) ON DELETE CASCADE
);