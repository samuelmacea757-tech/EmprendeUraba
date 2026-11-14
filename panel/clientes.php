<?php
/**
 * Gestión de clientes del negocio.
 *
 * Permite registrar, editar y buscar clientes, y consultar el historial de
 * compras y pedidos de cada uno (vista "ver"). Parámetros GET: q (búsqueda),
 * edit (id a editar), ver (id del historial).
 */

require_once __DIR__ . '/../includes/bootstrap.php';

$negocio = require_negocio();
require_modulo($negocio, 'clientes');
$negocio_id = $negocio['id'];

if (is_post()) {
    csrf_verify();

    if (post('nombres') === '') {
        flash('danger', 'El nombre es obligatorio.');
    } elseif (post('email') !== '' && !filter_var(post('email'), FILTER_VALIDATE_EMAIL)) {
        flash('danger', 'Correo inválido.');
    } else {
        $datos = [post('nombres'), post('apellidos'), post('telefono'), post('email'), post('direccion'), post('observaciones')];
        $cliente_id = (int) post('id');

        if ($cliente_id) {
            consultar(
                'UPDATE clientes SET nombres=?, apellidos=?, telefono=?, email=?, direccion=?, observaciones=?
                 WHERE id=? AND negocio_id=?',
                array_merge($datos, [$cliente_id, $negocio_id])
            );
            flash('success', 'Cliente actualizado.');
        } else {
            consultar(
                'INSERT INTO clientes (nombres, apellidos, telefono, email, direccion, observaciones, negocio_id)
                 VALUES (?,?,?,?,?,?,?)',
                array_merge($datos, [$negocio_id])
            );
            flash('success', 'Cliente registrado.');
        }
    }
    redirect('panel/clientes.php');
}

$title = 'Clientes';
$active = 'clientes';
$texto_busqueda = get('q');

$cliente_en_edicion = get('edit')
    ? consultar_uno('SELECT * FROM clientes WHERE id=? AND negocio_id=?', [(int) get('edit'), $negocio_id])
    : null;
$cliente_en_historial = get('ver')
    ? consultar_uno('SELECT * FROM clientes WHERE id=? AND negocio_id=?', [(int) get('ver'), $negocio_id])
    : null;

$sql = 'SELECT c.*,
               (SELECT COUNT(*) FROM ventas v WHERE v.cliente_id = c.id) AS compras,
               (SELECT COALESCE(SUM(total),0) FROM ventas v WHERE v.cliente_id = c.id) AS total
        FROM clientes c
        WHERE c.negocio_id = ?';
$parametros = [$negocio_id];
if ($texto_busqueda !== '') {
    $sql .= ' AND (c.nombres LIKE ? OR c.apellidos LIKE ? OR c.telefono LIKE ? OR c.email LIKE ?)';
    array_push($parametros, "%$texto_busqueda%", "%$texto_busqueda%", "%$texto_busqueda%", "%$texto_busqueda%");
}
$clientes = consultar_todos($sql . ' ORDER BY c.nombres LIMIT 200', $parametros);

include __DIR__ . '/../includes/panel_header.php';
?>
<h3>Clientes</h3>

<?php if ($cliente_en_historial): ?>
  <?php
  $ventas_del_cliente = consultar_todos(
      'SELECT * FROM ventas WHERE cliente_id=? AND negocio_id=? ORDER BY fecha DESC',
      [$cliente_en_historial['id'], $negocio_id]
  );
  $pedidos_del_cliente = consultar_todos(
      'SELECT * FROM pedidos WHERE cliente_id=? AND negocio_id=? ORDER BY fecha_solicitud DESC',
      [$cliente_en_historial['id'], $negocio_id]
  );
  ?>
  <div class="card mb-4"><div class="card-body">
    <a href="<?= url('panel/clientes.php') ?>" class="small">&larr; Volver</a>
    <h5 class="mt-2"><?= e($cliente_en_historial['nombres'] . ' ' . $cliente_en_historial['apellidos']) ?></h5>
    <div class="small text-muted"><?= e($cliente_en_historial['telefono']) ?> · <?= e($cliente_en_historial['email']) ?> · <?= e($cliente_en_historial['direccion']) ?></div>
    <?php if ($cliente_en_historial['observaciones']): ?>
      <div class="alert alert-light border mt-2 mb-0 small"><?= nl2br(e($cliente_en_historial['observaciones'])) ?></div>
    <?php endif; ?>
  </div></div>

  <div class="row g-3">
    <div class="col-md-6">
      <h6>Historial de compras</h6>
      <ul class="list-group">
        <?php foreach ($ventas_del_cliente as $venta): ?>
          <li class="list-group-item d-flex justify-content-between small">
            <a href="<?= url('comprobante.php?id=' . $venta['id']) ?>"><?= e($venta['referencia']) ?> · <?= fdate($venta['fecha']) ?></a>
            <span><?= money($venta['total']) ?></span>
          </li>
        <?php endforeach; ?>
        <?php if (!$ventas_del_cliente): ?><li class="list-group-item text-muted small">Sin compras.</li><?php endif; ?>
      </ul>
    </div>
    <div class="col-md-6">
      <h6>Pedidos anteriores</h6>
      <ul class="list-group">
        <?php foreach ($pedidos_del_cliente as $pedido): ?>
          <li class="list-group-item d-flex justify-content-between small">
            <a href="<?= url('panel/pedido.php?id=' . $pedido['id']) ?>"><?= e($pedido['referencia']) ?></a>
            <?= badge_estado($pedido['estado']) ?>
          </li>
        <?php endforeach; ?>
        <?php if (!$pedidos_del_cliente): ?><li class="list-group-item text-muted small">Sin pedidos.</li><?php endif; ?>
      </ul>
    </div>
  </div>

<?php else: ?>
  <div class="card card-body mb-3">
    <form method="post" class="row g-2">
      <?= csrf_field() ?>
      <input type="hidden" name="id" value="<?= $cliente_en_edicion['id'] ?? 0 ?>">
      <div class="col-md-3"><input class="form-control" name="nombres" placeholder="Nombres *" required value="<?= e($cliente_en_edicion['nombres'] ?? '') ?>"></div>
      <div class="col-md-3"><input class="form-control" name="apellidos" placeholder="Apellidos" value="<?= e($cliente_en_edicion['apellidos'] ?? '') ?>"></div>
      <div class="col-md-3"><input class="form-control" name="telefono" placeholder="Teléfono" value="<?= e($cliente_en_edicion['telefono'] ?? '') ?>"></div>
      <div class="col-md-3"><input class="form-control" name="email" placeholder="Correo" value="<?= e($cliente_en_edicion['email'] ?? '') ?>"></div>
      <div class="col-md-5"><input class="form-control" name="direccion" placeholder="Dirección" value="<?= e($cliente_en_edicion['direccion'] ?? '') ?>"></div>
      <div class="col-md-5"><input class="form-control" name="observaciones" placeholder="Observaciones" value="<?= e($cliente_en_edicion['observaciones'] ?? '') ?>"></div>
      <div class="col-md-2"><button class="btn btn-brand w-100"><?= $cliente_en_edicion ? 'Actualizar' : 'Registrar' ?></button></div>
    </form>
  </div>

  <form class="row g-2 mb-3">
    <div class="col-8 col-md-4"><input class="form-control" name="q" value="<?= e($texto_busqueda) ?>" placeholder="Buscar cliente…"></div>
    <div class="col-4"><button class="btn btn-outline-secondary">Buscar</button></div>
  </form>

  <div class="table-responsive">
    <table class="table table-hover align-middle mb-0">
      <thead><tr><th>Nombre</th><th>Teléfono</th><th>Correo</th><th>Compras</th><th>Total</th><th></th></tr></thead>
      <tbody>
        <?php foreach ($clientes as $cliente): ?>
          <tr>
            <td><?= e($cliente['nombres'] . ' ' . $cliente['apellidos']) ?></td>
            <td><?= e($cliente['telefono']) ?></td>
            <td><?= e($cliente['email']) ?></td>
            <td><?= $cliente['compras'] ?></td>
            <td><?= money($cliente['total']) ?></td>
            <td>
              <a class="btn btn-sm btn-outline-secondary" href="?ver=<?= $cliente['id'] ?>">Historial</a>
              <a class="btn btn-sm btn-outline-primary" href="?edit=<?= $cliente['id'] ?>"><i class="bi bi-pencil"></i></a>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
<?php endif; ?>
<?php include __DIR__ . '/../includes/panel_footer.php'; ?>