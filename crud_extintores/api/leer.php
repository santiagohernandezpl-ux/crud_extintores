<?php
/**
 * READ · GET /api/leer.php            → lista todos
 *        GET /api/leer.php?q=texto     → filtra por código de serie o cliente
 *        GET /api/leer.php?id=5        → un solo registro (para cargar el formulario de edición)
 */

declare(strict_types=1);
require_once __DIR__ . '/_bootstrap.php';

exigirMetodo('GET');
$repo = repositorio();

try {
    if (isset($_GET['id'])) {
        $extintor = $repo->buscarPorId(idValido($_GET['id']));
        if ($extintor === null) {
            responder(404, ['ok' => false, 'mensaje' => 'Extintor no encontrado.']);
        }
        responder(200, ['ok' => true, 'data' => $extintor]);
    }

    $q = isset($_GET['q']) && is_string($_GET['q']) ? trim(strip_tags($_GET['q'])) : '';
    $q = mb_substr($q, 0, 100); // limita la longitud de búsqueda

    $lista = $repo->listar($q);
    responder(200, ['ok' => true, 'total' => count($lista), 'data' => $lista]);
} catch (PDOException $e) {
    error_log('[ExtinSeguridad] leer: ' . $e->getMessage());
    responder(500, ['ok' => false, 'mensaje' => 'Error al consultar los extintores.']);
}
