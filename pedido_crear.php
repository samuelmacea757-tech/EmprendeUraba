<?php
/**
 * Creación de un pedido por parte de un comprador.
 *
 * Recibe por POST el formulario de emprendimiento.php. Reglas:
 *  - Los precios se toman SIEMPRE de la base de datos, nunca del formulario.
 *  - Se verifica que haya existencias suficientes.
 *  - El inventario NO se descuenta aquí: se descuenta cuando el emprendedor
 *    confirma el pedido (ver panel/pedido.php).
 *  - Todo se guarda en una transacción: o se crea completo o no se crea.
 */

require_once __DIR__ . '/includes/bootstrap.php';

$comprador = require_login(['comprador']);

if (!is_post()) {
    redirect('index.php');
}
csrf_verify();

$negocio_id = (int) post('negocio_id');
$negocio = consultar_uno(
    'SELECT * FROM emprendimientos WHERE id=? AND activo=1 AND suspendido=0',
    [$negocio_id]
);
if (!$negocio) {
    flash('danger', 'Emprendimiento no disponible.');
    redirect('index.php');
}

$cantidades = $_POST['qty'] ?? [];          // [producto_id => cantidad]
$observaciones_por_producto = $_POST['obs'] ?? [];
$nombre_comprador = post('nombre');
$telefono = post('telefono');

if ($nombre_comprador === '' || $telefono === '') {
    flash('danger', 'Completa tus datos de contacto.');
    redirect('emprendimiento.php?id=' . $negocio_id);
}

$fecha_entrega = post('fecha_entrega');
if ($fecha_entrega && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $fecha_entrega)) {
    $fecha_entrega = '';
}

try {
    db()->beginTransaction();

    $total = 0;
    $lineas = [];

    foreach ($cantidades as $producto_id => $cantidad) {
        $cantidad = (int) $cantidad;
        if ($cantidad < 1) {
            continue;
        }

        $producto = consultar_uno(
            "SELECT * FROM productos
             WHERE id=? AND negocio_id=? AND activo=1 AND publicado=1 AND disponible=1 AND tipo='producto'",
            [(int) $producto_id, $negocio_id]
        );
        if (!$producto) {
            continue;
        }
        if ($producto['stock'] < $cantidad) {
            throw new RuntimeException(
                'No hay suficientes existencias de "' . $producto['nombre'] . '" (quedan ' . $producto['stock'] . ').'
            );
        }

        $observacion = mb_substr(trim((string) ($observaciones_por_producto[$producto_id] ?? '')), 0, 200);
        $lineas[] = [$producto, $cantidad, $observacion];
        $total += $producto['precio'] * $cantidad;
    }

    if (!$lineas) {
        throw new RuntimeException('Selecciona al menos un producto.');
    }

    $referencia = referencia('PED');
    consultar(
        'INSERT INTO pedidos
            (negocio_id, comprador_id, referencia, nombre_comprador, telefono, email,
             direccion_entrega, observaciones, total, fecha_entrega)
         VALUES (?,?,?,?,?,?,?,?,?,?)',
        [
            $negocio_id, $comprador['id'], $referencia, $nombre_comprador, $telefono, $comprador['email'],
            post('direccion'), mb_substr(post('observaciones'), 0, 500), $total, $fecha_entrega ?: null,
        ]
    );
    $pedido_id = (int) db()->lastInsertId();

    foreach ($lineas as [$producto, $cantidad, $observacion]) {
        consultar(
            'INSERT INTO pedido_items (pedido_id, producto_id, nombre, precio, cantidad, observacion)
             VALUES (?,?,?,?,?,?)',
            [$pedido_id, $producto['id'], $producto['nombre'], $producto['precio'], $cantidad, $observacion]
        );
    }

    db()->commit();

    notify(
        (int) $negocio['usuario_id'],
        'pedido',
        "Nuevo pedido $referencia por " . money($total),
        'panel/pedido.php?id=' . $pedido_id
    );
    log_activity('pedido_nuevo', $referencia);

    flash('success', "Pedido $referencia enviado. El emprendedor lo revisará pronto.");
    redirect('comprador/pedido.php?id=' . $pedido_id);
} catch (RuntimeException $error) {
    if (db()->inTransaction()) {
        db()->rollBack();
    }
    flash('danger', $error->getMessage());
    redirect('emprendimiento.php?id=' . $negocio_id);
}