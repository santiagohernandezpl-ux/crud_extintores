<?php
/**
 * ExtinSeguridad · Vista principal del CRUD de extintores.
 * Genera el token CSRF de la sesión y lo expone al JavaScript mediante <meta>.
 */

declare(strict_types=1);
session_start();

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrf = htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES, 'UTF-8');

require_once __DIR__ . '/src/ExtintorValidador.php';

/** Imprime las <option> de un <select> escapando el texto. */
function opciones(array $valores): void
{
    foreach ($valores as $v) {
        $e = htmlspecialchars($v, ENT_QUOTES, 'UTF-8');
        echo "<option value=\"{$e}\">{$e}</option>\n";
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="<?= $csrf ?>">
    <title>ExtinSeguridad · Gestión de Extintores</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="styles.css">
</head>
<body>

<header class="topbar">
    <div class="contenedor topbar__inner">
        <div class="marca">
            <span class="marca__icono" aria-hidden="true">
                <svg viewBox="0 0 24 24" width="22" height="22" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M9 3h4l1 2"/><path d="M14 5h3"/><rect x="7" y="7" width="8" height="14" rx="3"/><path d="M11 7V5"/>
                </svg>
            </span>
            <div>
                <h1 class="marca__nombre">ExtinSeguridad</h1>
                <p class="marca__lema">Venta · Recarga · Mantenimiento</p>
            </div>
        </div>
        <button type="button" class="btn btn--primario" id="btnNuevo">
            <span aria-hidden="true">＋</span> Nuevo extintor
        </button>
    </div>
</header>

<main class="contenedor">

    <!-- Tarjetas resumen -->
    <section class="resumen" aria-label="Resumen del inventario">
        <article class="kpi"><span class="kpi__valor" id="kpiTotal">0</span><span class="kpi__label">Total equipos</span></article>
        <article class="kpi kpi--ok"><span class="kpi__valor" id="kpiVigente">0</span><span class="kpi__label">Vigentes</span></article>
        <article class="kpi kpi--alerta"><span class="kpi__valor" id="kpiProximo">0</span><span class="kpi__label">Próximos a vencer</span></article>
        <article class="kpi kpi--peligro"><span class="kpi__valor" id="kpiVencido">0</span><span class="kpi__label">Vencidos</span></article>
    </section>

    <!-- Inventario -->
    <section class="panel">
        <div class="panel__cabecera">
            <h2>Inventario de extintores</h2>
            <div class="buscador">
                <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><circle cx="11" cy="11" r="7"/><path d="m20 20-3.5-3.5"/></svg>
                <input type="search" id="buscador" placeholder="Buscar por código de serie o cliente…" aria-label="Buscar extintores" maxlength="100" autocomplete="off">
            </div>
        </div>

        <div class="tabla-wrap">
            <table class="tabla">
                <thead>
                    <tr>
                        <th>Código serie</th>
                        <th>Agente</th>
                        <th>Capacidad</th>
                        <th>Cliente / Ubicación</th>
                        <th>Recarga</th>
                        <th>Vencimiento</th>
                        <th>Estado</th>
                        <th class="col-acciones">Acciones</th>
                    </tr>
                </thead>
                <tbody id="tablaCuerpo">
                    <tr><td colspan="8" class="tabla__vacio">Cargando inventario…</td></tr>
                </tbody>
            </table>
        </div>
    </section>
</main>

<!-- Modal: crear / editar -->
<div class="modal" id="modalForm" role="dialog" aria-modal="true" aria-labelledby="modalTitulo" hidden>
    <div class="modal__fondo" data-cerrar></div>
    <div class="modal__caja">
        <header class="modal__cabecera">
            <h2 id="modalTitulo">Registrar extintor</h2>
            <button type="button" class="btn-icono" data-cerrar aria-label="Cerrar">✕</button>
        </header>

        <form id="formExtintor" novalidate>
            <input type="hidden" id="id" name="id">

            <div class="grid-form">
                <div class="campo">
                    <label for="codigo_serie">Código de serie *</label>
                    <input type="text" id="codigo_serie" name="codigo_serie" required maxlength="50"
                           pattern="[A-Za-z0-9\-]{3,50}" placeholder="EXT-ABC-0004">
                    <small class="campo__error"></small>
                </div>

                <div class="campo">
                    <label for="tipo_agente">Tipo de agente *</label>
                    <select id="tipo_agente" name="tipo_agente" required>
                        <option value="">Seleccione…</option>
                        <?php opciones(ExtintorValidador::TIPOS_AGENTE); ?>
                    </select>
                    <small class="campo__error"></small>
                </div>

                <div class="campo">
                    <label for="capacidad">Capacidad *</label>
                    <input type="text" id="capacidad" name="capacidad" required list="capacidades" placeholder="10 lbs">
                    <datalist id="capacidades">
                        <option value="5 lbs"><option value="10 lbs"><option value="15 lbs"><option value="20 lbs"><option value="30 lbs">
                    </datalist>
                    <small class="campo__error"></small>
                </div>

                <div class="campo">
                    <label for="estado">Estado *</label>
                    <select id="estado" name="estado" required>
                        <?php opciones(ExtintorValidador::ESTADOS); ?>
                    </select>
                    <small class="campo__ayuda" id="ayudaEstado"></small>
                    <small class="campo__error"></small>
                </div>

                <div class="campo campo--ancho">
                    <label for="cliente_ubicacion">Cliente / Ubicación *</label>
                    <input type="text" id="cliente_ubicacion" name="cliente_ubicacion" required minlength="3" maxlength="150"
                           placeholder="Empresa XYZ - Piso 2, pasillo norte">
                    <small class="campo__error"></small>
                </div>

                <div class="campo">
                    <label for="fecha_recarga">Fecha de recarga *</label>
                    <input type="date" id="fecha_recarga" name="fecha_recarga" required>
                    <small class="campo__error"></small>
                </div>

                <div class="campo">
                    <label for="fecha_vencimiento">Fecha de vencimiento *</label>
                    <input type="date" id="fecha_vencimiento" name="fecha_vencimiento" required>
                    <small class="campo__error"></small>
                </div>
            </div>

            <footer class="modal__pie">
                <button type="button" class="btn btn--secundario" data-cerrar>Cancelar</button>
                <button type="submit" class="btn btn--primario" id="btnGuardar">Guardar</button>
            </footer>
        </form>
    </div>
</div>

<!-- Modal: confirmar eliminación -->
<div class="modal" id="modalEliminar" role="alertdialog" aria-modal="true" aria-labelledby="elimTitulo" hidden>
    <div class="modal__fondo" data-cerrar></div>
    <div class="modal__caja modal__caja--sm">
        <header class="modal__cabecera">
            <h2 id="elimTitulo">¿Eliminar extintor?</h2>
        </header>
        <p class="modal__texto">Se eliminará permanentemente el equipo <strong id="elimCodigo"></strong>. Esta acción no se puede deshacer.</p>
        <footer class="modal__pie">
            <button type="button" class="btn btn--secundario" data-cerrar>Cancelar</button>
            <button type="button" class="btn btn--peligro" id="btnConfirmarEliminar">Sí, eliminar</button>
        </footer>
    </div>
</div>

<!-- Notificaciones -->
<div class="toasts" id="toasts" aria-live="polite"></div>

<script src="app.js" defer></script>
</body>
</html>
