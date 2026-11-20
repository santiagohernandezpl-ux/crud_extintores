<?php
/**
 * DELETE · DELETE /api/eliminar.php?id=5
 */

declare(strict_types=1);
require_once __DIR__ . '/_bootstrap.php';

exigirMetodo('DELETE');
verificarCsrf();

$id   = idValido($_GET['id'] ?? null);
$repo = repositorio();

try {
    if (!$repo->eliminar($id)) {
        responder(404, ['ok' => false, 'mensaje' => 'El extintor no existe o ya fue eliminado.']);
    }
    responder(200, ['ok' => true, 'mensaje' => 'Extintor eliminado correctamente.']);
} catch (PDOException $e) {
    error_log('[ExtinSeguridad] eliminar: ' . $e->getMessage());
    responder(500, ['ok' => false, 'mensaje' => 'No se pudo eliminar el extintor.']);
}
