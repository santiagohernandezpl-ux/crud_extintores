-- =============================================================
--  ExtinSeguridad · Sistema de gestión e inventario de extintores
--  Script de creación de base de datos (MySQL 5.7+ / MariaDB 10.3+)
-- =============================================================

CREATE DATABASE IF NOT EXISTS gestion_extintores
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

USE gestion_extintores;

DROP TABLE IF EXISTS extintores;

CREATE TABLE extintores (
    id                INT UNSIGNED NOT NULL AUTO_INCREMENT,
    codigo_serie      VARCHAR(50)  NOT NULL,
    tipo_agente       ENUM('ABC Polvo Químico', 'CO2', 'Agua a Presión', 'Solkaflam', 'Tipo K') NOT NULL,
    capacidad         VARCHAR(20)  NOT NULL,
    cliente_ubicacion VARCHAR(150) NOT NULL,
    fecha_recarga     DATE         NOT NULL,
    fecha_vencimiento DATE         NOT NULL,
    estado            ENUM('Vigente', 'Próximo a vencer', 'Vencido', 'En mantenimiento') NOT NULL DEFAULT 'Vigente',
    creado_en         TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    actualizado_en    TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_codigo_serie (codigo_serie),
    KEY idx_cliente (cliente_ubicacion),
    KEY idx_estado (estado)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -------------------------------------------------------------
--  Registros de ejemplo
-- -------------------------------------------------------------
INSERT INTO extintores
    (codigo_serie, tipo_agente, capacidad, cliente_ubicacion, fecha_recarga, fecha_vencimiento, estado)
VALUES
    ('EXT-ABC-0001', 'ABC Polvo Químico', '10 lbs', 'Clínica Medilaser - Urgencias',     '2026-06-15', '2027-06-15', 'Vigente'),
    ('EXT-CO2-0002', 'CO2',               '20 lbs', 'Centro Comercial San Pedro - Bodega', '2025-12-10', '2026-12-10', 'Próximo a vencer'),
    ('EXT-K-0003',   'Tipo K',            '5 lbs',  'Restaurante El Fogón Huilense - Cocina', '2025-08-01', '2026-08-01', 'Vencido');
