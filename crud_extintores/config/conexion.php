<?php
/**
 * Conexión segura a MySQL mediante PDO.
 *
 * - Codificación utf8mb4 (soporta tildes, ñ y emojis).
 * - Errores como excepciones (ERRMODE_EXCEPTION).
 * - Emulación de consultas preparadas DESACTIVADA: MySQL prepara la
 *   sentencia de verdad, separando código SQL y datos (anti inyección SQL).
 *
 * Ajusta las constantes según tu entorno (XAMPP / Laragon).
 */

declare(strict_types=1);

const DB_HOST    = '127.0.0.1';
const DB_PORT    = '3306';
const DB_NAME    = 'gestion_extintores';
const DB_USER    = 'root';
const DB_PASS    = '';          // XAMPP y Laragon traen root sin contraseña por defecto
const DB_CHARSET = 'utf8mb4';

/**
 * Devuelve una única instancia de PDO por petición (patrón singleton simple).
 *
 * @throws RuntimeException si no se puede conectar.
 */
function obtenerConexion(): PDO
{
    static $pdo = null;

    if ($pdo instanceof PDO) {
        return $pdo;
    }

    $dsn = sprintf(
        'mysql:host=%s;port=%s;dbname=%s;charset=%s',
        DB_HOST, DB_PORT, DB_NAME, DB_CHARSET
    );

    $opciones = [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
        PDO::ATTR_STRINGIFY_FETCHES  => false,
    ];

    try {
        $pdo = new PDO($dsn, DB_USER, DB_PASS, $opciones);
        return $pdo;
    } catch (PDOException $e) {
        // Se registra el detalle en el log del servidor, nunca se muestra al usuario.
        error_log('[ExtinSeguridad] Error de conexión: ' . $e->getMessage());
        throw new RuntimeException('No fue posible conectar con la base de datos.');
    }
}
