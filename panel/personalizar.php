<?php
require_once __DIR__ . '/../includes/bootstrap.php';
$n = require_negocio(); $id = $n['id'];
if (is_post()) {
    csrf_verify(); $a = post('accion');
    if ($a === 'config') {
        $color = preg_match('/^#[0-9a-fA-F]{6}$/', post('color')) ? post('color') : '#0f766e';
        $tipo = in_array(post('tipo_oferta'), ['productos', 'servicios', 'ambos']) ? post('tipo_oferta') : 'ambos';
        $mods = implode(',', array_intersect($_POST['modulos'] ?? [], array_keys(MODULOS)));
        q('UPDATE emprendimientos SET color_primario=?, tipo_oferta=?, modulos=? WHERE id=?', [$color, $tipo, $mods, $id]); flash('success', 'Personalización guardada.');
    } elseif ($a === 'cat_add' && post('nombre') !== '') { q('INSERT INTO categorias_propias (negocio_id, nombre) VALUES (?,?)', [$id, mb_substr(post('nombre'), 0, 80)]); flash('success', 'Categoría creada.'); }
    elseif ($a === 'cat_del') { q('DELETE FROM categorias_propias WHERE id=? AND negocio_id=?', [(int)post('cid'), $id]); flash('success', 'Categoría eliminada.'); }
    redirect('panel/personalizar.php');
}
$title = 'Personalizar'; $active = 'personalizar';
$cats = qa('SELECT * FROM categorias_propias WHERE negocio_id=? ORDER BY nombre', [$id]);
$act = ($n['modulos'] === null || $n['modulos'] === '') ? array_keys(MODULOS) : explode(',', $n['modulos']);
include __DIR__ . '/../includes/panel_header.php';
?>
<h3>Personalizar mi emprendimiento</h3>
<div class="row g-3">
<div class="col-lg-7"><form method="post" class="card card-body"><?= csrf_field() ?><input type="hidden" name="accion" value="config">
  <div class="mb-3"><label class="form-label">¿Qué ofrece tu negocio?</label><select class="form-select" name="tipo_oferta"><?php foreach (['productos' => 'Solo productos', 'servicios' => 'Solo servicios', 'ambos' => 'Productos y servicios'] as $k => $l): ?><option value="<?= $k ?>" <?= $n['tipo_oferta'] === $k ? 'selected' : '' ?>><?= $l ?></option><?php endforeach; ?></select></div>
  <div class="mb-3"><label class="form-label">Color del negocio</label><input type="color" class="form-control form-control-color" name="color" value="<?= e($n['color_primario']) ?>"></div>
  <div class="mb-3"><label class="form-label d-block">Módulos activos del panel</label>
    <?php foreach (MODULOS as $k => $l): ?><div class="form-check form-check-inline"><input class="form-check-input" type="checkbox" name="modulos[]" id="mod<?= $k ?>" value="<?= $k ?>" <?= in_array($k, $act) ? 'checked' : '' ?>><label class="form-check-label" for="mod<?= $k ?>"><?= $l ?></label></div><?php endforeach; ?>
    <div class="form-text">Las reservas solo están disponibles si ofreces servicios.</div></div>
  <p class="small text-muted">El logotipo, la portada, horarios y métodos de pago se editan en <a href="<?= url('panel/negocio.php') ?>">Mi negocio</a>.</p>
  <button class="btn btn-brand align-self-start">Guardar</button></form></div>
<div class="col-lg-5"><div class="card card-body"><h6>Mis categorías de productos</h6>
  <form method="post" class="d-flex gap-2 mb-3"><?= csrf_field() ?><input type="hidden" name="accion" value="cat_add"><input class="form-control" name="nombre" placeholder="Nueva categoría" required><button class="btn btn-outline-primary">Agregar</button></form>
  <ul class="list-group"><?php foreach ($cats as $c): ?><li class="list-group-item d-flex justify-content-between align-items-center"><?= e($c['nombre']) ?>
    <form method="post"><?= csrf_field() ?><input type="hidden" name="accion" value="cat_del"><input type="hidden" name="cid" value="<?= $c['id'] ?>"><button class="btn btn-sm btn-outline-danger" data-confirm="¿Eliminar categoría?"><i class="bi bi-trash"></i></button></form></li><?php endforeach; if (!$cats) echo '<li class="list-group-item text-muted small">Aún no tienes categorías.</li>'; ?></ul></div></div>
</div>
<?php include __DIR__ . '/../includes/panel_footer.php'; ?>
