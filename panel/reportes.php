<?php
require_once __DIR__ . '/../includes/bootstrap.php';
$n = require_negocio(); require_modulo($n, 'reportes'); $id = $n['id'];   // Todos los reportes se filtran por el negocio autenticado
$tipos = ['ventas' => 'Ventas', 'ingresos' => 'Ingresos', 'gastos' => 'Gastos', 'ganancias' => 'Ganancias estimadas', 'inventario' => 'Inventario', 'productos' => 'Productos más vendidos',
          'pedidos' => 'Pedidos', 'clientes' => 'Clientes', 'reservas' => 'Reservas'];
$t = array_key_exists(get('tipo'), $tipos) ? get('tipo') : 'ventas';
$d1 = preg_match('/^\d{4}-\d{2}-\d{2}$/', get('d1')) ? get('d1') : date('Y-m-01'); $d2 = preg_match('/^\d{4}-\d{2}-\d{2}$/', get('d2')) ? get('d2') : date('Y-m-d');
$cat = get('cat');
$cols = []; $rows = []; $chartCol = null;
switch ($t) {
    case 'ventas':
        $cols = ['Referencia', 'Fecha', 'Cliente', 'Subtotal', 'Descuento', 'Total', 'Estado pago'];
        $rows = qa("SELECT v.referencia, DATE(v.fecha) f, COALESCE(CONCAT(c.nombres,' ',c.apellidos),'General') cl, v.subtotal, v.descuento, v.total, v.estado_pago FROM ventas v LEFT JOIN clientes c ON c.id=v.cliente_id WHERE v.negocio_id=? AND DATE(v.fecha) BETWEEN ? AND ? ORDER BY v.fecha DESC", [$id, $d1, $d2]); break;
    case 'ingresos': case 'gastos':
        $tp = $t === 'ingresos' ? 'ingreso' : 'gasto'; $cols = ['Fecha', 'Categoría', 'Concepto', 'Monto'];
        $sql = 'SELECT fecha, categoria, concepto, monto FROM movimientos_financieros WHERE negocio_id=? AND tipo=? AND fecha BETWEEN ? AND ?'; $par = [$id, $tp, $d1, $d2];
        if ($cat !== '') { $sql .= ' AND categoria=?'; $par[] = $cat; } $rows = qa($sql . ' ORDER BY fecha DESC', $par); break;
    case 'ganancias':
        $cols = ['Mes', 'Ingresos', 'Gastos', 'Ganancia estimada'];
        $rows = qa("SELECT DATE_FORMAT(fecha,'%Y-%m') mes, SUM(CASE WHEN tipo='ingreso' THEN monto ELSE 0 END) ing, SUM(CASE WHEN tipo='gasto' THEN monto ELSE 0 END) gas, SUM(CASE WHEN tipo='ingreso' THEN monto ELSE -monto END) gan FROM movimientos_financieros WHERE negocio_id=? AND fecha BETWEEN ? AND ? GROUP BY mes ORDER BY mes", [$id, $d1, $d2]); break;
    case 'inventario':
        $cols = ['Producto', 'Categoría', 'Existencias', 'Mínimo', 'Costo', 'Valor en inventario'];
        $sql = "SELECT p.nombre, COALESCE(cp.nombre,'—') cat, p.stock, p.stock_minimo, p.costo, p.stock*p.costo valor FROM productos p LEFT JOIN categorias_propias cp ON cp.id=p.categoria_id WHERE p.negocio_id=? AND p.tipo='producto' AND p.activo=1"; $par = [$id];
        if ($cat !== '') { $sql .= ' AND p.categoria_id=?'; $par[] = (int)$cat; } $rows = qa($sql . ' ORDER BY p.nombre', $par); break;
    case 'productos':
        $cols = ['Producto', 'Unidades vendidas', 'Ingresos', 'Ganancia (precio-costo)'];
        $rows = qa('SELECT vi.nombre, SUM(vi.cantidad) u, SUM(vi.subtotal) ing, SUM((vi.precio-vi.costo)*vi.cantidad) gan FROM venta_items vi JOIN ventas v ON v.id=vi.venta_id WHERE v.negocio_id=? AND DATE(v.fecha) BETWEEN ? AND ? GROUP BY vi.nombre ORDER BY u DESC', [$id, $d1, $d2]); break;
    case 'pedidos':
        $cols = ['Referencia', 'Comprador', 'Solicitud', 'Entrega', 'Total', 'Estado'];
        $rows = qa('SELECT referencia, nombre_comprador, DATE(fecha_solicitud) f, fecha_entrega, total, estado FROM pedidos WHERE negocio_id=? AND DATE(fecha_solicitud) BETWEEN ? AND ? ORDER BY fecha_solicitud DESC', [$id, $d1, $d2]); break;
    case 'clientes':
        $cols = ['Cliente', 'Teléfono', 'Correo', 'N.º compras', 'Total comprado'];
        $rows = qa("SELECT CONCAT(c.nombres,' ',c.apellidos) n, c.telefono, c.email, COUNT(v.id) cnt, COALESCE(SUM(v.total),0) tot FROM clientes c LEFT JOIN ventas v ON v.cliente_id=c.id AND DATE(v.fecha) BETWEEN ? AND ? WHERE c.negocio_id=? GROUP BY c.id ORDER BY tot DESC", [$d1, $d2, $id]); break;
    case 'reservas':
        $cols = ['Servicio', 'Cliente', 'Fecha', 'Hora', 'Estado'];
        $rows = qa('SELECT s.nombre, r.nombre cl, r.fecha, r.hora, r.estado FROM reservas r JOIN productos s ON s.id=r.servicio_id WHERE r.negocio_id=? AND r.fecha BETWEEN ? AND ? ORDER BY r.fecha DESC', [$id, $d1, $d2]); break;
}
$rows = array_map(fn($r) => array_values($r), $rows);
if ($exp = get('export')) {
    if ($exp === 'excel') export_csv('reporte-' . $t . '-' . date('Ymd'), $cols, $rows);
    if ($exp === 'pdf') {
        $h = '<html><head><meta charset="UTF-8"><style>body{font-family:Arial;font-size:11px}table{width:100%;border-collapse:collapse}th,td{border:1px solid #ccc;padding:4px}th{background:#eee}</style></head><body><h2>' . e($n['nombre']) . ' — Reporte de ' . e($tipos[$t]) . '</h2><p>Periodo: ' . fdate($d1) . ' a ' . fdate($d2) . '</p><table><tr>';
        foreach ($cols as $c) $h .= '<th>' . e($c) . '</th>'; $h .= '</tr>';
        foreach ($rows as $r) { $h .= '<tr>'; foreach ($r as $v) $h .= '<td>' . e($v) . '</td>'; $h .= '</tr>'; }
        render_pdf($h . '</table></body></html>', 'reporte-' . $t . '.pdf');
    }
}
$title = 'Reportes'; $active = 'reportes'; $chart = true;
$catOpts = in_array($t, ['ingresos', 'gastos']) ? array_column(qa('SELECT DISTINCT categoria FROM movimientos_financieros WHERE negocio_id=?', [$id]), 'categoria') : ($t === 'inventario' ? qa('SELECT id, nombre FROM categorias_propias WHERE negocio_id=?', [$id]) : []);
// Gráfico: primera columna como etiqueta; última columna numérica relevante como valor
$gl = array_map(fn($r) => (string)$r[0], array_slice($rows, 0, 15)); $gv = array_map(fn($r) => (float)$r[count($r) - ($t === 'ventas' ? 2 : ($t === 'productos' ? 3 : 1))], array_slice($rows, 0, 15));
$showChart = in_array($t, ['ventas', 'ingresos', 'gastos', 'ganancias', 'productos', 'inventario', 'clientes']) && $rows;
$page_js = $showChart ? '<script>new Chart(document.getElementById("rc"),{type:"bar",data:{labels:' . json_encode($gl) . ',datasets:[{label:' . json_encode($tipos[$t]) . ',data:' . json_encode($gv) . ',backgroundColor:"#0f766e"}]}});</script>' : '';
include __DIR__ . '/../includes/panel_header.php';
$money_cols = ['Subtotal', 'Descuento', 'Total', 'Monto', 'Ingresos', 'Gastos', 'Ganancia estimada', 'Costo', 'Valor en inventario', 'Ganancia (precio-costo)', 'Total comprado'];
?>
<h3>Reportes y estadísticas</h3>
<form class="row g-2 mb-3 no-print">
  <div class="col-md-3"><select name="tipo" class="form-select"><?php foreach ($tipos as $k => $l): ?><option value="<?= $k ?>" <?= $t === $k ? 'selected' : '' ?>><?= $l ?></option><?php endforeach; ?></select></div>
  <div class="col-6 col-md-2"><input type="date" name="d1" class="form-control" value="<?= $d1 ?>"></div><div class="col-6 col-md-2"><input type="date" name="d2" class="form-control" value="<?= $d2 ?>"></div>
  <?php if ($catOpts): ?><div class="col-md-2"><select name="cat" class="form-select"><option value="">Todas las categorías</option><?php foreach ($catOpts as $c): $v = is_array($c) ? $c['id'] : $c; $l = is_array($c) ? $c['nombre'] : $c; ?><option value="<?= e($v) ?>" <?= (string)$cat === (string)$v ? 'selected' : '' ?>><?= e($l) ?></option><?php endforeach; ?></select></div><?php endif; ?>
  <div class="col-auto"><button class="btn btn-brand">Generar</button></div>
  <div class="col-auto ms-auto"><a class="btn btn-outline-danger" href="?<?= e(http_build_query(array_merge($_GET, ['export' => 'pdf']))) ?>"><i class="bi bi-file-earmark-pdf"></i> PDF</a>
    <a class="btn btn-outline-success" href="?<?= e(http_build_query(array_merge($_GET, ['export' => 'excel']))) ?>"><i class="bi bi-file-earmark-excel"></i> Excel</a></div>
</form>
<?php if ($showChart): ?><div class="card card-body mb-3"><canvas id="rc" height="80"></canvas></div><?php endif; ?>
<div class="table-responsive"><table class="table table-sm table-striped align-middle mb-0"><thead><tr><?php foreach ($cols as $c): ?><th><?= e($c) ?></th><?php endforeach; ?></tr></thead><tbody>
<?php foreach ($rows as $r): ?><tr><?php foreach ($r as $i => $v): ?><td><?= in_array($cols[$i], $money_cols) ? money($v) : e($v) ?></td><?php endforeach; ?></tr><?php endforeach; ?>
<?php if (!$rows): ?><tr><td colspan="<?= count($cols) ?>" class="text-center text-muted py-4">Sin datos para este filtro.</td></tr><?php endif; ?></tbody></table></div>
<?php include __DIR__ . '/../includes/panel_footer.php'; ?>
