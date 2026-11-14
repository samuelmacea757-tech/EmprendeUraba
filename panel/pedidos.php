<?php
/**
 * Listado de pedidos del negocio.
 *
 * Vistas: historial completo, pendientes (en curso) y finalizados.
 * Parámetros GET: v (pendientes|finalizados), estado (filtro puntual).
 * La gestión de cada pedido se hace en panel/pedido.php.
 */

require_once __DIR__ . '/../includes/bootstrap.php';

$negocio = require_negocio();
require_modulo($negocio, 'pedidos');
$negocio_id = $negocio['id'];

$title = 'Pedidos';
$active = 'pedidos';
$filtro_estado = get('estado');
$vista = get('v');

$sql = 'SELECT * FROM pedidos WHERE negocio_id = ?';
$parametros = [$negocio_id];

if ($vista === 'pendientes') {
    $sql .= " AND estado IN ('pendiente','confirmado','en_preparacion','listo')";
} elseif ($vista === 'finalizados') {
    $sql .= " AND estado IN ('entregado','rechazado','cancelado')";
}
if ($filtro_estado !== '') {
    $sql .= ' AND estado = ?';
    $parametros[] = $filtro_estado;
}
$pedidos = consultar_todos($sql . ' ORDER BY fecha_solicitud DESC LIMIT 200', $parametros);

include __DIR__ . '/../includes/panel_header.php';
?>
<h3>Pedidos</h3>
<ul class="nav nav-pills mb-3">
  <li class="nav-item"><a class="nav-link <?= $vista === '' ? 'active' : '' ?>" href="?">Historial</a></li>
  <li class="nav-item"><a class="nav-link <?= $vista === 'pendientes' ? 'active' : '' ?>" href="?v=pendientes">Pendientes</a></li>
  <li class="nav-item"><a class="nav-link <?= $vista === 'finalizados' ? 'active' : '' ?>" href="?v=finalizados">Finalizados</a></li>
</ul>

<div class="table-responsive">
  <table class="table table-hover align-middle mb-0">
    <thead><tr><th>Referencia</th><th>Comprador</th><th>Solicitud</th><th>Entrega</th><th>Total</th><th>Estado</th><th></th></tr></thead>
    <tbody>
      <?php foreach ($pedidos as $pedido): ?>
        <tr>
          <td><?= e($pedido['referencia']) ?></td>
          <td><?= e($pedido['nombre_comprador']) ?></td>
          <td><?= fdate($pedido['fecha_solicitud'], true) ?></td>
          <td><?= fdate($pedido['fecha_entrega']) ?></td>
          <td><?= money($pedido['total']) ?></td>
          <td><?= badge_estado($pedido['estado']) ?></td>
          <td><a class="btn btn-sm btn-outline-primary" href="<?= url('panel/pedido.php?id=' . $pedido['id']) ?>">Gestionar</a></td>
        </tr>
      <?php endforeach; ?>
      <?php if (!$pedidos): ?>
        <tr><td colspan="7" class="text-center text-muted py-4">Sin pedidos.</td></tr>
      <?php endif; ?>
    </tbody>
  </table>
</div>
<?php include __DIR__ . '/../includes/panel_footer.php'; ?>