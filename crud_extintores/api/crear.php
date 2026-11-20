<?php
/**
 * CREATE · POST /api/crear.php   (cuerpo JSON)
 */

declare(strict_types=1);
require_once __DIR__ . '/_bootstrap.php';

exigirMetodo('POST');
verificarCsrf();

$validador = new ExtintorValidador();
if (!$validador->validar(leerJson())) {
    responder(422, ['ok' => false, 'mensaje' => 'Revise los campos marcados.', 'errores' => $validador->errores()]);
}

$datos = $validador->datos();
$repo  = repositorio();

try {
    if ($repo->existeCodigo($datos['codigo_serie'])) {
        responder(409, [
            'ok'      => false,
            'mensaje' => 'El código de serie ya está registrado.',
            'errores' => ['codigo_serie' => 'Este código ya existe.'],
        ]);
    }

    $id = $repo->crear($datos);
    responder(201, ['ok' => true, 'mensaje' => 'Extintor registrado correctamente.', 'id' => $id]);
} catch (PDOException $e) {
    // 23000 = violación de restricción UNIQUE (por si hubo una carrera entre dos peticiones)
    if ($e->getCode() === '23000') {
        responder(409, ['ok' => false, 'mensaje' => 'El código de serie ya está registrado.']);
    }
    error_log('[ExtinSeguridad] crear: ' . $e->getMessage());
    responder(500, ['ok' => false, 'mensaje' => 'No se pudo registrar el extintor.']);
}
