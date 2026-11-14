<?php
require_once __DIR__ . '/../includes/bootstrap.php';
require_login(['admin']);
if (is_post()) {
    csrf_verify();
    q('UPDATE emprendimientos SET suspendido=? WHERE id=?', [post('accion') === 'suspender' ? 1 : 0, (int)post('id')]);
    log_activity('admin_negocio_' . post('accion'), '#' . post('id')); flash('success', 'Emprendimiento actualizado.'); redirect('admin/emprendimientos.php');
}
$title = 'Emprendimientos'; $active = 'emprendimientos';
$lista = qa("SELECT e.*, c.nombre categoria, CONCAT(u.nombre,' ',u.apellido) dueno, (SELECT COUNT(*) FROM productos p WHERE p.negocio_id=e.id AND p.activo=1) prods, (SELECT COALESCE(SUM(total),0) FROM ventas v WHERE v.negocio_id=e.id) ventas
            FROM emprendimientos e LEFT JOIN categorias c ON c.id=e.categoria_id JOIN usuarios u ON u.id=e.usuario_id ORDER BY e.creado_en DESC");
include __DIR__ . '/../includes/panel_header.php';
?>
<h3>Emprendimientos registrados</h3>
<div class="table-responsive"><table class="table align-middle mb-0"><thead><tr><th>Negocio</th><th>Dueño</th><th>Categoría</th><th>Productos</th><th>Ventas</th><th>Estado</th><th></th></tr></thead><tbody>
<?php foreach ($lista as $e): ?><tr><td><a href="<?= url('emprendimiento.php?id=' . $e['id']) ?>" target="_blank"><?= e($e['nombre']) ?></a></td><td><?= e($e['dueno']) ?></td><td><?= e($e['categoria']) ?></td><td><?= $e['prods'] ?></td><td><?= money($e['ventas']) ?></td>
<td><?= $e['suspendido'] ? '<span class="badge text-bg-danger">Suspendido</span>' : ($e['activo'] ? '<span class="badge text-bg-success">Activo</span>' : '<span class="badge text-bg-secondary">Inactivo</span>') ?></td>
<td><form method="post"><?= csrf_field() ?><input type="hidden" name="id" value="<?= $e['id'] ?>"><?php if ($e['suspendido']): ?><button name="accion" value="activar" class="btn btn-sm btn-outline-success">Reactivar</button><?php else: ?><button name="accion" value="suspender" class="btn btn-sm btn-outline-danger" data-confirm="¿Suspender este emprendimiento?">Suspender</button><?php endif; ?></form></td></tr><?php endforeach; ?></tbody></table></div>
<?php include __DIR__ . '/../includes/panel_footer.php'; ?>
