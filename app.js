/**
 * ExtinSeguridad · Lógica del frontend (ES6+, Fetch API)
 * - Comunicación asíncrona con /api (JSON)
 * - Validación en el cliente antes de enviar (el backend vuelve a validar)
 * - Renderizado seguro: se usa textContent, nunca innerHTML con datos del usuario (anti XSS)
 */

'use strict';

const API = {
    leer:       'api/leer.php',
    crear:      'api/crear.php',
    actualizar: 'api/actualizar.php',
    eliminar:   'api/eliminar.php',
};

const CSRF = document.querySelector('meta[name="csrf-token"]').content;
const DIAS_ALERTA = 30; // días antes del vencimiento para considerarlo "Próximo a vencer"

// ---------- Referencias DOM ----------
const $ = (sel) => document.querySelector(sel);
const tablaCuerpo  = $('#tablaCuerpo');
const buscador     = $('#buscador');
const modalForm    = $('#modalForm');
const modalElim    = $('#modalEliminar');
const form         = $('#formExtintor');
const btnGuardar   = $('#btnGuardar');
const ayudaEstado  = $('#ayudaEstado');

let idPorEliminar = null;

// ============================================================
//  Utilidades
// ============================================================

/** Wrapper de fetch que siempre devuelve JSON y lanza error si la API falla. */
async function peticion(url, { method = 'GET', body } = {}) {
    const opciones = { method, headers: { 'Accept': 'application/json' } };
    if (method !== 'GET') opciones.headers['X-CSRF-Token'] = CSRF;
    if (body) {
        opciones.headers['Content-Type'] = 'application/json';
        opciones.body = JSON.stringify(body);
    }

    const resp = await fetch(url, opciones);
    let datos;
    try {
        datos = await resp.json();
    } catch {
        throw { mensaje: 'Respuesta inválida del servidor.' };
    }
    if (!resp.ok || !datos.ok) throw datos;
    return datos;
}

/** Retrasa la ejecución mientras el usuario escribe. */
function debounce(fn, ms = 300) {
    let t;
    return (...args) => { clearTimeout(t); t = setTimeout(() => fn(...args), ms); };
}

/** "2026-06-15" -> "15/06/2026" */
function formatoFecha(iso) {
    const [a, m, d] = iso.split('-');
    return `${d}/${m}/${a}`;
}

function toast(mensaje, tipo = 'ok') {
    const el = document.createElement('div');
    el.className = `toast ${tipo === 'error' ? 'toast--error' : ''}`;
    el.textContent = mensaje;
    $('#toasts').appendChild(el);
    setTimeout(() => el.remove(), 4000);
}

const CLASE_BADGE = {
    'Vigente':          'badge--vigente',
    'Próximo a vencer': 'badge--proximo',
    'Vencido':          'badge--vencido',
    'En mantenimiento': 'badge--mantenimiento',
};

/** Sugiere el estado según la fecha de vencimiento. */
function estadoSugerido(fechaVencimiento) {
    if (!fechaVencimiento) return null;
    const hoy = new Date(); hoy.setHours(0, 0, 0, 0);
    const vence = new Date(fechaVencimiento + 'T00:00:00');
    const dias = Math.round((vence - hoy) / 86400000);
    if (dias < 0) return 'Vencido';
    if (dias <= DIAS_ALERTA) return 'Próximo a vencer';
    return 'Vigente';
}

// ============================================================
//  READ · Listado y búsqueda
// ============================================================

async function cargarExtintores(q = '') {
    try {
        const url = q ? `${API.leer}?q=${encodeURIComponent(q)}` : API.leer;
        const { data } = await peticion(url);
        renderTabla(data, q);
        if (!q) actualizarKpis(data);
    } catch (err) {
        tablaCuerpo.replaceChildren(filaMensaje(err.mensaje || 'No se pudo cargar el inventario.'));
    }
}

function filaMensaje(texto) {
    const tr = document.createElement('tr');
    const td = document.createElement('td');
    td.colSpan = 8;
    td.className = 'tabla__vacio';
    td.textContent = texto;
    tr.appendChild(td);
    return tr;
}

function celda(texto, etiqueta, clase = '') {
    const td = document.createElement('td');
    td.textContent = texto;
    td.dataset.label = etiqueta;       // usado por el CSS en móviles
    if (clase) td.className = clase;
    return td;
}

function renderTabla(lista, q) {
    if (!lista.length) {
        tablaCuerpo.replaceChildren(filaMensaje(q ? `Sin resultados para “${q}”.` : 'Aún no hay extintores registrados.'));
        return;
    }

    const filas = lista.map((ext) => {
        const tr = document.createElement('tr');
        tr.append(
            celda(ext.codigo_serie, 'Código', 'codigo'),
            celda(ext.tipo_agente, 'Agente'),
            celda(ext.capacidad, 'Capacidad'),
            celda(ext.cliente_ubicacion, 'Cliente', 'cliente'),
            celda(formatoFecha(ext.fecha_recarga), 'Recarga'),
            celda(formatoFecha(ext.fecha_vencimiento), 'Vence'),
        );

        // Badge de estado
        const tdEstado = celda('', 'Estado');
        const badge = document.createElement('span');
        badge.className = `badge ${CLASE_BADGE[ext.estado] ?? ''}`;
        badge.textContent = ext.estado;
        tdEstado.appendChild(badge);

        // Acciones
        const tdAcc = celda('', 'Acciones');
        const caja = document.createElement('div');
        caja.className = 'acciones';
        const bEditar = document.createElement('button');
        bEditar.className = 'accion accion--editar';
        bEditar.textContent = 'Editar';
        bEditar.addEventListener('click', () => abrirEdicion(ext.id));
        const bElim = document.createElement('button');
        bElim.className = 'accion accion--eliminar';
        bElim.textContent = 'Eliminar';
        bElim.addEventListener('click', () => pedirConfirmacion(ext));
        caja.append(bEditar, bElim);
        tdAcc.appendChild(caja);

        tr.append(tdEstado, tdAcc);
        return tr;
    });

    tablaCuerpo.replaceChildren(...filas);
}

function actualizarKpis(lista) {
    const contar = (e) => lista.filter((x) => x.estado === e).length;
    $('#kpiTotal').textContent   = lista.length;
    $('#kpiVigente').textContent = contar('Vigente');
    $('#kpiProximo').textContent = contar('Próximo a vencer');
    $('#kpiVencido').textContent = contar('Vencido');
}

buscador.addEventListener('input', debounce(() => cargarExtintores(buscador.value.trim())));

// ============================================================
//  Modal (abrir / cerrar)
// ============================================================

function abrirModal(modal) {
    modal.hidden = false;
    document.body.style.overflow = 'hidden';
    modal.querySelector('input:not([type=hidden]), select, button.btn')?.focus();
}

function cerrarModal(modal) {
    modal.hidden = true;
    document.body.style.overflow = '';
}

document.querySelectorAll('[data-cerrar]').forEach((el) =>
    el.addEventListener('click', () => cerrarModal(el.closest('.modal')))
);
document.addEventListener('keydown', (e) => {
    if (e.key === 'Escape') document.querySelectorAll('.modal:not([hidden])').forEach(cerrarModal);
});

// ============================================================
//  CREATE / UPDATE · Formulario
// ============================================================

function limpiarErrores() {
    form.querySelectorAll('.campo').forEach((c) => {
        c.classList.remove('invalido');
        c.querySelector('.campo__error').textContent = '';
    });
}

function marcarError(nombre, mensaje) {
    const campo = form.elements[nombre]?.closest('.campo');
    if (!campo) return;
    campo.classList.add('invalido');
    campo.querySelector('.campo__error').textContent = mensaje;
}

$('#btnNuevo').addEventListener('click', () => {
    form.reset();
    form.elements.id.value = '';
    limpiarErrores();
    ayudaEstado.textContent = '';
    form.elements.fecha_recarga.value = new Date().toISOString().slice(0, 10);
    $('#modalTitulo').textContent = 'Registrar extintor';
    btnGuardar.textContent = 'Guardar';
    abrirModal(modalForm);
});

async function abrirEdicion(id) {
    try {
        const { data } = await peticion(`${API.leer}?id=${encodeURIComponent(id)}`);
        limpiarErrores();
        ['id', 'codigo_serie', 'tipo_agente', 'capacidad', 'cliente_ubicacion',
         'fecha_recarga', 'fecha_vencimiento', 'estado'].forEach((k) => { form.elements[k].value = data[k]; });
        mostrarSugerencia();
        $('#modalTitulo').textContent = `Editar ${data.codigo_serie}`;
        btnGuardar.textContent = 'Actualizar';
        abrirModal(modalForm);
    } catch (err) {
        toast(err.mensaje || 'No se pudo cargar el extintor.', 'error');
    }
}

/** Al cambiar la fecha de vencimiento se sugiere (y aplica) el estado correspondiente. */
function mostrarSugerencia(aplicar = false) {
    const sugerido = estadoSugerido(form.elements.fecha_vencimiento.value);
    if (!sugerido) { ayudaEstado.textContent = ''; return; }
    if (aplicar && form.elements.estado.value !== 'En mantenimiento') {
        form.elements.estado.value = sugerido;
    }
    ayudaEstado.textContent = `Según la fecha de vencimiento: ${sugerido}`;
}
form.elements.fecha_vencimiento.addEventListener('change', () => mostrarSugerencia(true));

/** Validación del lado del cliente (espejo de ExtintorValidador.php). */
function validarFormulario(d) {
    const errores = {};
    if (!/^[A-Za-z0-9-]{3,50}$/.test(d.codigo_serie))
        errores.codigo_serie = 'Use 3 a 50 caracteres: letras, números y guiones.';
    if (!d.tipo_agente)
        errores.tipo_agente = 'Seleccione el tipo de agente.';
    if (!/^\d{1,3}([.,]\d{1,2})?\s?(lbs|lb|kg)$/i.test(d.capacidad))
        errores.capacidad = 'Formato: número + unidad (ej. 10 lbs).';
    if (d.cliente_ubicacion.length < 3 || d.cliente_ubicacion.length > 150)
        errores.cliente_ubicacion = 'Ingrese entre 3 y 150 caracteres.';
    if (!d.fecha_recarga)
        errores.fecha_recarga = 'Indique la fecha de recarga.';
    if (!d.fecha_vencimiento)
        errores.fecha_vencimiento = 'Indique la fecha de vencimiento.';
    else if (d.fecha_recarga && d.fecha_vencimiento <= d.fecha_recarga)
        errores.fecha_vencimiento = 'Debe ser posterior a la fecha de recarga.';
    if (!d.estado)
        errores.estado = 'Seleccione el estado.';
    return errores;
}

form.addEventListener('submit', async (e) => {
    e.preventDefault();
    limpiarErrores();

    const datos = Object.fromEntries(
        [...new FormData(form)].map(([k, v]) => [k, String(v).trim()])
    );
    datos.codigo_serie = datos.codigo_serie.toUpperCase();

    const errores = validarFormulario(datos);
    if (Object.keys(errores).length) {
        Object.entries(errores).forEach(([k, m]) => marcarError(k, m));
        form.querySelector('.invalido input, .invalido select')?.focus();
        return;
    }

    const { id, ...cuerpo } = datos;
    const esEdicion = Boolean(id);

    btnGuardar.disabled = true;
    try {
        const res = esEdicion
            ? await peticion(`${API.actualizar}?id=${encodeURIComponent(id)}`, { method: 'PUT', body: cuerpo })
            : await peticion(API.crear, { method: 'POST', body: cuerpo });
        toast(res.mensaje);
        cerrarModal(modalForm);
        buscador.value = '';
        cargarExtintores();
    } catch (err) {
        if (err.errores) Object.entries(err.errores).forEach(([k, m]) => marcarError(k, m));
        toast(err.mensaje || 'Ocurrió un error al guardar.', 'error');
    } finally {
        btnGuardar.disabled = false;
    }
});

// ============================================================
//  DELETE · con confirmación previa
// ============================================================

function pedirConfirmacion(ext) {
    idPorEliminar = ext.id;
    $('#elimCodigo').textContent = ext.codigo_serie;
    abrirModal(modalElim);
}

$('#btnConfirmarEliminar').addEventListener('click', async (e) => {
    if (!idPorEliminar) return;
    const btn = e.currentTarget;
    btn.disabled = true;
    try {
        const res = await peticion(`${API.eliminar}?id=${encodeURIComponent(idPorEliminar)}`, { method: 'DELETE' });
        toast(res.mensaje);
        cerrarModal(modalElim);
        cargarExtintores(buscador.value.trim());
        if (buscador.value.trim()) cargarKpisCompletos();
    } catch (err) {
        toast(err.mensaje || 'No se pudo eliminar.', 'error');
    } finally {
        btn.disabled = false;
        idPorEliminar = null;
    }
});

/** Recalcula los KPIs con todo el inventario aunque haya un filtro activo. */
async function cargarKpisCompletos() {
    try {
        const { data } = await peticion(API.leer);
        actualizarKpis(data);
    } catch { /* silencioso */ }
}

// ---------- Inicio ----------
cargarExtintores();
