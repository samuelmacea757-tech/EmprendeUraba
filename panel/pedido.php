<?php
require_once __DIR__ . '/../includes/bootstrap.php';
$n = require_negocio(); require_modulo($n, 'pedidos'); $nid = $n['id'];
$p = q1('SELECT * FROM pedidos WHERE id=? AND negocio_id=?', [(int)get('id'), $nid]);
if (!$p) { http_response_code(404); exit('Pedido no encontrado.'); }
$items = qa('SELECT * FROM pedido_items WHERE pedido_id=?', [$p['id']]);
if (is_post()) {
    csrf_verify(); $a = post('accion');
    try {
        db()->beginTransaction();
        $p = q1('SELECT * FROM pedidos WHERE id=? AND negocio_id=? FOR UPDATE', [$p['id'], $nid]);
        $notif = null;
        if ($a === 'confirmar' && $p['estado'] === 'pendiente') {
            // Cliente: se busca o se crea a partir del comprador
            $cid = $p['comprador_id'] ? qv('SELECT id FROM clientes WHERE negocio_id=? AND usuario_id=?', [$nid, $p['comprador_id']]) : null;
            if (!$cid) {
                $parts = explode(' ', $p['nombre_comprador'], 2);
                q('INSERT INTO clientes (negocio_id, usuario_id, nombres, apellidos, telefono, email, direccion) VALUES (?,?,?,?,?,?,?)', [$nid, $p['comprador_id'], $parts[0], $parts[1] ?? '', $p['telefono'], $p['email'], $p['direccion_entrega']]);
                $cid = (int)db()->lastInsertId();
            }
            $vid = crear_venta($n, (int)$cid, (int)$p['id'], $p['comprador_id'] ? (int)$p['comprador_id'] : null, array_map(fn($i) => [$i['producto_id'], $i['cantidad']], $items), 0, post('metodo') ?: 'Efectivo', 'Pedido ' . $p['referencia']);
            $fe = preg_match('/^\d{4}-\d{2}-\d{2}$/', post('fecha_entrega')) ? post('fecha_entrega') : $p['fecha_entrega'];
            q("UPDATE pedidos SET estado='confirmado', cliente_id=?, venta_id=?, fecha_entrega=? WHERE id=?", [$cid, $vid, $fe, $p['id']]);
            $notif = 'Tu pedido ' . $p['referencia'] . ' fue confirmado.'; flash('success', 'Pedido confirmado: se generó la venta y se actualizó el inventario.');
        } elseif ($a === 'rechazar' && $p['estado'] === 'pendiente') {
            q("UPDATE pedidos SET estado='rechazado', motivo=? WHERE id=?", [post('motivo') ?: 'Rechazado por el emprendedor', $p['id']]); $notif = 'Tu pedido ' . $p['referencia'] . ' fue rechazado.'; flash('success', 'Pedido rechazado.');
        } elseif ($a === 'cancelar' && in_array($p['estado'], ['confirmado', 'en_preparacion', 'listo'])) {
            foreach ($items as $i) if ($i['producto_id']) mover_stock($nid, (int)$i['producto_id'], 'entrada', (int)$i['cantidad'], 'Pedido cancelado ' . $p['referencia']);
            q("UPDATE pedidos SET estado='cancelado', motivo=? WHERE id=?", [post('motivo') ?: 'Cancelado por el emprendedor', $p['id']]);
            if ($p['venta_id']) { q('DELETE FROM movimientos_financieros WHERE venta_id=?', [$p['venta_id']]); q('DELETE FROM pagos WHERE venta_id=?', [$p['venta_id']]); q('DELETE FROM ventas WHERE id=?', [$p['venta_id']]); q('UPDATE pedidos SET venta_id=NULL WHERE id=?', [$p['id']]); }
            $notif = 'Tu pedido ' . $p['referencia'] . ' fue cancelado.'; flash('success', 'Pedido cancelado y existencias restituidas.');
        } elseif ($a === 'estado' && in_array(post('estado'), ['en_preparacion', 'listo', 'entregado']) && in_array($p['estado'], ['confirmado', 'en_preparacion', 'listo'])) {
            q('UPDATE pedidos SET estado=? WHERE id=?', [post('estado'), $p['id']]); $notif = 'Tu pedido ' . $p['referencia'] . ' ahora está: ' . str_replace('_', ' ', post('estado')); flash('success', 'Estado actualizado.');
        } elseif ($a === 'fecha') {
            if (preg_match('/^\d{4}-\d{2}-\d{2}$/', post('fecha_entrega'))) q('UPDATE pedidos SET fecha_entrega=? WHERE id=?', [post('fecha_entrega'), $p['id']]); flash('success', 'Fecha de entrega establecida.');
        }
        db()->commit();
        if ($notif && $p['comprador_id']) notify((int)$p['comprador_id'], 'pedido', $notif, 'comprador/pedido.php?id=' . $p['id']);
        log_activity('pedido_' . $a, $p['referencia']);
    } catch (RuntimeException $e) { if (db()->inTransaction()) db()->rollBack(); flash('danger', $e->getMessage()); }
    redirect('panel/pedido.php?id=' . $p['id']);
}
$title = 'Pedido ' . $p['referencia']; $active = 'pedidos';
include __DIR__ . '/../includes/panel_header.php';
?>
<a href="<?= url('panel/pedidos.php') ?>" class="small">&larr; Pedidos</a>
<h3 class="mt-2"><?= e($p['referencia']) ?> <?= badge_estado($p['estado']) ?></h3>
<div class="row g-3 mb-3">
  <div class="col-md-6"><div class="card h-100"><div class="card-body small"><h6>Comprador</h6><?= e($p['nombre_comprador']) ?><br><?= e($p['telefono']) ?> · <?= e($p['email']) ?><br><?= e($p['direccion_entrega']) ?>
    <?php if ($p['observaciones']): ?><hr class="my-2"><strong>Observaciones:</strong> <?= e($p['observaciones']) ?><?php endif; ?></div></div></div>
  <div class="col-md-6"><div class="card h-100"><div class="card-body small"><h6>Fechas</h6>Solicitud: <?= fdate($p['fecha_solicitud'], true) ?><br>Entrega: <?= fdate($p['fecha_entrega']) ?><?php if ($p['motivo']): ?><br>Motivo: <?= e($p['motivo']) ?><?php endif; ?>
    <?php if ($p['venta_id']): ?><br><a href="<?= url('comprobante.php?id=' . $p['venta_id']) ?>">Ver comprobante / venta</a><?php endif; ?></div></div></div>
</div>
<div class="table-responsive mb-3"><table class="table mb-0"><thead><tr><th>Producto</th><th>Cant.</th><th>Precio</th><th>Subtotal</th></tr></thead><tbody>
<?php foreach ($items as $i): ?><tr><td><?= e($i['nombre']) ?><?= $i['observacion'] ? '<div class="small text-muted">' . e($i['observacion']) . '</div>' : '' ?></td><td><?= $i['cantidad'] ?></td><td><?= money($i['precio']) ?></td><td><?= money($i['precio'] * $i['cantidad']) ?></td></tr><?php endforeach; ?>
<tr class="table-light"><td colspan="3" class="text-end fw-bold">Total</td><td class="fw-bold"><?= money($p['total']) ?></td></tr></tbody></table></div>

<?php $mp = array_map('trim', explode(',', $n['metodos_pago'])); ?>
<?php if ($p['estado'] === 'pendiente'): ?>
<div class="row g-3"><div class="col-md-6"><form method="post" class="card card-body"><?= csrf_field() ?><input type="hidden" name="accion" value="confirmar"><h6>Confirmar pedido</h6>
  <label class="form-label small">Fecha de entrega</label><input type="date" name="fecha_entrega" class="form-control mb-2" value="<?= e($p['fecha_entrega']) ?>" min="<?= date('Y-m-d') ?>">
  <label class="form-label small">Método de pago previsto</label><select name="metodo" class="form-select mb-2"><?php foreach ($mp as $m): ?><option><?= e($m) ?></option><?php endforeach; ?></select>
  <button class="btn btn-success">Confirmar (genera venta y descuenta inventario)</button></form></div>
  <div class="col-md-6"><form method="post" class="card card-body"><?= csrf_field() ?><input type="hidden" name="accion" value="rechazar"><h6>Rechazar pedido</h6><input class="form-control mb-2" name="motivo" placeholder="Motivo"><button class="btn btn-outline-danger" data-confirm="¿Rechazar el pedido?">Rechazar</button></form></div></div>
<?php elseif (in_array($p['estado'], ['confirmado', 'en_preparacion', 'listo'])): ?>
<div class="row g-3"><div class="col-md-4"><form method="post" class="card card-body"><?= csrf_field() ?><input type="hidden" name="accion" value="estado"><h6>Actualizar estado</h6><select name="estado" class="form-select mb-2"><option value="en_preparacion">En preparación</option><option value="listo">Listo para entrega</option><option value="entregado">Entregado</option></select><button class="btn btn-brand">Actualizar</button></form></div>
  <div class="col-md-4"><form method="post" class="card card-body"><?= csrf_field() ?><input type="hidden" name="accion" value="fecha"><h6>Fecha de entrega</h6><input type="date" name="fecha_entrega" class="form-control mb-2" value="<?= e($p['fecha_entrega']) ?>"><button class="btn btn-outline-primary">Guardar</button></form></div>
  <div class="col-md-4"><form method="post" class="card card-body"><?= csrf_field() ?><input type="hidden" name="accion" value="cancelar"><h6>Cancelar pedido</h6><input class="form-control mb-2" name="motivo" placeholder="Motivo"><button class="btn btn-outline-danger" data-confirm="Se eliminará la venta asociada y se devolverán las existencias. ¿Continuar?">Cancelar</button></form></div></div>
<?php endif; ?>
<?php include __DIR__ . '/../includes/panel_footer.php'; ?>
