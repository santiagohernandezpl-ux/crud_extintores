<?php
/**
 * Capa de acceso a datos (Repository).
 * Toda la lógica SQL vive aquí; los endpoints de /api solo orquestan.
 * TODAS las consultas usan sentencias preparadas con parámetros nombrados.
 */

declare(strict_types=1);

final class ExtintorRepository
{
    private const COLUMNAS = 'id, codigo_serie, tipo_agente, capacidad, cliente_ubicacion,
                              fecha_recarga, fecha_vencimiento, estado';

    public function __construct(private PDO $pdo)
    {
    }

    /** Lista todos los extintores; si $busqueda no está vacía filtra por código o cliente. */
    public function listar(string $busqueda = ''): array
    {
        if ($busqueda === '') {
            $stmt = $this->pdo->prepare('SELECT ' . self::COLUMNAS . ' FROM extintores ORDER BY fecha_vencimiento ASC');
            $stmt->execute();
            return $stmt->fetchAll();
        }

        // El comodín % se añade al VALOR, nunca concatenado en el SQL.
        $stmt = $this->pdo->prepare(
            'SELECT ' . self::COLUMNAS . ' FROM extintores
             WHERE codigo_serie LIKE :q1 OR cliente_ubicacion LIKE :q2
             ORDER BY fecha_vencimiento ASC'
        );
        $patron = '%' . $this->escaparLike($busqueda) . '%';
        $stmt->execute([':q1' => $patron, ':q2' => $patron]);
        return $stmt->fetchAll();
    }

    public function buscarPorId(int $id): ?array
    {
        $stmt = $this->pdo->prepare('SELECT ' . self::COLUMNAS . ' FROM extintores WHERE id = :id');
        $stmt->execute([':id' => $id]);
        $fila = $stmt->fetch();
        return $fila === false ? null : $fila;
    }

    /** Inserta y devuelve el id generado. */
    public function crear(array $d): int
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO extintores
                (codigo_serie, tipo_agente, capacidad, cliente_ubicacion, fecha_recarga, fecha_vencimiento, estado)
             VALUES
                (:codigo_serie, :tipo_agente, :capacidad, :cliente_ubicacion, :fecha_recarga, :fecha_vencimiento, :estado)'
        );
        $stmt->execute($this->parametros($d));
        return (int) $this->pdo->lastInsertId();
    }

    /** Actualiza un registro. Devuelve true si el id existe. */
    public function actualizar(int $id, array $d): bool
    {
        if ($this->buscarPorId($id) === null) {
            return false;
        }
        $stmt = $this->pdo->prepare(
            'UPDATE extintores SET
                codigo_serie      = :codigo_serie,
                tipo_agente       = :tipo_agente,
                capacidad         = :capacidad,
                cliente_ubicacion = :cliente_ubicacion,
                fecha_recarga     = :fecha_recarga,
                fecha_vencimiento = :fecha_vencimiento,
                estado            = :estado
             WHERE id = :id'
        );
        $params = $this->parametros($d);
        $params[':id'] = $id;
        $stmt->execute($params);
        return true;
    }

    /** Elimina un registro. Devuelve true si se borró alguna fila. */
    public function eliminar(int $id): bool
    {
        $stmt = $this->pdo->prepare('DELETE FROM extintores WHERE id = :id');
        $stmt->execute([':id' => $id]);
        return $stmt->rowCount() > 0;
    }

    /** ¿Existe ya ese código de serie? (opcionalmente excluyendo un id al editar) */
    public function existeCodigo(string $codigo, ?int $excluirId = null): bool
    {
        $stmt = $this->pdo->prepare(
            'SELECT COUNT(*) FROM extintores WHERE codigo_serie = :codigo AND id <> :id'
        );
        $stmt->execute([':codigo' => $codigo, ':id' => $excluirId ?? 0]);
        return (int) $stmt->fetchColumn() > 0;
    }

    private function parametros(array $d): array
    {
        return [
            ':codigo_serie'      => $d['codigo_serie'],
            ':tipo_agente'       => $d['tipo_agente'],
            ':capacidad'         => $d['capacidad'],
            ':cliente_ubicacion' => $d['cliente_ubicacion'],
            ':fecha_recarga'     => $d['fecha_recarga'],
            ':fecha_vencimiento' => $d['fecha_vencimiento'],
            ':estado'            => $d['estado'],
        ];
    }

    /** Escapa los comodines de LIKE para que el usuario no pueda inyectarlos. */
    private function escaparLike(string $texto): string
    {
        return addcslashes($texto, '\\%_');
    }
}
