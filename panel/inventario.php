<?php
/**
 * Gestión de inventario.
 *
 * Permite registrar entradas, salidas y ajustes de existencias (con motivo),
 * registrar compras de insumos (que generan un gasto y, si se asocian a un
 * producto, una entrada de inventario), ver el estado de las existencias y
 * consultar el historial de movimientos.
 */

require_once __DIR__ . '/../includes/bootstrap.php';

$negocio = require_negocio();
require_modulo($negocio, 'inventario');
$negocio_id = $negocio['id'];

if (is_post()) {
    csrf_verify();
    $accion = post('accion');

    try {
        db()->beginTransaction();

        if ($accion === 'mov') {
            // Movimiento manual de existencias.
            $producto_id = (int) post('producto_id');
            $cantidad = (int) post('cantidad');
            $tipo = post('tipo');

            $datos_validos = in_array($tipo, ['entrada', 'salida', 'ajuste'])
                && $cantidad >= 0
                && ($tipo === 'ajuste' || $cantidad >= 1)
                && post('motivo') !== '';
            if (!$datos_validos) {
                throw new RuntimeException('Completa tipo, cantidad y motivo.');
            }

            mover_stock($negocio_id, $producto_id, $tipo, $cantidad, post('motivo'));
            flash('success', $tipo === 'ajuste' ? 'Cantidad modificada.' : 'Movimiento registrado.');
        } elseif ($accion === 'compra') {
            // Compra de insumos: genera el gasto y, opcionalmente, la entrada de inventario.
            $costo_total = (float) post('costo_total');
            $cantidad = max(1, (int) post('cantidad'));
            $producto_id = (int) post('producto_id') ?: null;

            if (post('descripcion') === '' || $costo_total <= 0) {
                throw new RuntimeException('Descripción y costo son obligatorios.');
            }
            if ($producto_id && !consultar_valor('SELECT id FROM productos WHERE id=? AND negocio_id=?', [$producto_id, $negocio_id])) {
                $producto_id = null;
            }

            consultar(
                'INSERT INTO compras_insumos (negocio_id, producto_id, descripcion, proveedor, cantidad, costo_total, fecha)
                 VALUES (?,?,?,?,?,?,CURDATE())',
                [$negocio_id, $producto_id, post('descripcion'), post('proveedor'), $cantidad, $costo_total]
            );
            consultar(
                'INSERT INTO movimientos_financieros (negocio_id, tipo, categoria, concepto, monto, fecha)
                 VALUES (?,?,?,?,?,CURDATE())',
                [$negocio_id, 'gasto', 'Compra de insumos', post('descripcion'), $costo_total]
            );
            if ($producto_id) {
                mover_stock($negocio_id, $producto_id, 'entrada', $cantidad, 'Compra de insumos: ' . post('descripcion'));
            }
            flash('success', 'Compra registrada (se generó el gasto' . ($producto_id ? ' y la entrada de inventario' : '') . ').');
        }

        db()->commit();
    } catch (RuntimeException $error) {
        if (db()->inTransaction()) {
            db()->rollBack();
        }
        flash('danger', $error->getMessage());
    }
    redirect('panel/inventario.php');
}

$title = 'Inventario';
$active = 'inventario';
$productos = consultar_todos(
    "SELECT * FROM productos WHERE negocio_id=? AND tipo='producto' AND activo=1 ORDER BY nombre",
    [$negocio_id]
);
$historial = consultar_todos(
    'SELECT m.*, p.nombre
     FROM inventario_movimientos m
     JOIN productos p ON p.id = m.producto_id
     WHERE m.negocio_id = ?
     ORDER BY m.creado_en DESC, m.id DESC
     LIMIT 50',
    [$negocio_id]
);

include __DIR__ . '/../includes/panel_header.php';
?>
<h3>Inventario</h3>

<div class="row g-3 mb-4">
  <div class="col-lg-6">
    <div class="card"><div class="card-body">
      <h6>Registrar movimiento</h6>
      <form method="post" class="row g-2">
        <?= csrf_field() ?>
        <input type="hidden" name="accion" value="mov">
        <div class="col-12">
          <select class="form-select" name="producto_id" required>
            <option value="">Producto…</option>
            <?php foreach ($productos as $producto): ?>
              <option value="<?= $producto['id'] ?>"><?= e($producto['nombre']) ?> (stock <?= $producto['stock'] ?>)</option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="col-5">
          <select class="form-select" name="tipo">
            <option value="entrada">Entrada</option>
            <option value="salida">Salida</option>
            <option value="ajuste">Fijar cantidad exacta</option>
          </select>
        </div>
        <div class="col-3"><input type="number" min="0" class="form-control" name="cantidad" placeholder="Cant." required></div>
        <div class="col-12"><input class="form-control" name="motivo" placeholder="Motivo (obligatorio)" required></div>
        <div class="col-12"><button class="btn btn-brand">Registrar</button></div>
      </form>
    </div></div>
  </div>

  <div class="col-lg-6">
    <div class="card"><div class="card-body">
      <h6>Registrar compra de insumos</h6>
      <form method="post" class="row g-2">
        <?= csrf_field() ?>
        <input type="hidden" name="accion" value="compra">
        <div class="col-8"><input class="form-control" name="descripcion" placeholder="Descripción de la compra" required></div>
        <div class="col-4"><input class="form-control" name="proveedor" placeholder="Proveedor"></div>
        <div class="col-5">
          <select class="form-select" name="producto_id">
            <option value="">Sin producto asociado</option>
            <?php foreach ($productos as $producto): ?>
              <option value="<?= $producto['id'] ?>"><?= e($producto['nombre']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="col-3"><input type="number" min="1" class="form-control" name="cantidad" value="1"></div>
        <div class="col-4"><input type="number" min="0" step="0.01" class="form-control" name="costo_total" placeholder="Costo total" required></div>
        <div class="col-12"><button class="btn btn-outline-primary">Registrar compra</button></div>
      </form>
    </div></div>
  </div>
</div>

<h5>Existencias</h5>
<div class="table-responsive mb-4">
  <table class="table table-sm align-middle mb-0">
    <thead><tr><th>Producto</th><th>Existencias</th><th>Mínimo</th><th>Estado</th></tr></thead>
    <tbody>
      <?php foreach ($productos as $producto): ?>
        <tr>
          <td><?= e($producto['nombre']) ?></td>
          <td><?= $producto['stock'] ?></td>
          <td><?= $producto['stock_minimo'] ?></td>
          <td>
            <?php if ($producto['stock'] <= 0): ?>
              <span class="badge text-bg-danger">Agotado</span>
            <?php elseif ($producto['stock'] <= $producto['stock_minimo']): ?>
              <span class="badge text-bg-warning">Inventario bajo</span>
            <?php else: ?>
              <span class="badge text-bg-success">Disponible</span>
            <?php endif; ?>
          </td>
        </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
</div>

<h5>Historial de movimientos</h5>
<div class="table-responsive">
  <table class="table table-sm mb-0">
    <thead><tr><th>Fecha</th><th>Producto</th><th>Tipo</th><th>Cant.</th><th>Stock result.</th><th>Motivo</th></tr></thead>
    <tbody>
      <?php foreach ($historial as $movimiento): ?>
        <tr>
          <td><?= fdate($movimiento['creado_en'], true) ?></td>
          <td><?= e($movimiento['nombre']) ?></td>
          <td><?= ucfirst($movimiento['tipo']) ?></td>
          <td><?= $movimiento['cantidad'] ?></td>
          <td><?= $movimiento['stock_resultante'] ?></td>
          <td><?= e($movimiento['motivo']) ?></td>
        </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
</div>
<?php include __DIR__ . '/../includes/panel_footer.php'; ?>