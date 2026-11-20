<?php
/**
 * UPDATE · PUT /api/actualizar.php?id=5   (cuerpo JSON)
 */

declare(strict_types=1);
require_once __DIR__ . '/_bootstrap.php';

exigirMetodo('PUT');
verificarCsrf();

$id = idValido($_GET['id'] ?? null);

$validador = new ExtintorValidador();
if (!$validador->validar(leerJson())) {
    responder(422, ['ok' => false, 'mensaje' => 'Revise los campos marcados.', 'errores' => $validador->errores()]);
}

$datos = $validador->datos();
$repo  = repositorio();

try {
    if ($repo->existeCodigo($datos['codigo_serie'], $id)) {
        responder(409, [
            'ok'      => false,
            'mensaje' => 'Otro extintor ya usa ese código de serie.',
            'errores' => ['codigo_serie' => 'Este código ya existe.'],
        ]);
    }

    if (!$repo->actualizar($id, $datos)) {
        responder(404, ['ok' => false, 'mensaje' => 'Extintor no encontrado.']);
    }

    responder(200, ['ok' => true, 'mensaje' => 'Extintor actualizado correctamente.']);
} catch (PDOException $e) {
    if ($e->getCode() === '23000') {
        responder(409, ['ok' => false, 'mensaje' => 'Otro extintor ya usa ese código de serie.']);
    }
    error_log('[ExtinSeguridad] actualizar: ' . $e->getMessage());
    responder(500, ['ok' => false, 'mensaje' => 'No se pudo actualizar el extintor.']);
}
