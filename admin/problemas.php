<?php
require_once __DIR__ . '/../includes/bootstrap.php';
require_login(['admin']);
if (is_post()) {
    csrf_verify(); $pr = q1('SELECT * FROM problemas WHERE id=?', [(int)post('id')]);
    if ($pr && in_array(post('estado'), ['abierto', 'en_proceso', 'resuelto'])) {
        q('UPDATE problemas SET estado=?, respuesta=? WHERE id=?', [post('estado'), post('respuesta') ?: null, $pr['id']]);
        notify((int)$pr['usuario_id'], 'problema', 'Tu reporte "' . $pr['asunto'] . '" cambió a: ' . str_replace('_', ' ', post('estado')), 'reportar.php'); flash('success', 'Reporte actualizado.');
    }
    redirect('admin/problemas.php');
}
$title = 'Reportes de problemas'; $active = 'problemas';
$lista = qa("SELECT p.*, CONCAT(u.nombre,' ',u.apellido) usuario, u.email FROM problemas p JOIN usuarios u ON u.id=p.usuario_id ORDER BY FIELD(p.estado,'abierto','en_proceso','resuelto'), p.creado_en DESC");
include __DIR__ . '/../includes/panel_header.php';
?>
<h3>Reportes de problemas</h3>
<?php foreach ($lista as $p): ?><div class="card mb-2"><div class="card-body"><div class="d-flex justify-content-between"><strong><?= e($p['asunto']) ?></strong><?= badge_estado($p['estado']) ?></div>
<div class="small text-muted"><?= e($p['usuario']) ?> · <?= e($p['email']) ?> · <?= fdate($p['creado_en'], true) ?></div><p class="my-2"><?= nl2br(e($p['descripcion'])) ?></p>
<form method="post" class="row g-2"><?= csrf_field() ?><input type="hidden" name="id" value="<?= $p['id'] ?>"><div class="col-md-6"><input class="form-control form-control-sm" name="respuesta" placeholder="Respuesta" value="<?= e($p['respuesta']) ?>"></div>
<div class="col-md-3"><select name="estado" class="form-select form-select-sm"><?php foreach (['abierto', 'en_proceso', 'resuelto'] as $s): ?><option value="<?= $s ?>" <?= $p['estado'] === $s ? 'selected' : '' ?>><?= ucfirst(str_replace('_', ' ', $s)) ?></option><?php endforeach; ?></select></div><div class="col-auto"><button class="btn btn-sm btn-brand">Guardar</button></div></form></div></div><?php endforeach; ?>
<?php if (!$lista): ?><p class="text-muted">No hay reportes.</p><?php endif; ?>
<?php include __DIR__ . '/../includes/panel_footer.php'; ?>
