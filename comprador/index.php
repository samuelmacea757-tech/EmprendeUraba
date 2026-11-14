<?php
/**
 * Panel del comprador: lista de sus pedidos y de sus reservas.
 *
 * Desde aquí puede cancelar una reserva pendiente o aceptada. Los pedidos
 * se consultan y cancelan en comprador/pedido.php.
 */

require_once __DIR__ . '/../includes/bootstrap.php';

$comprador = require_login(['comprador']);

// Cancelar una reserva propia (el filtro por comprador_id impide tocar las ajenas).
if (is_post()) {
    csrf_verify();

    $reserva = consultar_uno(
        "SELECT * FROM reservas WHERE id=? AND comprador_id=? AND estado IN ('pendiente','aceptada')",
        [(int) post('reserva_id'), $comprador['id']]
    );

    if ($reserva) {
        consultar("UPDATE reservas SET estado='cancelada' WHERE id=?", [$reserva['id']]);

        $dueno_id = (int) consultar_valor('SELECT usuario_id FROM emprendimientos WHERE id=?', [$reserva['negocio_id']]);
        notify(
            $dueno_id,
            'reserva',
            'Reserva cancelada por el cliente: ' . $reserva['nombre'] . ' ' . fdate($reserva['fecha']),
            'panel/reservas.php'
        );
        flash('success', 'Reserva cancelada.');
    }
    redirect('comprador/index.php');
}

$title = 'Mis pedidos';
$active = 'index';

$pedidos = consultar_todos(
    'SELECT p.*, e.nombre AS negocio
     FROM pedidos p
     JOIN emprendimientos e ON e.id = p.negocio_id
     WHERE p.comprador_id = ?
     ORDER BY p.fecha_solicitud DESC',
    [$comprador['id']]
);
$reservas = consultar_todos(
    'SELECT r.*, s.nombre AS servicio, e.nombre AS negocio
     FROM reservas r
     JOIN productos s ON s.id = r.servicio_id
     JOIN emprendimientos e ON e.id = r.negocio_id
     WHERE r.comprador_id = ?
     ORDER BY r.fecha DESC, r.hora DESC',
    [$comprador['id']]
);

include __DIR__ . '/../includes/panel_header.php';
?>
<h3>Mis pedidos</h3>
<div class="table-responsive mb-4">
  <table class="table table-hover align-middle mb-0">
    <thead><tr><th>Referencia</th><th>Negocio</th><th>Fecha</th><th>Entrega</th><th>Total</th><th>Estado</th><th></th></tr></thead>
    <tbody>
      <?php foreach ($pedidos as $pedido): ?>
        <tr>
          <td><?= e($pedido['referencia']) ?></td>
          <td><?= e($pedido['negocio']) ?></td>
          <td><?= fdate($pedido['fecha_solicitud'], true) ?></td>
          <td><?= fdate($pedido['fecha_entrega']) ?></td>
          <td><?= money($pedido['total']) ?></td>
          <td><?= badge_estado($pedido['estado']) ?></td>
          <td><a class="btn btn-sm btn-outline-secondary" href="<?= url('comprador/pedido.php?id=' . $pedido['id']) ?>">Ver</a></td>
        </tr>
      <?php endforeach; ?>
      <?php if (!$pedidos): ?>
        <tr><td colspan="7" class="text-center text-muted py-4">Aún no has hecho pedidos. <a href="<?= url('catalogo.php') ?>">Explorar catálogo</a></td></tr>
      <?php endif; ?>
    </tbody>
  </table>
</div>

<h3>Mis reservas</h3>
<div class="table-responsive">
  <table class="table align-middle mb-0">
    <thead><tr><th>Servicio</th><th>Negocio</th><th>Fecha</th><th>Hora</th><th>Estado</th><th></th></tr></thead>
    <tbody>
      <?php foreach ($reservas as $reserva): ?>
        <tr>
          <td><?= e($reserva['servicio']) ?></td>
          <td><?= e($reserva['negocio']) ?></td>
          <td><?= fdate($reserva['fecha']) ?></td>
          <td><?= substr($reserva['hora'], 0, 5) ?></td>
          <td><?= badge_estado($reserva['estado']) ?></td>
          <td>
            <?php if (in_array($reserva['estado'], ['pendiente', 'aceptada'])): ?>
              <form method="post">
                <?= csrf_field() ?>
                <input type="hidden" name="reserva_id" value="<?= $reserva['id'] ?>">
                <button class="btn btn-sm btn-outline-danger" data-confirm="¿Cancelar esta reserva?">Cancelar</button>
              </form>
            <?php endif; ?>
          </td>
        </tr>
      <?php endforeach; ?>
      <?php if (!$reservas): ?>
        <tr><td colspan="6" class="text-center text-muted py-4">No tienes reservas.</td></tr>
      <?php endif; ?>
    </tbody>
  </table>
</div>
<?php include __DIR__ . '/../includes/panel_footer.php'; ?>