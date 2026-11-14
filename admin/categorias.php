<?php
require_once __DIR__ . '/../includes/bootstrap.php';
require_login(['admin']);
if (is_post()) {
    csrf_verify(); $a = post('accion');
    if ($a === 'add' && post('nombre') !== '') q('INSERT INTO categorias (nombre, icono) VALUES (?,?)', [post('nombre'), preg_match('/^bi-[a-z0-9-]+$/', post('icono')) ? post('icono') : 'bi-shop']);
    elseif ($a === 'toggle') q('UPDATE categorias SET activo=1-activo WHERE id=?', [(int)post('id')]);
    elseif ($a === 'edit' && post('nombre') !== '') q('UPDATE categorias SET nombre=?, icono=? WHERE id=?', [post('nombre'), preg_match('/^bi-[a-z0-9-]+$/', post('icono')) ? post('icono') : 'bi-shop', (int)post('id')]);
    flash('success', 'Categorías actualizadas.'); redirect('admin/categorias.php');
}
$title = 'Categorías'; $active = 'categorias';
$lista = qa('SELECT c.*, (SELECT COUNT(*) FROM emprendimientos e WHERE e.categoria_id=c.id) n FROM categorias c ORDER BY nombre');
include __DIR__ . '/../includes/panel_header.php';
?>
<h3>Categorías generales de negocios</h3>
<form method="post" class="row g-2 mb-3"><?= csrf_field() ?><input type="hidden" name="accion" value="add"><div class="col-md-4"><input class="form-control" name="nombre" placeholder="Nombre" required></div><div class="col-md-3"><input class="form-control" name="icono" placeholder="Icono (bi-shop)" value="bi-shop"></div><div class="col-auto"><button class="btn btn-brand">Agregar</button></div></form>
<div class="table-responsive"><table class="table align-middle mb-0"><thead><tr><th>Nombre / icono</th><th>Negocios</th><th>Estado</th><th></th></tr></thead><tbody>
<?php foreach ($lista as $c): ?><tr><td><form method="post" class="d-flex gap-2"><?= csrf_field() ?><input type="hidden" name="accion" value="edit"><input type="hidden" name="id" value="<?= $c['id'] ?>"><input class="form-control form-control-sm" name="nombre" value="<?= e($c['nombre']) ?>"><input class="form-control form-control-sm" name="icono" value="<?= e($c['icono']) ?>"><button class="btn btn-sm btn-outline-primary">Guardar</button></form></td><td><?= $c['n'] ?></td><td><?= $c['activo'] ? 'Activa' : 'Oculta' ?></td>
<td><form method="post"><?= csrf_field() ?><input type="hidden" name="accion" value="toggle"><input type="hidden" name="id" value="<?= $c['id'] ?>"><button class="btn btn-sm btn-outline-secondary"><?= $c['activo'] ? 'Ocultar' : 'Mostrar' ?></button></form></td></tr><?php endforeach; ?></tbody></table></div>
<?php include __DIR__ . '/../includes/panel_footer.php'; ?>
