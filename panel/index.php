<?php
/**
 * Dashboard del emprendedor: resumen general de su negocio.
 *
 * Muestra ventas, ingresos y gastos del mes, ganancia estimada, existencias
 * bajas, pedidos pendientes, próximas entregas, últimas ventas y movimientos,
 * alertas y gráficos (Chart.js). Todos los datos pertenecen al negocio
 * autenticado.
 */

require_once __DIR__ . '/../includes/bootstrap.php';

/**
 * Tarjeta numérica del resumen.
 */
function tarjeta_estadistica(string $etiqueta, $valor, string $icono): string
{
    return '<div class="col-6 col-lg-3"><div class="card stat-card shadow-sm h-100"><div class="card-body">'
        . '<div class="small text-muted"><i class="bi ' . $icono . '"></i> ' . $etiqueta . '</div>'
        . '<div class="num">' . $valor . '</div></div></div></div>';
}

$negocio = require_negocio();
$negocio_id = $negocio['id'];
generar_alertas($negocio);

$title = 'Resumen';
$active = 'index';
$chart = true;
$inicio_de_mes = date('Y-m-01');

// --- Cifras principales ---
$total_ventas = (float) consultar_valor('SELECT COALESCE(SUM(total),0) FROM ventas WHERE negocio_id=?', [$negocio_id]);
$ingresos_del_mes = (float) consultar_valor(
    "SELECT COALESCE(SUM(monto),0) FROM movimientos_financieros WHERE negocio_id=? AND tipo='ingreso' AND fecha>=?",
    [$negocio_id, $inicio_de_mes]
);
$gastos_del_mes = (float) consultar_valor(
    "SELECT COALESCE(SUM(monto),0) FROM movimientos_financieros WHERE negocio_id=? AND tipo='gasto' AND fecha>=?",
    [$negocio_id, $inicio_de_mes]
);
$cantidad_productos = (int) consultar_valor('SELECT COUNT(*) FROM productos WHERE negocio_id=? AND activo=1', [$negocio_id]);

// --- Listados del resumen ---
$productos_bajos = consultar_todos(
    "SELECT nombre, stock, stock_minimo FROM productos
     WHERE negocio_id=? AND tipo='producto' AND activo=1 AND stock<=stock_minimo
     ORDER BY stock LIMIT 6",
    [$negocio_id]
);
$pedidos_pendientes = consultar_todos(
    "SELECT * FROM pedidos WHERE negocio_id=? AND estado='pendiente' ORDER BY fecha_solicitud DESC LIMIT 6",
    [$negocio_id]
);
$proximas_entregas = consultar_todos(
    "SELECT * FROM pedidos
     WHERE negocio_id=? AND estado IN ('confirmado','en_preparacion','listo') AND fecha_entrega>=CURDATE()
     ORDER BY fecha_entrega LIMIT 6",
    [$negocio_id]
);
$ultimas_ventas = consultar_todos(
    'SELECT v.*, c.nombres FROM ventas v LEFT JOIN clientes c ON c.id=v.cliente_id
     WHERE v.negocio_id=? ORDER BY v.fecha DESC LIMIT 6',
    [$negocio_id]
);
$ultimos_movimientos = consultar_todos(
    'SELECT * FROM movimientos_financieros WHERE negocio_id=? ORDER BY fecha DESC, id DESC LIMIT 6',
    [$negocio_id]
);
$notificaciones_nuevas = consultar_todos(
    'SELECT * FROM notificaciones WHERE usuario_id=? AND leida=0 ORDER BY creado_en DESC LIMIT 5',
    [$negocio['usuario_id']]
);

// --- Datos de los gráficos: ingresos y gastos de los últimos 6 meses ---
$etiquetas_meses = [];
$ingresos_por_mes = [];
$gastos_por_mes = [];
for ($mes_atras = 5; $mes_atras >= 0; $mes_atras--) {
    $mes = date('Y-m', strtotime("-$mes_atras month"));
    $etiquetas_meses[] = date('M y', strtotime("$mes-01"));
    $ingresos_por_mes[] = (float) consultar_valor(
        "SELECT COALESCE(SUM(monto),0) FROM movimientos_financieros
         WHERE negocio_id=? AND tipo='ingreso' AND DATE_FORMAT(fecha,'%Y-%m')=?",
        [$negocio_id, $mes]
    );
    $gastos_por_mes[] = (float) consultar_valor(
        "SELECT COALESCE(SUM(monto),0) FROM movimientos_financieros
         WHERE negocio_id=? AND tipo='gasto' AND DATE_FORMAT(fecha,'%Y-%m')=?",
        [$negocio_id, $mes]
    );
}
$mas_vendidos = consultar_todos(
    'SELECT vi.nombre, SUM(vi.cantidad) AS unidades
     FROM venta_items vi JOIN ventas v ON v.id=vi.venta_id
     WHERE v.negocio_id=? GROUP BY vi.nombre ORDER BY unidades DESC LIMIT 5',
    [$negocio_id]
);

$page_js = '<script>
new Chart(document.getElementById("grafico_ingresos_gastos"), {
  type: "bar",
  data: {
    labels: ' . json_encode($etiquetas_meses) . ',
    datasets: [
      {label: "Ingresos", data: ' . json_encode($ingresos_por_mes) . ', backgroundColor: "#0f766e"},
      {label: "Gastos", data: ' . json_encode($gastos_por_mes) . ', backgroundColor: "#f59e0b"}
    ]
  },
  options: {responsive: true}
});
new Chart(document.getElementById("grafico_mas_vendidos"), {
  type: "doughnut",
  data: {
    labels: ' . json_encode(array_column($mas_vendidos, 'nombre')) . ',
    datasets: [{data: ' . json_encode(array_map('intval', array_column($mas_vendidos, 'unidades'))) . '}]
  }
});
</script>';

include __DIR__ . '/../includes/panel_header.php';
?>
<h3 class="mb-1"><?= e($negocio['nombre']) ?></h3>
<p class="text-muted">
  Resumen general ·
  <?= !$negocio['activo'] ? '<span class="badge text-bg-secondary">Negocio inactivo</span>' : 'Negocio activo' ?>
</p>

<div class="row g-3 mb-4">
  <?= tarjeta_estadistica('Total de ventas', money($total_ventas), 'bi-cash-coin') ?>
  <?= tarjeta_estadistica('Ingresos del mes', money($ingresos_del_mes), 'bi-arrow-down-circle') ?>
  <?= tarjeta_estadistica('Gastos del mes', money($gastos_del_mes), 'bi-arrow-up-circle') ?>
  <?= tarjeta_estadistica('Ganancia estimada (mes)', money($ingresos_del_mes - $gastos_del_mes), 'bi-graph-up-arrow') ?>
  <?= tarjeta_estadistica('Productos registrados', $cantidad_productos, 'bi-box-seam') ?>
  <?= tarjeta_estadistica('Pocas existencias', count($productos_bajos), 'bi-exclamation-triangle') ?>
  <?= tarjeta_estadistica('Pedidos pendientes', count($pedidos_pendientes), 'bi-bag') ?>
  <?= tarjeta_estadistica('Próximas entregas', count($proximas_entregas), 'bi-truck') ?>
</div>

<div class="row g-3 mb-4">
  <div class="col-lg-8">
    <div class="card"><div class="card-body">
      <h6>Ingresos vs gastos (6 meses)</h6>
      <canvas id="grafico_ingresos_gastos" height="110"></canvas>
    </div></div>
  </div>
  <div class="col-lg-4">
    <div class="card h-100"><div class="card-body">
      <h6>Más vendidos</h6>
      <?php if ($mas_vendidos): ?>
        <canvas id="grafico_mas_vendidos"></canvas>
      <?php else: ?>
        <p class="text-muted small">Sin ventas aún.</p>
      <?php endif; ?>
    </div></div>
  </div>
</div>

<div class="row g-3">
  <div class="col-lg-6">
    <div class="card"><div class="card-header">Notificaciones y alertas</div>
      <ul class="list-group list-group-flush">
        <?php foreach ($notificaciones_nuevas as $notificacion): ?>
          <li class="list-group-item small"><?= e($notificacion['mensaje']) ?></li>
        <?php endforeach; ?>
        <?php if (!$notificaciones_nuevas): ?><li class="list-group-item text-muted small">Sin alertas nuevas.</li><?php endif; ?>
      </ul>
    </div>
  </div>

  <div class="col-lg-6">
    <div class="card"><div class="card-header">Productos con pocas existencias</div>
      <ul class="list-group list-group-flush">
        <?php foreach ($productos_bajos as $producto): ?>
          <li class="list-group-item d-flex justify-content-between small">
            <?= e($producto['nombre']) ?>
            <span class="badge text-bg-<?= $producto['stock'] <= 0 ? 'danger' : 'warning' ?>"><?= $producto['stock'] ?> / mín. <?= $producto['stock_minimo'] ?></span>
          </li>
        <?php endforeach; ?>
        <?php if (!$productos_bajos): ?><li class="list-group-item text-muted small">Todo en orden.</li><?php endif; ?>
      </ul>
    </div>
  </div>

  <div class="col-lg-6">
    <div class="card"><div class="card-header">Pedidos pendientes</div>
      <ul class="list-group list-group-flush">
        <?php foreach ($pedidos_pendientes as $pedido): ?>
          <li class="list-group-item d-flex justify-content-between small">
            <a href="<?= url('panel/pedido.php?id=' . $pedido['id']) ?>"><?= e($pedido['referencia']) ?> · <?= e($pedido['nombre_comprador']) ?></a>
            <span><?= money($pedido['total']) ?></span>
          </li>
        <?php endforeach; ?>
        <?php if (!$pedidos_pendientes): ?><li class="list-group-item text-muted small">Sin pedidos pendientes.</li><?php endif; ?>
      </ul>
    </div>
  </div>

  <div class="col-lg-6">
    <div class="card"><div class="card-header">Próximas entregas</div>
      <ul class="list-group list-group-flush">
        <?php foreach ($proximas_entregas as $pedido): ?>
          <li class="list-group-item d-flex justify-content-between small">
            <a href="<?= url('panel/pedido.php?id=' . $pedido['id']) ?>"><?= e($pedido['referencia']) ?> · <?= e($pedido['nombre_comprador']) ?></a>
            <span><?= fdate($pedido['fecha_entrega']) ?></span>
          </li>
        <?php endforeach; ?>
        <?php if (!$proximas_entregas): ?><li class="list-group-item text-muted small">Sin entregas programadas.</li><?php endif; ?>
      </ul>
    </div>
  </div>

  <div class="col-lg-6">
    <div class="card"><div class="card-header">Últimas ventas</div>
      <ul class="list-group list-group-flush">
        <?php foreach ($ultimas_ventas as $venta): ?>
          <li class="list-group-item d-flex justify-content-between small">
            <span><?= e($venta['referencia']) ?> · <?= e($venta['nombres'] ?? 'Cliente general') ?></span>
            <span><?= money($venta['total']) ?> <?= badge_estado($venta['estado_pago']) ?></span>
          </li>
        <?php endforeach; ?>
        <?php if (!$ultimas_ventas): ?><li class="list-group-item text-muted small">Sin ventas.</li><?php endif; ?>
      </ul>
    </div>
  </div>

  <div class="col-lg-6">
    <div class="card"><div class="card-header">Últimos movimientos financieros</div>
      <ul class="list-group list-group-flush">
        <?php foreach ($ultimos_movimientos as $movimiento): ?>
          <li class="list-group-item d-flex justify-content-between small">
            <span><?= e($movimiento['concepto']) ?></span>
            <span class="text-<?= $movimiento['tipo'] === 'ingreso' ? 'success' : 'danger' ?>">
              <?= $movimiento['tipo'] === 'ingreso' ? '+' : '-' ?><?= money($movimiento['monto']) ?>
            </span>
          </li>
        <?php endforeach; ?>
        <?php if (!$ultimos_movimientos): ?><li class="list-group-item text-muted small">Sin movimientos.</li><?php endif; ?>
      </ul>
    </div>
  </div>
</div>
<?php include __DIR__ . '/../includes/panel_footer.php'; ?>