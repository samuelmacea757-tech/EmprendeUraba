<?php
require_once __DIR__ . '/../includes/bootstrap.php';
$n = require_negocio(); require_modulo($n, 'inventario'); $id = $n['id'];
if (is_post()) {
    csrf_verify(); $a = post('accion');
    try {
        db()->beginTransaction();
        if ($a === 'mov') {
            $pid = (int)post('producto_id'); $c = (int)post('cantidad'); $t = post('tipo');
            if (!in_array($t, ['entrada', 'salida', 'ajuste']) || $c < 0 || ($t !== 'ajuste' && $c < 1) || post('motivo') === '') throw new RuntimeException('Completa tipo, cantidad y motivo.');
            mover_stock($id, $pid, $t, $c, post('motivo'));
            if ($t === 'ajuste') flash('success', 'Cantidad modificada.'); else flash('success', 'Movimiento registrado.');
        } elseif ($a === 'compra') {
            $costo = (float)post('costo_total'); $cant = max(1, (int)post('cantidad')); $pid = (int)post('producto_id') ?: null;
            if (post('descripcion') === '' || $costo <= 0) throw new RuntimeException('Descripción y costo son obligatorios.');
            if ($pid && !qv('SELECT id FROM productos WHERE id=? AND negocio_id=?', [$pid, $id])) $pid = null;
            q('INSERT INTO compras_insumos (negocio_id, producto_id, descripcion, proveedor, cantidad, costo_total, fecha) VALUES (?,?,?,?,?,?,CURDATE())', [$id, $pid, post('descripcion'), post('proveedor'), $cant, $costo]);
            q("INSERT INTO movimientos_financieros (negocio_id, tipo, categoria, concepto, monto, fecha) VALUES (?,?,?,?,?,CURDATE())", [$id, 'gasto', 'Compra de insumos', post('descripcion'), $costo]);
            if ($pid) mover_stock($id, $pid, 'entrada', $cant, 'Compra de insumos: ' . post('descripcion'));
            flash('success', 'Compra registrada (se generó el gasto' . ($pid ? ' y la entrada de inventario' : '') . ').');
        }
        db()->commit();
    } catch (RuntimeException $e) { if (db()->inTransaction()) db()->rollBack(); flash('danger', $e->getMessage()); }
    redirect('panel/inventario.php');
}
$title = 'Inventario'; $active = 'inventario';
$prods = qa("SELECT * FROM productos WHERE negocio_id=? AND tipo='producto' AND activo=1 ORDER BY nombre", [$id]);
$hist = qa('SELECT m.*, p.nombre FROM inventario_movimientos m JOIN productos p ON p.id=m.producto_id WHERE m.negocio_id=? ORDER BY m.creado_en DESC, m.id DESC LIMIT 50', [$id]);
include __DIR__ . '/../includes/panel_header.php';
?>
<h3>Inventario</h3>
<div class="row g-3 mb-4">
  <div class="col-lg-6"><div class="card"><div class="card-body"><h6>Registrar movimiento</h6>
  <form method="post" class="row g-2"><?= csrf_field() ?><input type="hidden" name="accion" value="mov">
    <div class="col-12"><select class="form-select" name="producto_id" required><option value="">Producto…</option><?php foreach ($prods as $p): ?><option value="<?= $p['id'] ?>"><?= e($p['nombre']) ?> (stock <?= $p['stock'] ?>)</option><?php endforeach; ?></select></div>
    <div class="col-5"><select class="form-select" name="tipo"><option value="entrada">Entrada</option><option value="salida">Salida</option><option value="ajuste">Fijar cantidad exacta</option></select></div>
    <div class="col-3"><input type="number" min="0" class="form-control" name="cantidad" placeholder="Cant." required></div>
    <div class="col-12"><input class="form-control" name="motivo" placeholder="Motivo (obligatorio)" required></div>
    <div class="col-12"><button class="btn btn-brand">Registrar</button></div></form></div></div></div>
  <div class="col-lg-6"><div class="card"><div class="card-body"><h6>Registrar compra de insumos</h6>
  <form method="post" class="row g-2"><?= csrf_field() ?><input type="hidden" name="accion" value="compra">
    <div class="col-8"><input class="form-control" name="descripcion" placeholder="Descripción de la compra" required></div><div class="col-4"><input class="form-control" name="proveedor" placeholder="Proveedor"></div>
    <div class="col-5"><select class="form-select" name="producto_id"><option value="">Sin producto asociado</option><?php foreach ($prods as $p): ?><option value="<?= $p['id'] ?>"><?= e($p['nombre']) ?></option><?php endforeach; ?></select></div>
    <div class="col-3"><input type="number" min="1" class="form-control" name="cantidad" value="1"></div><div class="col-4"><input type="number" min="0" step="0.01" class="form-control" name="costo_total" placeholder="Costo total" required></div>
    <div class="col-12"><button class="btn btn-outline-primary">Registrar compra</button></div></form></div></div></div>
</div>
<h5>Existencias</h5>
<div class="table-responsive mb-4"><table class="table table-sm align-middle mb-0"><thead><tr><th>Producto</th><th>Existencias</th><th>Mínimo</th><th>Estado</th></tr></thead><tbody>
<?php foreach ($prods as $p): ?><tr><td><?= e($p['nombre']) ?></td><td><?= $p['stock'] ?></td><td><?= $p['stock_minimo'] ?></td>
<td><?= $p['stock'] <= 0 ? '<span class="badge text-bg-danger">Agotado</span>' : ($p['stock'] <= $p['stock_minimo'] ? '<span class="badge text-bg-warning">Inventario bajo</span>' : '<span class="badge text-bg-success">Disponible</span>') ?></td></tr><?php endforeach; ?></tbody></table></div>
<h5>Historial de movimientos</h5>
<div class="table-responsive"><table class="table table-sm mb-0"><thead><tr><th>Fecha</th><th>Producto</th><th>Tipo</th><th>Cant.</th><th>Stock result.</th><th>Motivo</th></tr></thead><tbody>
<?php foreach ($hist as $h): ?><tr><td><?= fdate($h['creado_en'], true) ?></td><td><?= e($h['nombre']) ?></td><td><?= ucfirst($h['tipo']) ?></td><td><?= $h['cantidad'] ?></td><td><?= $h['stock_resultante'] ?></td><td><?= e($h['motivo']) ?></td></tr><?php endforeach; ?></tbody></table></div>
<?php include __DIR__ . '/../includes/panel_footer.php'; ?>
