<?php
require_once __DIR__ . '/../includes/bootstrap.php';
$n = require_negocio(); require_modulo($n, 'finanzas'); $id = $n['id'];
if (is_post()) {
    csrf_verify();
    $tipo = post('tipo'); $monto = (float)post('monto'); $f = post('fecha');
    if (!in_array($tipo, ['ingreso', 'gasto']) || $monto <= 0 || post('concepto') === '' || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $f)) flash('danger', 'Revisa tipo, concepto, monto y fecha.');
    else { q('INSERT INTO movimientos_financieros (negocio_id, tipo, categoria, concepto, monto, fecha) VALUES (?,?,?,?,?,?)', [$id, $tipo, post('categoria') ?: 'General', post('concepto'), $monto, $f]); flash('success', 'Movimiento registrado.'); }
    redirect('panel/finanzas.php');
}
$title = 'Finanzas'; $active = 'finanzas'; $chart = true;
$d1 = preg_match('/^\d{4}-\d{2}-\d{2}$/', get('d1')) ? get('d1') : date('Y-m-01'); $d2 = preg_match('/^\d{4}-\d{2}-\d{2}$/', get('d2')) ? get('d2') : date('Y-m-d');
$tot = fn($t) => (float)qv('SELECT COALESCE(SUM(monto),0) FROM movimientos_financieros WHERE negocio_id=? AND tipo=? AND fecha BETWEEN ? AND ?', [$id, $t, $d1, $d2]);
$ing = $tot('ingreso'); $gas = $tot('gasto');
$movs = qa('SELECT * FROM movimientos_financieros WHERE negocio_id=? AND fecha BETWEEN ? AND ? ORDER BY fecha DESC, id DESC LIMIT 300', [$id, $d1, $d2]);
$gcat = qa("SELECT categoria, SUM(monto) t, COUNT(*) c FROM movimientos_financieros WHERE negocio_id=? AND tipo='gasto' AND fecha BETWEEN ? AND ? GROUP BY categoria ORDER BY c DESC, t DESC", [$id, $d1, $d2]);
$icat = qa("SELECT categoria, SUM(monto) t FROM movimientos_financieros WHERE negocio_id=? AND tipo='ingreso' AND fecha BETWEEN ? AND ? GROUP BY categoria ORDER BY t DESC", [$id, $d1, $d2]);
$cats = array_column(qa('SELECT DISTINCT categoria FROM movimientos_financieros WHERE negocio_id=?', [$id]), 'categoria');
$page_js = '<script>new Chart(document.getElementById("cg"),{type:"pie",data:{labels:' . json_encode(array_column($gcat, 'categoria')) . ',datasets:[{data:' . json_encode(array_map('floatval', array_column($gcat, 't'))) . '}]}});</script>';
include __DIR__ . '/../includes/panel_header.php';
?>
<h3>Finanzas</h3>
<form class="row g-2 mb-3"><div class="col-auto"><input type="date" name="d1" class="form-control" value="<?= $d1 ?>"></div><div class="col-auto"><input type="date" name="d2" class="form-control" value="<?= $d2 ?>"></div><div class="col-auto"><button class="btn btn-outline-secondary">Filtrar periodo</button></div></form>
<div class="row g-3 mb-4">
  <div class="col-md-3"><div class="card stat-card"><div class="card-body"><div class="small text-muted">Ingresos</div><div class="num text-success"><?= money($ing) ?></div></div></div></div>
  <div class="col-md-3"><div class="card stat-card"><div class="card-body"><div class="small text-muted">Gastos</div><div class="num text-danger"><?= money($gas) ?></div></div></div></div>
  <div class="col-md-3"><div class="card stat-card"><div class="card-body"><div class="small text-muted">Ganancia estimada / balance</div><div class="num"><?= money($ing - $gas) ?></div></div></div></div>
</div>
<div class="row g-3 mb-4">
  <div class="col-lg-5"><div class="card card-body"><h6>Registrar movimiento</h6><form method="post" class="row g-2"><?= csrf_field() ?>
    <div class="col-5"><select name="tipo" class="form-select"><option value="gasto">Gasto</option><option value="ingreso">Ingreso</option></select></div><div class="col-7"><input type="date" name="fecha" class="form-control" value="<?= date('Y-m-d') ?>" required></div>
    <div class="col-12"><input class="form-control" name="concepto" placeholder="Concepto" required></div>
    <div class="col-7"><input class="form-control" name="categoria" list="cl" placeholder="Categoría (Insumos, Servicios, Transporte…)"><datalist id="cl"><?php foreach (array_unique(array_merge(['Insumos', 'Costos de producción', 'Servicios públicos', 'Transporte', 'Arriendo', 'Publicidad', 'Nómina', 'Ventas'], $cats)) as $c): ?><option value="<?= e($c) ?>"><?php endforeach; ?></datalist></div>
    <div class="col-5"><input type="number" step="0.01" min="0.01" class="form-control" name="monto" placeholder="Monto" required></div>
    <div class="col-12"><button class="btn btn-brand">Guardar</button></div></form></div></div>
  <div class="col-lg-4"><div class="card card-body h-100"><h6>Gastos más frecuentes</h6><ul class="list-unstyled small mb-0"><?php foreach ($gcat as $g): ?><li class="d-flex justify-content-between"><span><?= e($g['categoria']) ?> (<?= $g['c'] ?>x)</span><strong><?= money($g['t']) ?></strong></li><?php endforeach; if (!$gcat) echo '<li class="text-muted">Sin gastos.</li>'; ?></ul>
    <h6 class="mt-3">Ingresos por categoría</h6><ul class="list-unstyled small mb-0"><?php foreach ($icat as $g): ?><li class="d-flex justify-content-between"><span><?= e($g['categoria']) ?></span><strong><?= money($g['t']) ?></strong></li><?php endforeach; ?></ul></div></div>
  <div class="col-lg-3"><div class="card card-body h-100"><h6>Gastos por categoría</h6><?php if ($gcat): ?><canvas id="cg"></canvas><?php endif; ?></div></div>
</div>
<h5>Historial de movimientos</h5>
<div class="table-responsive"><table class="table table-sm align-middle mb-0"><thead><tr><th>Fecha</th><th>Tipo</th><th>Categoría</th><th>Concepto</th><th class="text-end">Monto</th></tr></thead><tbody>
<?php foreach ($movs as $m): ?><tr><td><?= fdate($m['fecha']) ?></td><td><?= $m['tipo'] === 'ingreso' ? '<span class="badge text-bg-success">Ingreso</span>' : '<span class="badge text-bg-danger">Gasto</span>' ?></td><td><?= e($m['categoria']) ?></td><td><?= e($m['concepto']) ?></td><td class="text-end"><?= money($m['monto']) ?></td></tr><?php endforeach; ?></tbody></table></div>
<?php include __DIR__ . '/../includes/panel_footer.php'; ?>
