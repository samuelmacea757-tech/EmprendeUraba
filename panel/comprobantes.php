<?php
require_once __DIR__ . '/../includes/bootstrap.php';
$n = require_negocio(); require_modulo($n, 'ventas');
$title = 'Comprobantes'; $active = 'comprobantes';
$lista = qa('SELECT v.*, c.nombres, c.apellidos FROM ventas v LEFT JOIN clientes c ON c.id=v.cliente_id WHERE v.negocio_id=? ORDER BY v.fecha DESC LIMIT 200', [$n['id']]);
include __DIR__ . '/../includes/panel_header.php';
?>
<h3>Comprobantes anteriores</h3>
<div class="table-responsive"><table class="table align-middle mb-0"><thead><tr><th>N.º</th><th>Fecha</th><th>Cliente</th><th>Total</th><th>Pago</th><th></th></tr></thead><tbody>
<?php foreach ($lista as $v): ?><tr><td><?= e($v['referencia']) ?></td><td><?= fdate($v['fecha'], true) ?></td><td><?= e(trim($v['nombres'] . ' ' . $v['apellidos']) ?: 'General') ?></td><td><?= money($v['total']) ?></td><td><?= badge_estado($v['estado_pago']) ?></td>
<td><a class="btn btn-sm btn-outline-primary" href="<?= url('comprobante.php?id=' . $v['id']) ?>">Ver</a> <a class="btn btn-sm btn-outline-danger" href="<?= url('comprobante.php?id=' . $v['id'] . '&pdf=1') ?>">PDF</a></td></tr><?php endforeach; ?></tbody></table></div>
<?php include __DIR__ . '/../includes/panel_footer.php'; ?>
