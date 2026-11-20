<?php
/**
 * Validación y sanitización de datos en el BACKEND.
 * Nunca se confía en la validación del navegador: aquí se repite todo.
 */

declare(strict_types=1);

final class ExtintorValidador
{
    public const TIPOS_AGENTE = ['ABC Polvo Químico', 'CO2', 'Agua a Presión', 'Solkaflam', 'Tipo K'];
    public const ESTADOS      = ['Vigente', 'Próximo a vencer', 'Vencido', 'En mantenimiento'];

    /** @var array<string,string> errores por campo */
    private array $errores = [];

    /** @var array<string,string> datos limpios */
    private array $datos = [];

    public function validar(array $entrada): bool
    {
        $this->errores = [];
        $this->datos   = [];

        // 1) Sanitizar: quitar espacios, etiquetas HTML y caracteres de control.
        $codigo    = $this->limpiar($entrada['codigo_serie'] ?? '');
        $tipo      = $this->limpiar($entrada['tipo_agente'] ?? '');
        $capacidad = $this->limpiar($entrada['capacidad'] ?? '');
        $cliente   = $this->limpiar($entrada['cliente_ubicacion'] ?? '');
        $fRecarga  = $this->limpiar($entrada['fecha_recarga'] ?? '');
        $fVence    = $this->limpiar($entrada['fecha_vencimiento'] ?? '');
        $estado    = $this->limpiar($entrada['estado'] ?? '');

        // 2) Validar reglas de negocio.
        $codigo = strtoupper($codigo);
        if (!preg_match('/^[A-Z0-9\-]{3,50}$/', $codigo)) {
            $this->errores['codigo_serie'] = 'Use 3 a 50 caracteres: letras, números y guiones.';
        }

        if (!in_array($tipo, self::TIPOS_AGENTE, true)) {
            $this->errores['tipo_agente'] = 'Seleccione un tipo de agente válido.';
        }

        // Ej: "5 lbs", "10 lbs", "2.5 kg"
        if (!preg_match('/^\d{1,3}([.,]\d{1,2})?\s?(lbs|lb|kg)$/i', $capacidad)) {
            $this->errores['capacidad'] = 'Formato esperado: número + unidad (ej. 10 lbs, 4.5 kg).';
        }

        $largoCliente = mb_strlen($cliente);
        if ($largoCliente < 3 || $largoCliente > 150) {
            $this->errores['cliente_ubicacion'] = 'Ingrese entre 3 y 150 caracteres.';
        }

        $dRecarga = $this->fecha($fRecarga);
        $dVence   = $this->fecha($fVence);
        if ($dRecarga === null) {
            $this->errores['fecha_recarga'] = 'Fecha de recarga inválida.';
        }
        if ($dVence === null) {
            $this->errores['fecha_vencimiento'] = 'Fecha de vencimiento inválida.';
        }
        if ($dRecarga && $dVence && $dVence <= $dRecarga) {
            $this->errores['fecha_vencimiento'] = 'Debe ser posterior a la fecha de recarga.';
        }

        if (!in_array($estado, self::ESTADOS, true)) {
            $this->errores['estado'] = 'Seleccione un estado válido.';
        }

        if ($this->errores === []) {
            $this->datos = [
                'codigo_serie'      => $codigo,
                'tipo_agente'       => $tipo,
                'capacidad'         => $this->normalizarCapacidad($capacidad),
                'cliente_ubicacion' => $cliente,
                'fecha_recarga'     => $dRecarga->format('Y-m-d'),
                'fecha_vencimiento' => $dVence->format('Y-m-d'),
                'estado'            => $estado,
            ];
        }

        return $this->errores === [];
    }

    public function errores(): array
    {
        return $this->errores;
    }

    public function datos(): array
    {
        return $this->datos;
    }

    private function limpiar(mixed $valor): string
    {
        if (!is_scalar($valor)) {
            return '';
        }
        $texto = strip_tags((string) $valor);
        $texto = preg_replace('/[\x00-\x1F\x7F]/u', '', $texto) ?? '';
        return trim($texto);
    }

    /** Valida formato estricto AAAA-MM-DD y que la fecha exista en el calendario. */
    private function fecha(string $valor): ?DateTimeImmutable
    {
        $d = DateTimeImmutable::createFromFormat('!Y-m-d', $valor);
        return ($d && $d->format('Y-m-d') === $valor) ? $d : null;
    }

    /** "10LBS" -> "10 lbs", "4,5kg" -> "4.5 kg" */
    private function normalizarCapacidad(string $c): string
    {
        preg_match('/^(\d{1,3}(?:[.,]\d{1,2})?)\s?(lbs|lb|kg)$/i', $c, $m);
        $numero = str_replace(',', '.', $m[1]);
        $unidad = strtolower($m[2]) === 'kg' ? 'kg' : 'lbs';
        return $numero . ' ' . $unidad;
    }
}
