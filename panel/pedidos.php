<?php
require_once __DIR__ . '/../includes/bootstrap.php';
$n = require_negocio(); require_modulo($n, 'pedidos'); $id = $n['id'];
$title = 'Pedidos'; $active = 'pedidos';
$f = get('estado'); $vista = get('v');
$sql = 'SELECT * FROM pedidos WHERE negocio_id=?'; $par = [$id];
if ($vista === 'pendientes') $sql .= " AND estado IN ('pendiente','confirmado','en_preparacion','listo')";
elseif ($vista === 'finalizados') $sql .= " AND estado IN ('entregado','rechazado','cancelado')";
if ($f !== '') { $sql .= ' AND estado=?'; $par[] = $f; }
$lista = qa($sql . ' ORDER BY fecha_solicitud DESC LIMIT 200', $par);
include __DIR__ . '/../includes/panel_header.php';
?>
<h3>Pedidos</h3>
<ul class="nav nav-pills mb-3"><li class="nav-item"><a class="nav-link <?= $vista === '' ? 'active' : '' ?>" href="?">Historial</a></li><li class="nav-item"><a class="nav-link <?= $vista === 'pendientes' ? 'active' : '' ?>" href="?v=pendientes">Pendientes</a></li><li class="nav-item"><a class="nav-link <?= $vista === 'finalizados' ? 'active' : '' ?>" href="?v=finalizados">Finalizados</a></li></ul>
<div class="table-responsive"><table class="table table-hover align-middle mb-0"><thead><tr><th>Referencia</th><th>Comprador</th><th>Solicitud</th><th>Entrega</th><th>Total</th><th>Estado</th><th></th></tr></thead><tbody>
<?php foreach ($lista as $p): ?><tr><td><?= e($p['referencia']) ?></td><td><?= e($p['nombre_comprador']) ?></td><td><?= fdate($p['fecha_solicitud'], true) ?></td><td><?= fdate($p['fecha_entrega']) ?></td><td><?= money($p['total']) ?></td><td><?= badge_estado($p['estado']) ?></td><td><a class="btn btn-sm btn-outline-primary" href="<?= url('panel/pedido.php?id=' . $p['id']) ?>">Gestionar</a></td></tr><?php endforeach; ?>
<?php if (!$lista): ?><tr><td colspan="7" class="text-center text-muted py-4">Sin pedidos.</td></tr><?php endif; ?></tbody></table></div>
<?php include __DIR__ . '/../includes/panel_footer.php'; ?>
