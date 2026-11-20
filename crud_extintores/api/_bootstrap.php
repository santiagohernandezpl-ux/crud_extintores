<?php
/**
 * Archivo común para todos los endpoints de la API.
 * - Carga conexión, repositorio y validador.
 * - Fija cabeceras JSON y de seguridad.
 * - Ofrece utilidades: responder(), exigirMetodo(), leerJson(), verificarCsrf().
 */

declare(strict_types=1);

require_once __DIR__ . '/../config/conexion.php';
require_once __DIR__ . '/../src/ExtintorRepository.php';
require_once __DIR__ . '/../src/ExtintorValidador.php';

session_start();

header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');
header('Cache-Control: no-store');

/** Envía una respuesta JSON uniforme y termina la ejecución. */
function responder(int $codigoHttp, array $cuerpo): never
{
    http_response_code($codigoHttp);
    echo json_encode($cuerpo, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

/** Rechaza la petición si no usa el método HTTP esperado. */
function exigirMetodo(string $metodo): void
{
    if ($_SERVER['REQUEST_METHOD'] !== $metodo) {
        header('Allow: ' . $metodo);
        responder(405, ['ok' => false, 'mensaje' => 'Método no permitido.']);
    }
}

/** Lee el cuerpo JSON de la petición (Fetch API). */
function leerJson(): array
{
    $crudo = file_get_contents('php://input') ?: '';
    $datos = json_decode($crudo, true);
    if (!is_array($datos)) {
        responder(400, ['ok' => false, 'mensaje' => 'Cuerpo JSON inválido.']);
    }
    return $datos;
}

/**
 * Protección CSRF: el token se genera en index.php y el frontend lo envía
 * en la cabecera X-CSRF-Token en cada operación que modifica datos.
 */
function verificarCsrf(): void
{
    $enviado = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
    $sesion  = $_SESSION['csrf_token'] ?? '';
    if ($sesion === '' || !hash_equals($sesion, $enviado)) {
        responder(403, ['ok' => false, 'mensaje' => 'Token de seguridad inválido. Recargue la página.']);
    }
}

/** Valida que un id sea un entero positivo. */
function idValido(mixed $valor): int
{
    $id = filter_var($valor, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
    if ($id === false) {
        responder(400, ['ok' => false, 'mensaje' => 'Identificador inválido.']);
    }
    return $id;
}

/** Construye el repositorio o responde 500 si la BD no está disponible. */
function repositorio(): ExtintorRepository
{
    try {
        return new ExtintorRepository(obtenerConexion());
    } catch (RuntimeException $e) {
        responder(500, ['ok' => false, 'mensaje' => $e->getMessage()]);
    }
}
