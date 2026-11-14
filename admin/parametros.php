<?php
require_once __DIR__ . '/../includes/bootstrap.php';
require_login(['admin']);
if (is_post()) {
    csrf_verify();
    foreach ($_POST['p'] ?? [] as $k => $v) q('UPDATE parametros SET valor=? WHERE clave=?', [mb_substr(trim($v), 0, 255), $k]);
    log_activity('admin_parametros'); flash('success', 'Parámetros guardados.'); redirect('admin/parametros.php');
}
$title = 'Parámetros'; $active = 'parametros';
$lista = qa('SELECT * FROM parametros ORDER BY clave');
include __DIR__ . '/../includes/panel_header.php';
?>
<h3>Parámetros generales</h3>
<form method="post" class="card card-body" style="max-width:800px"><?= csrf_field() ?>
<?php foreach ($lista as $p): ?><div class="mb-3"><label class="form-label"><?= e($p['descripcion'] ?: $p['clave']) ?> <code class="small"><?= e($p['clave']) ?></code></label><input class="form-control" name="p[<?= e($p['clave']) ?>]" value="<?= e($p['valor']) ?>"></div><?php endforeach; ?>
<button class="btn btn-brand align-self-start">Guardar</button></form>
<?php include __DIR__ . '/../includes/panel_footer.php'; ?>
