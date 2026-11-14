<?php
/**
 * Autenticación, control de acceso por rol y lógica de negocio compartida.
 *
 * Secciones:
 *  1. Sesión y usuario actual
 *  2. Control de acceso (roles) y negocio del emprendedor
 *  3. Módulos activables por negocio
 *  4. Inventario, ventas y pagos
 *  5. Alertas automáticas
 */

// ---------------------------------------------------------------------------
// 1. Sesión y usuario actual
// ---------------------------------------------------------------------------

/**
 * Devuelve el usuario con sesión iniciada, o null.
 *
 * Verifica que la cuenta siga activa y que el token de sesión coincida con el
 * de la base de datos (así se pueden cerrar sesiones remotamente).
 */
function current_user(): ?array
{
    static $usuario = false; // false = aún no consultado

    if ($usuario !== false) {
        return $usuario;
    }
    if (empty($_SESSION['uid'])) {
        return $usuario = null;
    }

    $fila = consultar_uno("SELECT * FROM usuarios WHERE id=? AND estado='activo'", [$_SESSION['uid']]);
    $token_valido = $fila && hash_equals((string) $fila['session_token'], (string) ($_SESSION['stoken'] ?? ''));

    if (!$token_valido) {
        unset($_SESSION['uid'], $_SESSION['stoken']);

        return $usuario = null;
    }

    return $usuario = $fila;
}

/**
 * Inicia la sesión de un usuario ya verificado.
 * Regenera el ID de sesión para evitar la fijación de sesión.
 */
function login_user(array $usuario): void
{
    session_regenerate_id(true);

    $token = bin2hex(random_bytes(16));
    consultar('UPDATE usuarios SET session_token=? WHERE id=?', [$token, $usuario['id']]);

    $_SESSION['uid'] = (int) $usuario['id'];
    $_SESSION['stoken'] = $token;

    log_activity('login', $usuario['email']);
}

/**
 * Ruta de la página principal de cada rol.
 */
function home_for(array $usuario): string
{
    $paginas_inicio = [
        'admin' => 'admin/index.php',
        'emprendedor' => 'panel/index.php',
        'comprador' => 'comprador/index.php',
    ];

    return $paginas_inicio[$usuario['rol']];
}

// ---------------------------------------------------------------------------
// 2. Control de acceso (roles) y negocio del emprendedor
// ---------------------------------------------------------------------------

/**
 * Exige sesión iniciada y, opcionalmente, un rol permitido.
 *
 * @param string[] $roles_permitidos Vacío = cualquier usuario autenticado.
 * @return array El usuario autenticado.
 */
function require_login(array $roles_permitidos = []): array
{
    $usuario = current_user();

    if (!$usuario) {
        flash('warning', 'Inicia sesión para continuar.');
        redirect('auth/login.php?next=' . urlencode($_SERVER['REQUEST_URI']));
    }
    if ($roles_permitidos && !in_array($usuario['rol'], $roles_permitidos, true)) {
        http_response_code(403);
        exit('Acceso denegado.');
    }

    return $usuario;
}

/**
 * Negocio del emprendedor autenticado.
 *
 * IMPORTANTE: todas las consultas del panel se filtran con el id de este
 * negocio; así cada emprendedor solo ve y modifica sus propios datos.
 */
function negocio_actual(): ?array
{
    static $negocio = false;

    if ($negocio !== false) {
        return $negocio;
    }

    $usuario = current_user();
    if (!$usuario || $usuario['rol'] !== 'emprendedor') {
        return $negocio = null;
    }

    return $negocio = consultar_uno('SELECT * FROM emprendimientos WHERE usuario_id=?', [$usuario['id']]);
}

/**
 * Exige un emprendedor con negocio creado; si no lo tiene, lo envía a crearlo.
 */
function require_negocio(): array
{
    require_login(['emprendedor']);
    $negocio = negocio_actual();

    if (!$negocio) {
        flash('info', 'Primero crea tu emprendimiento.');
        redirect('panel/negocio.php');
    }

    return $negocio;
}

// ---------------------------------------------------------------------------
// 3. Módulos activables por negocio
// ---------------------------------------------------------------------------

/** Módulos que el emprendedor puede activar o desactivar (clave => nombre). */
const MODULOS = [
    'inventario' => 'Inventario',
    'clientes' => 'Clientes',
    'pedidos' => 'Pedidos',
    'ventas' => 'Ventas y pagos',
    'finanzas' => 'Finanzas',
    'reservas' => 'Reservas',
    'reportes' => 'Reportes',
];

/**
 * Indica si un módulo está activo para el negocio.
 * Las reservas no existen en negocios que solo venden productos.
 */
function modulo_activo(array $negocio, string $modulo): bool
{
    $sin_configurar = $negocio['modulos'] === null || $negocio['modulos'] === '';
    $activos = $sin_configurar ? array_keys(MODULOS) : explode(',', $negocio['modulos']);

    if ($modulo === 'reservas' && $negocio['tipo_oferta'] === 'productos') {
        return false;
    }

    return in_array($modulo, $activos, true);
}

/**
 * Exige que un módulo esté activo; si no, redirige a Personalizar.
 */
function require_modulo(array $negocio, string $modulo): void
{
    if (!modulo_activo($negocio, $modulo)) {
        flash('warning', 'Este módulo está desactivado para tu negocio.');
        redirect('panel/personalizar.php');
    }
}

// ---------------------------------------------------------------------------
// 4. Inventario, ventas y pagos
// ---------------------------------------------------------------------------

/**
 * Cambia las existencias de un producto y registra el movimiento.
 * Debe llamarse dentro de una transacción.
 *
 * @param string $tipo entrada (suma) | salida (resta) | ajuste (fija la cantidad exacta)
 * @throws RuntimeException Si una salida dejaría el stock negativo.
 */
function mover_stock(int $negocio_id, int $producto_id, string $tipo, int $cantidad, string $motivo, ?string $referencia = null): void
{
    $producto = consultar_uno(
        'SELECT stock, tipo FROM productos WHERE id=? AND negocio_id=? FOR UPDATE',
        [$producto_id, $negocio_id]
    );

    // Los servicios no manejan existencias.
    if (!$producto || $producto['tipo'] === 'servicio') {
        return;
    }

    $stock_nuevo = match ($tipo) {
        'entrada' => $producto['stock'] + $cantidad,
        'salida' => $producto['stock'] - $cantidad,
        default => $cantidad,
    };

    if ($stock_nuevo < 0) {
        throw new RuntimeException('Stock insuficiente para realizar la salida.');
    }

    consultar('UPDATE productos SET stock=? WHERE id=?', [$stock_nuevo, $producto_id]);
    consultar(
        'INSERT INTO inventario_movimientos
            (negocio_id, producto_id, tipo, cantidad, stock_resultante, motivo, referencia, usuario_id)
         VALUES (?,?,?,?,?,?,?,?)',
        [$negocio_id, $producto_id, $tipo, $cantidad, $stock_nuevo, $motivo, $referencia, $_SESSION['uid'] ?? null]
    );
}

/**
 * Crea una venta con sus ítems y descuenta el inventario.
 * Debe llamarse dentro de una transacción. Los precios se toman siempre de
 * la base de datos, nunca del formulario.
 *
 * @param array $items Lista de [producto_id, cantidad].
 * @return int Id de la venta creada.
 * @throws RuntimeException Si no hay ítems válidos o falta stock.
 */
function crear_venta(array $negocio, ?int $cliente_id, ?int $pedido_id, ?int $comprador_id, array $items, float $descuento, string $metodo_pago, ?string $notas = null): int
{
    $subtotal = 0;
    $lineas = [];

    foreach ($items as [$producto_id, $cantidad]) {
        $producto = consultar_uno(
            'SELECT * FROM productos WHERE id=? AND negocio_id=? AND activo=1',
            [$producto_id, $negocio['id']]
        );
        if (!$producto || $cantidad < 1) {
            continue;
        }
        $lineas[] = [$producto, $cantidad];
        $subtotal += $producto['precio'] * $cantidad;
    }

    if (!$lineas) {
        throw new RuntimeException('La venta no tiene productos válidos.');
    }

    $descuento = max(0, min($descuento, $subtotal));
    $total = $subtotal - $descuento;
    $referencia = referencia('VTA');

    consultar(
        'INSERT INTO ventas
            (negocio_id, cliente_id, pedido_id, comprador_id, referencia, subtotal, descuento, total, metodo_pago, notas)
         VALUES (?,?,?,?,?,?,?,?,?,?)',
        [$negocio['id'], $cliente_id, $pedido_id, $comprador_id, $referencia, $subtotal, $descuento, $total, $metodo_pago, $notas]
    );
    $venta_id = (int) db()->lastInsertId();

    foreach ($lineas as [$producto, $cantidad]) {
        consultar(
            'INSERT INTO venta_items (venta_id, producto_id, nombre, precio, costo, cantidad, subtotal)
             VALUES (?,?,?,?,?,?,?)',
            [$venta_id, $producto['id'], $producto['nombre'], $producto['precio'], $producto['costo'], $cantidad, $producto['precio'] * $cantidad]
        );
        mover_stock((int) $negocio['id'], (int) $producto['id'], 'salida', $cantidad, 'Venta confirmada', $referencia);
    }

    return $venta_id;
}

/**
 * Registra un pago sobre una venta: actualiza el estado del pago y genera el
 * ingreso en finanzas. Debe llamarse dentro de una transacción.
 *
 * @throws RuntimeException Si la venta no existe o ya está pagada.
 */
function registrar_pago(array $negocio, int $venta_id, float $monto, string $metodo_pago): void
{
    $venta = consultar_uno('SELECT * FROM ventas WHERE id=? AND negocio_id=?', [$venta_id, $negocio['id']]);
    if (!$venta) {
        throw new RuntimeException('Venta no encontrada.');
    }

    // Nunca se acepta más de lo que falta por pagar.
    $saldo_pendiente = $venta['total'] - $venta['monto_pagado'];
    $monto = min($monto, $saldo_pendiente);
    if ($monto <= 0) {
        throw new RuntimeException('No hay saldo pendiente.');
    }

    consultar(
        'INSERT INTO pagos (venta_id, negocio_id, monto, metodo) VALUES (?,?,?,?)',
        [$venta_id, $negocio['id'], $monto, $metodo_pago]
    );

    $total_pagado = $venta['monto_pagado'] + $monto;
    $estado_pago = $total_pagado >= $venta['total'] ? 'pagado' : 'parcial';
    consultar('UPDATE ventas SET monto_pagado=?, estado_pago=? WHERE id=?', [$total_pagado, $estado_pago, $venta_id]);

    consultar(
        'INSERT INTO movimientos_financieros (negocio_id, tipo, categoria, concepto, monto, fecha, venta_id)
         VALUES (?,?,?,?,?,CURDATE(),?)',
        [$negocio['id'], 'ingreso', 'Ventas', 'Pago venta ' . $venta['referencia'], $monto, $venta_id]
    );

    notify(
        (int) $negocio['usuario_id'],
        'pago',
        'Pago registrado de ' . money($monto) . ' (' . $venta['referencia'] . ')',
        'panel/ventas.php'
    );
}

// ---------------------------------------------------------------------------
// 5. Alertas automáticas
// ---------------------------------------------------------------------------

/**
 * Genera las notificaciones del emprendedor: stock bajo o agotado, pedidos
 * pendientes, entregas y citas próximas, y pagos atrasados.
 * Cada alerta lleva una clave para no repetirse el mismo día.
 */
function generar_alertas(array $negocio): void
{
    $usuario_id = (int) $negocio['usuario_id'];
    $negocio_id = $negocio['id'];

    // Productos agotados o con pocas existencias.
    $productos_bajos = consultar_todos(
        "SELECT id, nombre, stock, stock_minimo FROM productos
         WHERE negocio_id=? AND tipo='producto' AND activo=1 AND stock <= stock_minimo",
        [$negocio_id]
    );
    foreach ($productos_bajos as $producto) {
        if ($producto['stock'] <= 0) {
            notify($usuario_id, 'agotado', 'Producto agotado: ' . $producto['nombre'], 'panel/inventario.php', 'agot-' . $producto['id'] . '-' . date('Ymd'));
        } else {
            notify($usuario_id, 'stock', 'Pocas existencias de ' . $producto['nombre'] . ' (' . $producto['stock'] . ')', 'panel/inventario.php', 'bajo-' . $producto['id'] . '-' . date('Ymd'));
        }
    }

    // Pedidos pendientes por revisar.
    $pedidos_pendientes = (int) consultar_valor(
        "SELECT COUNT(*) FROM pedidos WHERE negocio_id=? AND estado='pendiente'",
        [$negocio_id]
    );
    if ($pedidos_pendientes > 0) {
        notify(
            $usuario_id,
            'pedido_pend',
            "Tienes $pedidos_pendientes pedido(s) pendiente(s) por revisar",
            'panel/pedidos.php?v=pendientes',
            'pedpend-' . date('Ymd') . '-' . $pedidos_pendientes
        );
    }

    // Entregas en los próximos 2 días.
    $entregas = consultar_todos(
        "SELECT id, referencia, fecha_entrega FROM pedidos
         WHERE negocio_id=? AND estado IN ('confirmado','en_preparacion','listo')
           AND fecha_entrega BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 2 DAY)",
        [$negocio_id]
    );
    foreach ($entregas as $pedido) {
        notify($usuario_id, 'entrega', 'Próxima entrega ' . $pedido['referencia'] . ' el ' . fdate($pedido['fecha_entrega']), 'panel/pedido.php?id=' . $pedido['id'], 'ent-' . $pedido['id']);
    }

    // Citas aceptadas para hoy o mañana.
    $citas = consultar_todos(
        "SELECT r.id, r.fecha, r.hora, p.nombre
         FROM reservas r JOIN productos p ON p.id=r.servicio_id
         WHERE r.negocio_id=? AND r.estado='aceptada'
           AND r.fecha BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 1 DAY)",
        [$negocio_id]
    );
    foreach ($citas as $cita) {
        notify($usuario_id, 'cita', 'Próxima cita: ' . $cita['nombre'] . ' ' . fdate($cita['fecha']) . ' ' . substr($cita['hora'], 0, 5), 'panel/reservas.php', 'cita-' . $cita['id']);
    }

    // Ventas sin pagar completamente después de 3 días.
    $ventas_atrasadas = consultar_todos(
        "SELECT id, referencia FROM ventas
         WHERE negocio_id=? AND estado_pago<>'pagado' AND fecha < DATE_SUB(NOW(), INTERVAL 3 DAY)",
        [$negocio_id]
    );
    foreach ($ventas_atrasadas as $venta) {
        notify($usuario_id, 'pago_pend', 'Pago pendiente de la venta ' . $venta['referencia'], 'panel/ventas.php', 'pp-' . $venta['id']);
    }
}