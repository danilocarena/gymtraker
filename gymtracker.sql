-- Active: 1766764290861@@127.0.0.1@3306@gymtracker
-- Base de datos para GymTracker

CREATE DATABASE IF NOT EXISTS gymtracker;

USE gymtracker;

-- Tabla de Usuarios
CREATE TABLE IF NOT EXISTS GT_users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) NOT NULL UNIQUE,
    email VARCHAR(100) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    height DECIMAL(5,2) NULL,
    weight DECIMAL(5,2) NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Tabla de Ejercicios (Catálogo)
CREATE TABLE IF NOT EXISTS GT_exercises (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    muscle_group VARCHAR(50) NOT NULL,
    description TEXT
);

-- Tabla de Sesiones de Entrenamiento
CREATE TABLE IF NOT EXISTS GT_workout_sessions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    session_date DATE NOT NULL,
    session_name VARCHAR(100) DEFAULT 'Entrenamiento',
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES GT_users (id) ON DELETE CASCADE
);

-- Tabla de Registros de Ejercicios por Sesión
CREATE TABLE IF NOT EXISTS GT_session_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    session_id INT NOT NULL,
    exercise_id INT NOT NULL,
    sets INT NOT NULL DEFAULT 1,
    reps INT NOT NULL,
    weight DECIMAL(6, 2) NOT NULL, -- Peso en kg o libras
    log_time TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (session_id) REFERENCES GT_workout_sessions (id) ON DELETE CASCADE,
    FOREIGN KEY (exercise_id) REFERENCES GT_exercises (id) ON DELETE CASCADE
);

-- Insertar algunos ejercicios de ejemplo
INSERT INTO
    GT_exercises (name, muscle_group)
VALUES ('Press de Banca', 'Pecho'),
    ('Sentadilla Libre', 'Piernas'),
    ('Peso Muerto', 'Espalda'),
    ('Dominadas', 'Espalda'),
    ('Press Militar', 'Hombros'),
    ('Curl de Bíceps', 'Brazos'),
    (
        'Extensión de Tríceps',
        'Brazos'
    )
ON DUPLICATE KEY UPDATE
    name = name;