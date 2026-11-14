<?php
/**
 * Bandeja de notificaciones del usuario (todos los roles).
 *
 * Si el usuario es emprendedor, al abrirla se generan sus alertas
 * automáticas (stock bajo, entregas, citas, etc.).
 */

require_once __DIR__ . '/includes/bootstrap.php';

$usuario = require_login();

if ($usuario['rol'] === 'emprendedor' && ($negocio = negocio_actual())) {
    generar_alertas($negocio);
}

if (is_post()) {
    csrf_verify();

    if (post('accion') === 'todas') {
        consultar('UPDATE notificaciones SET leida=1 WHERE usuario_id=?', [$usuario['id']]);
    } elseif (post('id')) {
        // El filtro por usuario_id impide marcar notificaciones ajenas.
        consultar('UPDATE notificaciones SET leida=1 WHERE id=? AND usuario_id=?', [(int) post('id'), $usuario['id']]);
    }
    redirect('notificaciones.php');
}

$title = 'Notificaciones';
$active = 'notificaciones';
$notificaciones = consultar_todos(
    'SELECT * FROM notificaciones WHERE usuario_id=? ORDER BY leida, creado_en DESC LIMIT 100',
    [$usuario['id']]
);

include __DIR__ . '/includes/panel_header.php';
?>
<div class="d-flex justify-content-between mb-3">
  <h3>Notificaciones</h3>
  <form method="post">
    <?= csrf_field() ?>
    <input type="hidden" name="accion" value="todas">
    <button class="btn btn-sm btn-outline-secondary">Marcar todas como leídas</button>
  </form>
</div>

<div class="list-group">
  <?php foreach ($notificaciones as $notificacion): ?>
    <div class="list-group-item d-flex justify-content-between align-items-center <?= $notificacion['leida'] ? '' : 'list-group-item-warning' ?>">
      <div>
        <?php if ($notificacion['enlace']): ?>
          <a href="<?= url($notificacion['enlace']) ?>" class="text-reset"><?= e($notificacion['mensaje']) ?></a>
        <?php else: ?>
          <?= e($notificacion['mensaje']) ?>
        <?php endif; ?>
        <div class="small text-muted"><?= fdate($notificacion['creado_en'], true) ?></div>
      </div>
      <?php if (!$notificacion['leida']): ?>
        <form method="post">
          <?= csrf_field() ?>
          <input type="hidden" name="id" value="<?= $notificacion['id'] ?>">
          <button class="btn btn-sm btn-outline-dark">Leída</button>
        </form>
      <?php endif; ?>
    </div>
  <?php endforeach; ?>
  <?php if (!$notificaciones): ?>
    <div class="list-group-item text-muted">No tienes notificaciones.</div>
  <?php endif; ?>
</div>
<?php include __DIR__ . '/includes/panel_footer.php'; ?>