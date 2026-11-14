<?php
/**
 * Reporte de problemas (todos los roles).
 *
 * El usuario describe un inconveniente; el administrador lo gestiona desde
 * Admin > Reportes de problemas y puede responder aquí mismo.
 */

require_once __DIR__ . '/includes/bootstrap.php';

$usuario = require_login();

if (is_post()) {
    csrf_verify();

    if (post('asunto') === '' || post('descripcion') === '') {
        flash('danger', 'Completa el asunto y la descripción.');
    } else {
        consultar(
            'INSERT INTO problemas (usuario_id, asunto, descripcion) VALUES (?,?,?)',
            [$usuario['id'], mb_substr(post('asunto'), 0, 150), post('descripcion')]
        );
        flash('success', 'Reporte enviado al administrador. ¡Gracias!');
    }
    redirect('reportar.php');
}

$title = 'Reportar un problema';
$active = 'reportar';
$mis_reportes = consultar_todos(
    'SELECT * FROM problemas WHERE usuario_id=? ORDER BY creado_en DESC',
    [$usuario['id']]
);

include __DIR__ . '/includes/panel_header.php';
?>
<h3>Reportar un problema</h3>
<form method="post" class="card card-body mb-4" style="max-width:700px">
  <?= csrf_field() ?>
  <input class="form-control mb-2" name="asunto" placeholder="Asunto" required maxlength="150">
  <textarea class="form-control mb-2" name="descripcion" rows="4" placeholder="Describe lo que ocurrió" required></textarea>
  <button class="btn btn-brand align-self-start">Enviar reporte</button>
</form>

<?php foreach ($mis_reportes as $reporte): ?>
  <div class="card mb-2"><div class="card-body">
    <div class="d-flex justify-content-between">
      <strong><?= e($reporte['asunto']) ?></strong>
      <?= badge_estado($reporte['estado']) ?>
    </div>
    <p class="mb-1 small"><?= nl2br(e($reporte['descripcion'])) ?></p>
    <?php if ($reporte['respuesta']): ?>
      <div class="alert alert-success py-2 mb-0 small"><strong>Respuesta:</strong> <?= nl2br(e($reporte['respuesta'])) ?></div>
    <?php endif; ?>
  </div></div>
<?php endforeach; ?>
<?php include __DIR__ . '/includes/panel_footer.php'; ?>