<?php
/**
 * Detalle de un pedido del comprador.
 *
 * Muestra productos, total y estado; permite cancelar mientras esté
 * pendiente y abrir el comprobante cuando el pedido ya fue confirmado.
 * Parámetro GET: id (del pedido).
 */

require_once __DIR__ . '/../includes/bootstrap.php';

$comprador = require_login(['comprador']);

// El filtro por comprador_id impide ver pedidos de otras personas.
$pedido = consultar_uno(
    'SELECT p.*, e.nombre AS negocio, e.usuario_id AS dueno_id
     FROM pedidos p
     JOIN emprendimientos e ON e.id = p.negocio_id
     WHERE p.id = ? AND p.comprador_id = ?',
    [(int) get('id'), $comprador['id']]
);
if (!$pedido) {
    http_response_code(404);
    exit('Pedido no encontrado.');
}

if (is_post()) {
    csrf_verify();

    if ($pedido['estado'] === 'pendiente') {
        consultar(
            "UPDATE pedidos SET estado='cancelado', motivo='Cancelado por el comprador' WHERE id=?",
            [$pedido['id']]
        );
        notify(
            (int) $pedido['dueno_id'],
            'pedido',
            'El comprador canceló el pedido ' . $pedido['referencia'],
            'panel/pedido.php?id=' . $pedido['id']
        );
        flash('success', 'Pedido cancelado.');
    }
    redirect('comprador/pedido.php?id=' . $pedido['id']);
}

$items = consultar_todos('SELECT * FROM pedido_items WHERE pedido_id = ?', [$pedido['id']]);

$title = 'Pedido ' . $pedido['referencia'];
$active = 'index';
include __DIR__ . '/../includes/panel_header.php';
?>
<a href="<?= url('comprador/index.php') ?>" class="small">&larr; Mis pedidos</a>
<h3 class="mt-2">Pedido <?= e($pedido['referencia']) ?> <?= badge_estado($pedido['estado']) ?></h3>
<p class="text-muted">
  <?= e($pedido['negocio']) ?> · Solicitado el <?= fdate($pedido['fecha_solicitud'], true) ?> · Entrega: <?= fdate($pedido['fecha_entrega']) ?>
</p>
<?php if ($pedido['motivo']): ?>
  <div class="alert alert-light border">Motivo: <?= e($pedido['motivo']) ?></div>
<?php endif; ?>

<div class="table-responsive mb-3">
  <table class="table mb-0">
    <thead><tr><th>Producto</th><th>Cant.</th><th>Precio</th><th>Subtotal</th></tr></thead>
    <tbody>
      <?php foreach ($items as $item): ?>
        <tr>
          <td>
            <?= e($item['nombre']) ?>
            <?php if ($item['observacion']): ?><div class="small text-muted"><?= e($item['observacion']) ?></div><?php endif; ?>
          </td>
          <td><?= $item['cantidad'] ?></td>
          <td><?= money($item['precio']) ?></td>
          <td><?= money($item['precio'] * $item['cantidad']) ?></td>
        </tr>
      <?php endforeach; ?>
      <tr class="table-light">
        <td colspan="3" class="text-end fw-bold">Total</td>
        <td class="fw-bold"><?= money($pedido['total']) ?></td>
      </tr>
    </tbody>
  </table>
</div>

<div class="d-flex gap-2">
  <?php if ($pedido['venta_id']): ?>
    <a class="btn btn-brand" href="<?= url('comprobante.php?id=' . $pedido['venta_id']) ?>"><i class="bi bi-receipt"></i> Ver comprobante</a>
  <?php endif; ?>
  <?php if ($pedido['estado'] === 'pendiente'): ?>
    <form method="post">
      <?= csrf_field() ?>
      <button class="btn btn-outline-danger" data-confirm="¿Cancelar el pedido?">Cancelar pedido</button>
    </form>
  <?php endif; ?>
</div>
<?php include __DIR__ . '/../includes/panel_footer.php'; ?>