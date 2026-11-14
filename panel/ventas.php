<?php
require_once __DIR__ . '/../includes/bootstrap.php';
$n = require_negocio(); require_modulo($n, 'ventas'); $id = $n['id'];
$mp = array_map('trim', explode(',', $n['metodos_pago']));
$prods = qa('SELECT id, nombre, precio, stock, tipo FROM productos WHERE negocio_id=? AND activo=1 ORDER BY nombre', [$id]);
$clientes = qa('SELECT id, nombres, apellidos FROM clientes WHERE negocio_id=? ORDER BY nombres', [$id]);
if (is_post()) {
    csrf_verify(); $a = post('accion');
    try {
        db()->beginTransaction();
        if ($a === 'nueva') {
            $items = [];
            foreach ($_POST['prod'] ?? [] as $k => $pid) if ((int)$pid && (int)($_POST['cant'][$k] ?? 0) > 0) $items[] = [(int)$pid, (int)$_POST['cant'][$k]];
            $cid = (int)post('cliente_id') ?: null; if ($cid && !qv('SELECT id FROM clientes WHERE id=? AND negocio_id=?', [$cid, $id])) $cid = null;
            $vid = crear_venta($n, $cid, null, null, $items, (float)post('descuento'), post('metodo'), post('notas'));
            if (isset($_POST['pagado'])) registrar_pago($n, $vid, (float)qv('SELECT total FROM ventas WHERE id=?', [$vid]), post('metodo'));
            flash('success', 'Venta registrada.');
        } elseif ($a === 'pago') {
            registrar_pago($n, (int)post('venta_id'), (float)post('monto'), post('metodo')); flash('success', 'Pago registrado.');
        }
        db()->commit(); log_activity('venta_' . $a);
    } catch (RuntimeException $e) { if (db()->inTransaction()) db()->rollBack(); flash('danger', $e->getMessage()); }
    redirect('panel/ventas.php');
}
$title = 'Ventas y pagos'; $active = 'ventas';
$est = get('estado');
$sql = 'SELECT v.*, c.nombres, c.apellidos FROM ventas v LEFT JOIN clientes c ON c.id=v.cliente_id WHERE v.negocio_id=?'; $par = [$id];
if (in_array($est, ['pendiente', 'parcial', 'pagado'])) { $sql .= ' AND v.estado_pago=?'; $par[] = $est; }
$lista = qa($sql . ' ORDER BY v.fecha DESC LIMIT 200', $par);
include __DIR__ . '/../includes/panel_header.php';
?>
<h3>Ventas y pagos</h3>
<div class="card mb-4"><div class="card-body"><h6>Registrar venta</h6>
<form method="post"><?= csrf_field() ?><input type="hidden" name="accion" value="nueva">
  <div id="rows"><div class="row g-2 mb-2 row-item"><div class="col-7"><select name="prod[]" class="form-select"><option value="">Producto o servicio…</option><?php foreach ($prods as $p): ?><option value="<?= $p['id'] ?>"><?= e($p['nombre']) ?> — <?= money($p['precio']) ?><?= $p['tipo'] === 'producto' ? ' (stock ' . $p['stock'] . ')' : '' ?></option><?php endforeach; ?></select></div>
    <div class="col-3"><input type="number" min="1" value="1" name="cant[]" class="form-control"></div><div class="col-2"><button type="button" class="btn btn-outline-danger rm-row w-100"><i class="bi bi-x"></i></button></div></div></div>
  <button type="button" id="addRow" class="btn btn-sm btn-outline-secondary mb-3"><i class="bi bi-plus"></i> Agregar línea</button>
  <div class="row g-2"><div class="col-md-3"><select name="cliente_id" class="form-select"><option value="">Cliente general</option><?php foreach ($clientes as $c): ?><option value="<?= $c['id'] ?>"><?= e($c['nombres'] . ' ' . $c['apellidos']) ?></option><?php endforeach; ?></select></div>
    <div class="col-md-2"><input type="number" min="0" step="0.01" name="descuento" class="form-control" placeholder="Descuento $" value="0"></div>
    <div class="col-md-2"><select name="metodo" class="form-select"><?php foreach ($mp as $m): ?><option><?= e($m) ?></option><?php endforeach; ?></select></div>
    <div class="col-md-3"><input name="notas" class="form-control" placeholder="Notas"></div>
    <div class="col-md-2 d-flex align-items-center"><div class="form-check"><input class="form-check-input" type="checkbox" name="pagado" id="pg" checked><label for="pg" class="form-check-label">Pagada</label></div></div></div>
  <button class="btn btn-brand mt-3">Registrar venta</button></form></div></div>

<ul class="nav nav-pills mb-3"><?php foreach (['' => 'Todas', 'pendiente' => 'Pago pendiente', 'parcial' => 'Parcial', 'pagado' => 'Pagadas'] as $k => $l): ?><li class="nav-item"><a class="nav-link <?= $est === $k ? 'active' : '' ?>" href="?estado=<?= $k ?>"><?= $l ?></a></li><?php endforeach; ?></ul>
<div class="table-responsive"><table class="table align-middle mb-0"><thead><tr><th>Ref.</th><th>Fecha</th><th>Cliente</th><th>Total</th><th>Pagado</th><th>Método</th><th>Estado</th><th></th></tr></thead><tbody>
<?php foreach ($lista as $v): ?><tr><td><?= e($v['referencia']) ?></td><td><?= fdate($v['fecha'], true) ?></td><td><?= e(trim($v['nombres'] . ' ' . $v['apellidos']) ?: 'General') ?></td><td><?= money($v['total']) ?><?= $v['descuento'] > 0 ? '<div class="small text-muted">desc. ' . money($v['descuento']) . '</div>' : '' ?></td><td><?= money($v['monto_pagado']) ?></td><td><?= e($v['metodo_pago']) ?></td><td><?= badge_estado($v['estado_pago']) ?></td>
<td class="text-nowrap"><a class="btn btn-sm btn-outline-secondary" href="<?= url('comprobante.php?id=' . $v['id']) ?>" title="Comprobante"><i class="bi bi-receipt"></i></a>
<?php if ($v['estado_pago'] !== 'pagado'): ?><button class="btn btn-sm btn-outline-success" data-bs-toggle="modal" data-bs-target="#pagoM" data-id="<?= $v['id'] ?>" data-saldo="<?= $v['total'] - $v['monto_pagado'] ?>">Registrar pago</button><?php endif; ?></td></tr><?php endforeach; ?></tbody></table></div>
<div class="modal fade" id="pagoM"><div class="modal-dialog"><form method="post" class="modal-content"><?= csrf_field() ?><input type="hidden" name="accion" value="pago"><input type="hidden" name="venta_id" id="pv">
  <div class="modal-header"><h6>Registrar pago</h6><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
  <div class="modal-body"><input type="number" step="0.01" min="1" class="form-control mb-2" name="monto" id="pm" required><select name="metodo" class="form-select"><?php foreach ($mp as $m): ?><option><?= e($m) ?></option><?php endforeach; ?></select></div>
  <div class="modal-footer"><button class="btn btn-brand">Guardar pago</button></div></form></div></div>
<?php $page_js = '<script>document.getElementById("pagoM").addEventListener("show.bs.modal",e=>{pv.value=e.relatedTarget.dataset.id;pm.value=e.relatedTarget.dataset.saldo;});</script>'; include __DIR__ . '/../includes/panel_footer.php'; ?>
