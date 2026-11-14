<?php
require_once __DIR__ . '/../includes/bootstrap.php';
$n = require_negocio(); $id = $n['id'];
if (is_post() || get('accion')) {
    csrf_verify();
    $pid = (int)(post('id') ?: get('id'));
    $p = q1('SELECT * FROM productos WHERE id=? AND negocio_id=?', [$pid, $id]);
    if ($p) {
        $a = post('accion') ?: get('accion');
        if ($a === 'toggle') q('UPDATE productos SET activo=1-activo WHERE id=?', [$pid]);
        if ($a === 'publicar') q('UPDATE productos SET publicado=1-publicado WHERE id=?', [$pid]);
        if ($a === 'disp') q('UPDATE productos SET disponible=1-disponible WHERE id=?', [$pid]);
        flash('success', 'Producto actualizado.');
    }
    redirect('panel/productos.php');
}
$title = 'Productos y servicios'; $active = 'productos';
$qs = get('q'); $tipo = get('tipo');
$sql = 'SELECT p.*, cp.nombre cat FROM productos p LEFT JOIN categorias_propias cp ON cp.id=p.categoria_id WHERE p.negocio_id=?'; $par = [$id];
if ($qs !== '') { $sql .= ' AND p.nombre LIKE ?'; $par[] = "%$qs%"; }
if (in_array($tipo, ['producto', 'servicio'])) { $sql .= ' AND p.tipo=?'; $par[] = $tipo; }
$lista = qa($sql . ' ORDER BY p.activo DESC, p.nombre', $par);
include __DIR__ . '/../includes/panel_header.php';
?>
<div class="d-flex flex-wrap justify-content-between gap-2 mb-3"><h3 class="mb-0">Productos y servicios</h3><a href="<?= url('panel/producto_form.php') ?>" class="btn btn-brand"><i class="bi bi-plus-lg"></i> Nuevo</a></div>
<form class="row g-2 mb-3"><div class="col-8 col-md-4"><input class="form-control" name="q" value="<?= e($qs) ?>" placeholder="Buscar…"></div>
  <div class="col-4 col-md-2"><select class="form-select" name="tipo" onchange="this.form.submit()"><option value="">Todos</option><option value="producto" <?= $tipo === 'producto' ? 'selected' : '' ?>>Productos</option><option value="servicio" <?= $tipo === 'servicio' ? 'selected' : '' ?>>Servicios</option></select></div></form>
<div class="table-responsive"><table class="table align-middle table-hover mb-0"><thead><tr><th></th><th>Nombre</th><th>Tipo</th><th>Categoría</th><th>Precio</th><th>Costo</th><th>Stock</th><th>Estado</th><th></th></tr></thead><tbody>
<?php foreach ($lista as $p): ?><tr class="<?= $p['activo'] ? '' : 'table-secondary' ?>">
  <td><img src="<?= img($p['foto']) ?>" width="48" height="48" style="object-fit:cover" class="rounded"></td>
  <td><?= e($p['nombre']) ?> <?= $p['destacado'] ? '<i class="bi bi-star-fill text-warning"></i>' : '' ?></td><td><?= ucfirst($p['tipo']) ?></td><td><?= e($p['cat'] ?? '—') ?></td><td><?= money($p['precio']) ?></td><td><?= money($p['costo']) ?></td>
  <td><?= $p['tipo'] === 'servicio' ? '—' : ($p['stock'] <= 0 ? '<span class="badge text-bg-danger">Agotado</span>' : $p['stock'] . ($p['stock'] <= $p['stock_minimo'] ? ' <span class="badge text-bg-warning">bajo</span>' : '')) ?></td>
  <td><?= !$p['activo'] ? '<span class="badge text-bg-secondary">Desactivado</span>' : ($p['publicado'] ? '<span class="badge text-bg-success">Publicado</span>' : '<span class="badge text-bg-warning">Oculto</span>') ?><?= $p['disponible'] ? '' : ' <span class="badge text-bg-dark">No disp.</span>' ?></td>
  <td class="text-nowrap"><a class="btn btn-sm btn-outline-primary" href="<?= url('panel/producto_form.php?id=' . $p['id']) ?>"><i class="bi bi-pencil"></i></a>
    <a class="btn btn-sm btn-outline-secondary" title="Publicar/ocultar en catálogo" href="<?= csrf_url('?accion=publicar&id=' . $p['id']) ?>"><i class="bi bi-eye"></i></a>
    <a class="btn btn-sm btn-outline-secondary" title="Disponible/no disponible" href="<?= csrf_url('?accion=disp&id=' . $p['id']) ?>"><i class="bi bi-toggle-on"></i></a>
    <a class="btn btn-sm btn-outline-danger" title="Desactivar/activar" data-confirm="¿Cambiar el estado activo de este ítem?" href="<?= csrf_url('?accion=toggle&id=' . $p['id']) ?>"><i class="bi bi-trash"></i></a></td></tr><?php endforeach; ?>
<?php if (!$lista): ?><tr><td colspan="9" class="text-center text-muted py-4">No hay registros.</td></tr><?php endif; ?></tbody></table></div>
<?php include __DIR__ . '/../includes/panel_footer.php'; ?>
