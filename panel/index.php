<?php
require_once __DIR__ . '/../includes/bootstrap.php';
$n = require_negocio(); $id = $n['id'];
generar_alertas($n);
$title = 'Resumen'; $active = 'index'; $chart = true;
$mes = date('Y-m-01');
$totVentas = (float)qv('SELECT COALESCE(SUM(total),0) FROM ventas WHERE negocio_id=?', [$id]);
$ingresos = (float)qv("SELECT COALESCE(SUM(monto),0) FROM movimientos_financieros WHERE negocio_id=? AND tipo='ingreso' AND fecha>=?", [$id, $mes]);
$gastos = (float)qv("SELECT COALESCE(SUM(monto),0) FROM movimientos_financieros WHERE negocio_id=? AND tipo='gasto' AND fecha>=?", [$id, $mes]);
$nProd = (int)qv('SELECT COUNT(*) FROM productos WHERE negocio_id=? AND activo=1', [$id]);
$bajos = qa("SELECT nombre, stock, stock_minimo FROM productos WHERE negocio_id=? AND tipo='producto' AND activo=1 AND stock<=stock_minimo ORDER BY stock LIMIT 6", [$id]);
$pend = qa("SELECT * FROM pedidos WHERE negocio_id=? AND estado='pendiente' ORDER BY fecha_solicitud DESC LIMIT 6", [$id]);
$entregas = qa("SELECT * FROM pedidos WHERE negocio_id=? AND estado IN ('confirmado','en_preparacion','listo') AND fecha_entrega>=CURDATE() ORDER BY fecha_entrega LIMIT 6", [$id]);
$ultVentas = qa('SELECT v.*, c.nombres FROM ventas v LEFT JOIN clientes c ON c.id=v.cliente_id WHERE v.negocio_id=? ORDER BY v.fecha DESC LIMIT 6', [$id]);
$movs = qa('SELECT * FROM movimientos_financieros WHERE negocio_id=? ORDER BY fecha DESC, id DESC LIMIT 6', [$id]);
$notifs = qa('SELECT * FROM notificaciones WHERE usuario_id=? AND leida=0 ORDER BY creado_en DESC LIMIT 5', [$n['usuario_id']]);
// Gráfico: últimos 6 meses
$labels = $ing = $gas = [];
for ($i = 5; $i >= 0; $i--) {
    $m = date('Y-m', strtotime("-$i month")); $labels[] = date('M y', strtotime("$m-01"));
    $ing[] = (float)qv("SELECT COALESCE(SUM(monto),0) FROM movimientos_financieros WHERE negocio_id=? AND tipo='ingreso' AND DATE_FORMAT(fecha,'%Y-%m')=?", [$id, $m]);
    $gas[] = (float)qv("SELECT COALESCE(SUM(monto),0) FROM movimientos_financieros WHERE negocio_id=? AND tipo='gasto' AND DATE_FORMAT(fecha,'%Y-%m')=?", [$id, $m]);
}
$top = qa('SELECT vi.nombre, SUM(vi.cantidad) c FROM venta_items vi JOIN ventas v ON v.id=vi.venta_id WHERE v.negocio_id=? GROUP BY vi.nombre ORDER BY c DESC LIMIT 5', [$id]);
$page_js = '<script>
new Chart(document.getElementById("c1"),{type:"bar",data:{labels:' . json_encode($labels) . ',datasets:[{label:"Ingresos",data:' . json_encode($ing) . ',backgroundColor:"#0f766e"},{label:"Gastos",data:' . json_encode($gas) . ',backgroundColor:"#f59e0b"}]},options:{responsive:true}});
new Chart(document.getElementById("c2"),{type:"doughnut",data:{labels:' . json_encode(array_column($top, 'nombre')) . ',datasets:[{data:' . json_encode(array_map('intval', array_column($top, 'c'))) . '}]}});
</script>';
include __DIR__ . '/../includes/panel_header.php';
function stat_card($l, $v, $i) { return '<div class="col-6 col-lg-3"><div class="card stat-card shadow-sm h-100"><div class="card-body"><div class="small text-muted"><i class="bi ' . $i . '"></i> ' . $l . '</div><div class="num">' . $v . '</div></div></div></div>'; }
?>
<h3 class="mb-1"><?= e($n['nombre']) ?></h3>
<p class="text-muted">Resumen general · <?= !$n['activo'] ? '<span class="badge text-bg-secondary">Negocio inactivo</span>' : 'Negocio activo' ?></p>
<div class="row g-3 mb-4">
  <?= stat_card('Total de ventas', money($totVentas), 'bi-cash-coin') ?>
  <?= stat_card('Ingresos del mes', money($ingresos), 'bi-arrow-down-circle') ?>
  <?= stat_card('Gastos del mes', money($gastos), 'bi-arrow-up-circle') ?>
  <?= stat_card('Ganancia estimada (mes)', money($ingresos - $gastos), 'bi-graph-up-arrow') ?>
  <?= stat_card('Productos registrados', $nProd, 'bi-box-seam') ?>
  <?= stat_card('Pocas existencias', count($bajos), 'bi-exclamation-triangle') ?>
  <?= stat_card('Pedidos pendientes', count($pend), 'bi-bag') ?>
  <?= stat_card('Próximas entregas', count($entregas), 'bi-truck') ?>
</div>
<div class="row g-3 mb-4">
  <div class="col-lg-8"><div class="card"><div class="card-body"><h6>Ingresos vs gastos (6 meses)</h6><canvas id="c1" height="110"></canvas></div></div></div>
  <div class="col-lg-4"><div class="card h-100"><div class="card-body"><h6>Más vendidos</h6><?php if ($top): ?><canvas id="c2"></canvas><?php else: ?><p class="text-muted small">Sin ventas aún.</p><?php endif; ?></div></div></div>
</div>
<div class="row g-3">
  <div class="col-lg-6"><div class="card"><div class="card-header">Notificaciones y alertas</div><ul class="list-group list-group-flush">
    <?php foreach ($notifs as $x): ?><li class="list-group-item small"><?= e($x['mensaje']) ?></li><?php endforeach; if (!$notifs) echo '<li class="list-group-item text-muted small">Sin alertas nuevas.</li>'; ?></ul></div></div>
  <div class="col-lg-6"><div class="card"><div class="card-header">Productos con pocas existencias</div><ul class="list-group list-group-flush">
    <?php foreach ($bajos as $b): ?><li class="list-group-item d-flex justify-content-between small"><?= e($b['nombre']) ?><span class="badge text-bg-<?= $b['stock'] <= 0 ? 'danger' : 'warning' ?>"><?= $b['stock'] ?> / mín. <?= $b['stock_minimo'] ?></span></li><?php endforeach; if (!$bajos) echo '<li class="list-group-item text-muted small">Todo en orden.</li>'; ?></ul></div></div>
  <div class="col-lg-6"><div class="card"><div class="card-header">Pedidos pendientes</div><ul class="list-group list-group-flush">
    <?php foreach ($pend as $p): ?><li class="list-group-item d-flex justify-content-between small"><a href="<?= url('panel/pedido.php?id=' . $p['id']) ?>"><?= e($p['referencia']) ?> · <?= e($p['nombre_comprador']) ?></a><span><?= money($p['total']) ?></span></li><?php endforeach; if (!$pend) echo '<li class="list-group-item text-muted small">Sin pedidos pendientes.</li>'; ?></ul></div></div>
  <div class="col-lg-6"><div class="card"><div class="card-header">Próximas entregas</div><ul class="list-group list-group-flush">
    <?php foreach ($entregas as $p): ?><li class="list-group-item d-flex justify-content-between small"><a href="<?= url('panel/pedido.php?id=' . $p['id']) ?>"><?= e($p['referencia']) ?> · <?= e($p['nombre_comprador']) ?></a><span><?= fdate($p['fecha_entrega']) ?></span></li><?php endforeach; if (!$entregas) echo '<li class="list-group-item text-muted small">Sin entregas programadas.</li>'; ?></ul></div></div>
  <div class="col-lg-6"><div class="card"><div class="card-header">Últimas ventas</div><ul class="list-group list-group-flush">
    <?php foreach ($ultVentas as $v): ?><li class="list-group-item d-flex justify-content-between small"><span><?= e($v['referencia']) ?> · <?= e($v['nombres'] ?? 'Cliente general') ?></span><span><?= money($v['total']) ?> <?= badge_estado($v['estado_pago']) ?></span></li><?php endforeach; if (!$ultVentas) echo '<li class="list-group-item text-muted small">Sin ventas.</li>'; ?></ul></div></div>
  <div class="col-lg-6"><div class="card"><div class="card-header">Últimos movimientos financieros</div><ul class="list-group list-group-flush">
    <?php foreach ($movs as $m): ?><li class="list-group-item d-flex justify-content-between small"><span><?= e($m['concepto']) ?></span><span class="text-<?= $m['tipo'] === 'ingreso' ? 'success' : 'danger' ?>"><?= $m['tipo'] === 'ingreso' ? '+' : '-' ?><?= money($m['monto']) ?></span></li><?php endforeach; if (!$movs) echo '<li class="list-group-item text-muted small">Sin movimientos.</li>'; ?></ul></div></div>
</div>
<?php include __DIR__ . '/../includes/panel_footer.php'; ?>
