<?php
/**
 * Funciones de utilidad compartidas por todo el sistema.
 *
 * Convención: funciones y variables en snake_case y en español; constantes
 * en MAYÚSCULAS. Cada función hace una sola tarea.
 */

// ---------------------------------------------------------------------------
// Salida y navegación
// ---------------------------------------------------------------------------

/**
 * Escapa un texto para mostrarlo en HTML (protección contra XSS).
 * Debe usarse con todo dato que venga del usuario o de la base de datos.
 *
 * @param mixed $texto
 */
function e($texto): string
{
    return htmlspecialchars((string) $texto, ENT_QUOTES, 'UTF-8');
}

/**
 * Construye una URL absoluta dentro de la aplicación.
 */
function url(string $ruta = ''): string
{
    return BASE_URL . '/' . ltrim($ruta, '/');
}

/**
 * Redirige a otra página y detiene la ejecución.
 */
function redirect(string $ruta): never
{
    $destino = str_starts_with($ruta, 'http') ? $ruta : url($ruta);
    header('Location: ' . $destino);
    exit;
}

/**
 * Da formato de moneda: 12500 -> "$12.500".
 *
 * @param mixed $valor
 */
function money($valor): string
{
    return '$' . number_format((float) $valor, 0, ',', '.');
}

/**
 * Da formato de fecha dd/mm/aaaa (y hora opcional).
 */
function fdate(?string $fecha, bool $con_hora = false): string
{
    if (!$fecha) {
        return '—';
    }

    return date($con_hora ? 'd/m/Y H:i' : 'd/m/Y', strtotime($fecha));
}

/**
 * Lee y limpia un campo enviado por POST.
 */
function post(string $campo, string $por_defecto = ''): string
{
    return trim((string) ($_POST[$campo] ?? $por_defecto));
}

/**
 * Lee y limpia un parámetro enviado por GET.
 */
function get(string $campo, string $por_defecto = ''): string
{
    return trim((string) ($_GET[$campo] ?? $por_defecto));
}

/**
 * Indica si la petición actual es un envío de formulario (POST).
 */
function is_post(): bool
{
    return $_SERVER['REQUEST_METHOD'] === 'POST';
}

/**
 * URL pública de una imagen subida, o de una imagen de reemplazo.
 */
function img(?string $archivo, string $imagen_por_defecto = 'assets/img/placeholder.svg'): string
{
    return $archivo ? url('uploads/' . $archivo) : url($imagen_por_defecto);
}

// ---------------------------------------------------------------------------
// Mensajes flash (avisos que se muestran una sola vez)
// ---------------------------------------------------------------------------

/**
 * Guarda un mensaje (si se pasan tipo y texto) o devuelve y borra los
 * pendientes (si se llama sin argumentos).
 *
 * @param string|null $tipo   Clase de alerta de Bootstrap: success, danger, warning, info.
 * @param string|null $texto  Mensaje a mostrar.
 * @return array|void
 */
function flash(?string $tipo = null, ?string $texto = null)
{
    if ($tipo !== null) {
        $_SESSION['flash'][] = [$tipo, $texto];

        return;
    }

    $mensajes = $_SESSION['flash'] ?? [];
    unset($_SESSION['flash']);

    return $mensajes;
}

// ---------------------------------------------------------------------------
// Protección CSRF
// ---------------------------------------------------------------------------

/**
 * Devuelve el token CSRF de la sesión, creándolo si no existe.
 */
function csrf_token(): string
{
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
    }

    return $_SESSION['csrf'];
}

/**
 * Campo oculto con el token, para incluir dentro de cada formulario.
 */
function csrf_field(): string
{
    return '<input type="hidden" name="csrf" value="' . csrf_token() . '">';
}

/**
 * Verifica el token recibido. Si no coincide, detiene la petición.
 */
function csrf_verify(): void
{
    $token_recibido = $_POST['csrf'] ?? ($_GET['csrf'] ?? '');

    if (!hash_equals($_SESSION['csrf'] ?? '', (string) $token_recibido)) {
        http_response_code(419);
        exit('Sesión expirada o petición inválida (CSRF). Vuelve atrás y recarga la página.');
    }
}

/**
 * Agrega el token CSRF a un enlace que ejecuta una acción por GET.
 */
function csrf_url(string $url): string
{
    $separador = str_contains($url, '?') ? '&' : '?';

    return $url . $separador . 'csrf=' . csrf_token();
}

// ---------------------------------------------------------------------------
// Bitácora y notificaciones
// ---------------------------------------------------------------------------

/**
 * Registra una acción importante en la bitácora (tabla logs).
 * Nunca interrumpe la página si el registro falla.
 */
function log_activity(string $accion, string $detalle = ''): void
{
    try {
        consultar(
            'INSERT INTO logs (usuario_id, accion, detalle, ip) VALUES (?,?,?,?)',
            [
                $_SESSION['uid'] ?? null,
                $accion,
                mb_substr($detalle, 0, 250),
                $_SERVER['REMOTE_ADDR'] ?? null,
            ]
        );
    } catch (Throwable $error) {
        // La bitácora es secundaria: un fallo aquí no debe afectar al usuario.
    }
}

/**
 * Crea una notificación interna para un usuario, respetando su preferencia.
 * Si se indica $clave, no se repite la misma notificación (clave única).
 */
function notify(int $usuario_id, string $tipo, string $mensaje, ?string $enlace = null, ?string $clave = null): void
{
    $acepta_notificaciones = consultar_valor('SELECT pref_notif FROM usuarios WHERE id=?', [$usuario_id]);

    if ($acepta_notificaciones === null || (int) $acepta_notificaciones === 0) {
        return;
    }

    consultar(
        'INSERT IGNORE INTO notificaciones (usuario_id, tipo, mensaje, enlace, clave) VALUES (?,?,?,?,?)',
        [$usuario_id, $tipo, $mensaje, $enlace, $clave]
    );
}

/**
 * Cantidad de notificaciones sin leer de un usuario.
 */
function notif_count(int $usuario_id): int
{
    return (int) consultar_valor(
        'SELECT COUNT(*) FROM notificaciones WHERE usuario_id=? AND leida=0',
        [$usuario_id]
    );
}

// ---------------------------------------------------------------------------
// Utilidades varias
// ---------------------------------------------------------------------------

/**
 * Genera un código de referencia único, por ejemplo "PED-261110-AB12C".
 */
function referencia(string $prefijo): string
{
    return $prefijo . '-' . date('ymd') . '-' . strtoupper(substr(bin2hex(random_bytes(3)), 0, 5));
}

/**
 * Lee un parámetro general del sistema (tabla parametros).
 */
function param(string $clave, string $por_defecto = ''): string
{
    return (string) (consultar_valor('SELECT valor FROM parametros WHERE clave=?', [$clave]) ?? $por_defecto);
}

/**
 * Valida y guarda una imagen subida desde un formulario.
 *
 * Revisa el tipo real del archivo (no solo la extensión), el tamaño máximo,
 * y lo guarda con un nombre aleatorio. Si no se subió nada, devuelve
 * $archivo_anterior sin cambios; si se sube una nueva, borra la anterior.
 *
 * @param string      $campo            Nombre del input type="file".
 * @param string|null $archivo_anterior Archivo que se reemplaza.
 * @param string|null $indice           Posición, cuando el input admite varios archivos.
 * @return string|null Nombre del archivo guardado.
 * @throws RuntimeException Si el archivo no es válido.
 */
function upload_image(string $campo, ?string $archivo_anterior = null, ?string $indice = null): ?string
{
    $archivo = $_FILES[$campo] ?? null;

    if ($indice !== null) {
        $sin_archivo = !$archivo || ($archivo['error'][$indice] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE;
        if ($sin_archivo) {
            return $archivo_anterior;
        }
        $archivo = [
            'tmp_name' => $archivo['tmp_name'][$indice],
            'size' => $archivo['size'][$indice],
            'error' => $archivo['error'][$indice],
        ];
    }

    if (!$archivo || $archivo['error'] === UPLOAD_ERR_NO_FILE) {
        return $archivo_anterior;
    }
    if ($archivo['error'] !== UPLOAD_ERR_OK) {
        throw new RuntimeException('Error al subir el archivo.');
    }
    if ($archivo['size'] > UPLOAD_MAX_BYTES) {
        throw new RuntimeException('La imagen supera los 2 MB.');
    }

    $tipo_mime = (new finfo(FILEINFO_MIME_TYPE))->file($archivo['tmp_name']);
    $extensiones_validas = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp',
        'image/gif' => 'gif',
    ];
    $extension = $extensiones_validas[$tipo_mime] ?? null;

    if (!$extension || !@getimagesize($archivo['tmp_name'])) {
        throw new RuntimeException('Solo se permiten imágenes JPG, PNG, WEBP o GIF.');
    }

    $nombre_nuevo = bin2hex(random_bytes(12)) . '.' . $extension;

    if (!is_dir(UPLOAD_DIR)) {
        mkdir(UPLOAD_DIR, 0755, true);
    }
    if (!move_uploaded_file($archivo['tmp_name'], UPLOAD_DIR . $nombre_nuevo)) {
        throw new RuntimeException('No se pudo guardar la imagen.');
    }
    if ($archivo_anterior && is_file(UPLOAD_DIR . $archivo_anterior)) {
        @unlink(UPLOAD_DIR . $archivo_anterior);
    }

    return $nombre_nuevo;
}

/**
 * Etiqueta HTML de color (badge de Bootstrap) para un estado.
 */
function badge_estado(string $estado): string
{
    $colores = [
        'pendiente' => 'warning', 'confirmado' => 'info', 'en_preparacion' => 'primary',
        'listo' => 'primary', 'entregado' => 'success', 'rechazado' => 'danger',
        'cancelado' => 'secondary', 'aceptada' => 'success', 'rechazada' => 'danger',
        'cancelada' => 'secondary', 'finalizada' => 'success', 'pagado' => 'success',
        'parcial' => 'info', 'abierto' => 'warning', 'en_proceso' => 'info',
        'resuelto' => 'success', 'activo' => 'success', 'suspendido' => 'danger',
        'desactivado' => 'secondary',
    ];
    $color = $colores[$estado] ?? 'secondary';
    $texto = ucfirst(str_replace('_', ' ', $estado));

    return '<span class="badge text-bg-' . $color . '">' . e($texto) . '</span>';
}

// ---------------------------------------------------------------------------
// Exportación de documentos
// ---------------------------------------------------------------------------

/**
 * Entrega un documento PDF. Usa Dompdf si está instalado (composer install);
 * si no, muestra el HTML con el diálogo de impresión del navegador.
 */
function render_pdf(string $html, string $nombre_archivo): void
{
    $autoload = __DIR__ . '/../vendor/autoload.php';

    if (is_file($autoload)) {
        require_once $autoload;
        $pdf = new Dompdf\Dompdf(['isRemoteEnabled' => false]);
        $pdf->loadHtml($html, 'UTF-8');
        $pdf->setPaper('A4');
        $pdf->render();
        $pdf->stream($nombre_archivo, ['Attachment' => true]);
        exit;
    }

    echo $html . '<script>window.print()</script>';
    exit;
}

/**
 * Descarga datos como CSV (se abre directamente en Excel).
 *
 * @param string $nombre_archivo Nombre sin extensión.
 * @param array  $columnas       Encabezados.
 * @param array  $filas          Filas de datos.
 */
function export_csv(string $nombre_archivo, array $columnas, array $filas): never
{
    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="' . $nombre_archivo . '.csv"');

    $salida = fopen('php://output', 'w');
    fwrite($salida, "\xEF\xBB\xBF"); // marca BOM para que Excel lea las tildes
    fputcsv($salida, $columnas, ';');

    foreach ($filas as $fila) {
        fputcsv($salida, array_values($fila), ';');
    }
    exit;
}