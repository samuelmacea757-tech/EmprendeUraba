<?php
require_once __DIR__ . '/../includes/bootstrap.php';
$n = require_negocio(); require_modulo($n, 'clientes'); $id = $n['id'];
if (is_post()) {
    csrf_verify();
    if (post('nombres') === '') flash('danger', 'El nombre es obligatorio.');
    elseif (post('email') !== '' && !filter_var(post('email'), FILTER_VALIDATE_EMAIL)) flash('danger', 'Correo inválido.');
    else {
        $d = [post('nombres'), post('apellidos'), post('telefono'), post('email'), post('direccion'), post('observaciones')];
        if ((int)post('id')) { q('UPDATE clientes SET nombres=?, apellidos=?, telefono=?, email=?, direccion=?, observaciones=? WHERE id=? AND negocio_id=?', array_merge($d, [(int)post('id'), $id])); flash('success', 'Cliente actualizado.'); }
        else { q('INSERT INTO clientes (nombres, apellidos, telefono, email, direccion, observaciones, negocio_id) VALUES (?,?,?,?,?,?,?)', array_merge($d, [$id])); flash('success', 'Cliente registrado.'); }
    }
    redirect('panel/clientes.php');
}
$title = 'Clientes'; $active = 'clientes'; $qs = get('q');
$edit = get('edit') ? q1('SELECT * FROM clientes WHERE id=? AND negocio_id=?', [(int)get('edit'), $id]) : null;
$sql = 'SELECT c.*, (SELECT COUNT(*) FROM ventas v WHERE v.cliente_id=c.id) compras, (SELECT COALESCE(SUM(total),0) FROM ventas v WHERE v.cliente_id=c.id) total FROM clientes c WHERE c.negocio_id=?'; $par = [$id];
if ($qs !== '') { $sql .= ' AND (c.nombres LIKE ? OR c.apellidos LIKE ? OR c.telefono LIKE ? OR c.email LIKE ?)'; array_push($par, "%$qs%", "%$qs%", "%$qs%", "%$qs%"); }
$lista = qa($sql . ' ORDER BY c.nombres LIMIT 200', $par);
$ver = get('ver') ? q1('SELECT * FROM clientes WHERE id=? AND negocio_id=?', [(int)get('ver'), $id]) : null;
include __DIR__ . '/../includes/panel_header.php';
?>
<h3>Clientes</h3>
<?php if ($ver):
  $ventas = qa('SELECT * FROM ventas WHERE cliente_id=? AND negocio_id=? ORDER BY fecha DESC', [$ver['id'], $id]);
  $peds = qa('SELECT * FROM pedidos WHERE cliente_id=? AND negocio_id=? ORDER BY fecha_solicitud DESC', [$ver['id'], $id]); ?>
  <div class="card mb-4"><div class="card-body"><a href="<?= url('panel/clientes.php') ?>" class="small">&larr; Volver</a>
    <h5 class="mt-2"><?= e($ver['nombres'] . ' ' . $ver['apellidos']) ?></h5><div class="small text-muted"><?= e($ver['telefono']) ?> · <?= e($ver['email']) ?> · <?= e($ver['direccion']) ?></div>
    <?php if ($ver['observaciones']): ?><div class="alert alert-light border mt-2 mb-0 small"><?= nl2br(e($ver['observaciones'])) ?></div><?php endif; ?></div></div>
  <div class="row g-3"><div class="col-md-6"><h6>Historial de compras</h6><ul class="list-group"><?php foreach ($ventas as $v): ?><li class="list-group-item d-flex justify-content-between small"><a href="<?= url('comprobante.php?id=' . $v['id']) ?>"><?= e($v['referencia']) ?> · <?= fdate($v['fecha']) ?></a><span><?= money($v['total']) ?></span></li><?php endforeach; if (!$ventas) echo '<li class="list-group-item text-muted small">Sin compras.</li>'; ?></ul></div>
  <div class="col-md-6"><h6>Pedidos anteriores</h6><ul class="list-group"><?php foreach ($peds as $p): ?><li class="list-group-item d-flex justify-content-between small"><a href="<?= url('panel/pedido.php?id=' . $p['id']) ?>"><?= e($p['referencia']) ?></a><?= badge_estado($p['estado']) ?></li><?php endforeach; if (!$peds) echo '<li class="list-group-item text-muted small">Sin pedidos.</li>'; ?></ul></div></div>
<?php else: ?>
<div class="card card-body mb-3"><form method="post" class="row g-2"><?= csrf_field() ?><input type="hidden" name="id" value="<?= $edit['id'] ?? 0 ?>">
  <div class="col-md-3"><input class="form-control" name="nombres" placeholder="Nombres *" required value="<?= e($edit['nombres'] ?? '') ?>"></div><div class="col-md-3"><input class="form-control" name="apellidos" placeholder="Apellidos" value="<?= e($edit['apellidos'] ?? '') ?>"></div>
  <div class="col-md-3"><input class="form-control" name="telefono" placeholder="Teléfono" value="<?= e($edit['telefono'] ?? '') ?>"></div><div class="col-md-3"><input class="form-control" name="email" placeholder="Correo" value="<?= e($edit['email'] ?? '') ?>"></div>
  <div class="col-md-5"><input class="form-control" name="direccion" placeholder="Dirección" value="<?= e($edit['direccion'] ?? '') ?>"></div><div class="col-md-5"><input class="form-control" name="observaciones" placeholder="Observaciones" value="<?= e($edit['observaciones'] ?? '') ?>"></div>
  <div class="col-md-2"><button class="btn btn-brand w-100"><?= $edit ? 'Actualizar' : 'Registrar' ?></button></div></form></div>
<form class="row g-2 mb-3"><div class="col-8 col-md-4"><input class="form-control" name="q" value="<?= e($qs) ?>" placeholder="Buscar cliente…"></div><div class="col-4"><button class="btn btn-outline-secondary">Buscar</button></div></form>
<div class="table-responsive"><table class="table table-hover align-middle mb-0"><thead><tr><th>Nombre</th><th>Teléfono</th><th>Correo</th><th>Compras</th><th>Total</th><th></th></tr></thead><tbody>
<?php foreach ($lista as $c): ?><tr><td><?= e($c['nombres'] . ' ' . $c['apellidos']) ?></td><td><?= e($c['telefono']) ?></td><td><?= e($c['email']) ?></td><td><?= $c['compras'] ?></td><td><?= money($c['total']) ?></td>
<td><a class="btn btn-sm btn-outline-secondary" href="?ver=<?= $c['id'] ?>">Historial</a> <a class="btn btn-sm btn-outline-primary" href="?edit=<?= $c['id'] ?>"><i class="bi bi-pencil"></i></a></td></tr><?php endforeach; ?></tbody></table></div>
<?php endif; ?>
<?php include __DIR__ . '/../includes/panel_footer.php'; ?>
