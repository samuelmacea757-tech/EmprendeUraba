<?php
/**
 * Acceso a la base de datos (capa de modelo básica).
 *
 * Todas las consultas se ejecutan como consultas preparadas, lo que previene
 * la inyección SQL: los valores nunca se concatenan dentro del texto SQL.
 */

/**
 * Devuelve la conexión PDO única (patrón singleton).
 */
function db(): PDO
{
    static $conexion = null;

    if ($conexion === null) {
        $dsn = 'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4';
        $conexion = new PDO($dsn, DB_USER, DB_PASS, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]);
    }

    return $conexion;
}

/**
 * Ejecuta una consulta preparada y devuelve el statement.
 *
 * @param string $sql        Consulta con marcadores "?".
 * @param array  $parametros Valores que reemplazan a los marcadores.
 */
function consultar(string $sql, array $parametros = []): PDOStatement
{
    $sentencia = db()->prepare($sql);
    $sentencia->execute($parametros);

    return $sentencia;
}

/**
 * Devuelve todas las filas de una consulta.
 */
function consultar_todos(string $sql, array $parametros = []): array
{
    return consultar($sql, $parametros)->fetchAll();
}

/**
 * Devuelve la primera fila de una consulta, o null si no hay resultados.
 */
function consultar_uno(string $sql, array $parametros = []): ?array
{
    $fila = consultar($sql, $parametros)->fetch();

    return $fila ?: null;
}

/**
 * Devuelve el valor de la primera columna de la primera fila (por ejemplo
 * un COUNT o un SUM), o null si no hay resultados.
 *
 * @return mixed
 */
function consultar_valor(string $sql, array $parametros = [])
{
    $valor = consultar($sql, $parametros)->fetchColumn();

    return $valor === false ? null : $valor;
}

// --- Alias cortos (los usan las páginas existentes) ---
// Se mantienen por compatibilidad; en código nuevo usar los nombres largos.

function q(string $sql, array $parametros = []): PDOStatement
{
    return consultar($sql, $parametros);
}

function qa(string $sql, array $parametros = []): array
{
    return consultar_todos($sql, $parametros);
}

function q1(string $sql, array $parametros = []): ?array
{
    return consultar_uno($sql, $parametros);
}

function qv(string $sql, array $parametros = [])
{
    return consultar_valor($sql, $parametros);
}