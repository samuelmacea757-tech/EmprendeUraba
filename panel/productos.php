<?php
/**
 * Listado de productos y servicios del negocio.
 *
 * Permite buscar y filtrar, y cambiar con un clic si un ítem está publicado,
 * disponible o activo (desactivar es un "borrado lógico": no se elimina el
 * registro, así se conserva el historial de ventas).
 * Las acciones por enlace llevan token CSRF.
 */

require_once __DIR__ . '/../includes/bootstrap.php';

$negocio = require_negocio();
$negocio_id = $negocio['id'];

if (is_post() || get('accion')) {
    csrf_verify();

    $producto_id = (int) (post('id') ?: get('id'));
    // El filtro por negocio_id impide modificar productos de otro emprendedor.
    $producto = consultar_uno('SELECT * FROM productos WHERE id=? AND negocio_id=?', [$producto_id, $negocio_id]);

    if ($producto) {
        $accion = post('accion') ?: get('accion');
        if ($accion === 'toggle') {
            consultar('UPDATE productos SET activo=1-activo WHERE id=?', [$producto_id]);
        } elseif ($accion === 'publicar') {
            consultar('UPDATE productos SET publicado=1-publicado WHERE id=?', [$producto_id]);
        } elseif ($accion === 'disp') {
            consultar('UPDATE productos SET disponible=1-disponible WHERE id=?', [$producto_id]);
        }
        flash('success', 'Producto actualizado.');
    }
    redirect('panel/productos.php');
}

$title = 'Productos y servicios';
$active = 'productos';
$texto_busqueda = get('q');
$tipo = get('tipo');

$sql = 'SELECT p.*, cp.nombre AS categoria
        FROM productos p
        LEFT JOIN categorias_propias cp ON cp.id = p.categoria_id
        WHERE p.negocio_id = ?';
$parametros = [$negocio_id];

if ($texto_busqueda !== '') {
    $sql .= ' AND p.nombre LIKE ?';
    $parametros[] = "%$texto_busqueda%";
}
if (in_array($tipo, ['producto', 'servicio'])) {
    $sql .= ' AND p.tipo = ?';
    $parametros[] = $tipo;
}
$productos = consultar_todos($sql . ' ORDER BY p.activo DESC, p.nombre', $parametros);

include __DIR__ . '/../includes/panel_header.php';
?>
<div class="d-flex flex-wrap justify-content-between gap-2 mb-3">
  <h3 class="mb-0">Productos y servicios</h3>
  <a href="<?= url('panel/producto_form.php') ?>" class="btn btn-brand"><i class="bi bi-plus-lg"></i> Nuevo</a>
</div>

<form class="row g-2 mb-3">
  <div class="col-8 col-md-4"><input class="form-control" name="q" value="<?= e($texto_busqueda) ?>" placeholder="Buscar…"></div>
  <div class="col-4 col-md-2">
    <select class="form-select" name="tipo" onchange="this.form.submit()">
      <option value="">Todos</option>
      <option value="producto" <?= $tipo === 'producto' ? 'selected' : '' ?>>Productos</option>
      <option value="servicio" <?= $tipo === 'servicio' ? 'selected' : '' ?>>Servicios</option>
    </select>
  </div>
</form>

<div class="table-responsive">
  <table class="table align-middle table-hover mb-0">
    <thead><tr><th></th><th>Nombre</th><th>Tipo</th><th>Categoría</th><th>Precio</th><th>Costo</th><th>Stock</th><th>Estado</th><th></th></tr></thead>
    <tbody>
      <?php foreach ($productos as $producto): ?>
        <tr class="<?= $producto['activo'] ? '' : 'table-secondary' ?>">
          <td><img src="<?= img($producto['foto']) ?>" width="48" height="48" style="object-fit:cover" class="rounded" alt=""></td>
          <td><?= e($producto['nombre']) ?> <?= $producto['destacado'] ? '<i class="bi bi-star-fill text-warning"></i>' : '' ?></td>
          <td><?= ucfirst($producto['tipo']) ?></td>
          <td><?= e($producto['categoria'] ?? '—') ?></td>
          <td><?= money($producto['precio']) ?></td>
          <td><?= money($producto['costo']) ?></td>
          <td>
            <?php if ($producto['tipo'] === 'servicio'): ?>
              —
            <?php elseif ($producto['stock'] <= 0): ?>
              <span class="badge text-bg-danger">Agotado</span>
            <?php else: ?>
              <?= $producto['stock'] ?>
              <?= $producto['stock'] <= $producto['stock_minimo'] ? '<span class="badge text-bg-warning">bajo</span>' : '' ?>
            <?php endif; ?>
          </td>
          <td>
            <?php if (!$producto['activo']): ?>
              <span class="badge text-bg-secondary">Desactivado</span>
            <?php elseif ($producto['publicado']): ?>
              <span class="badge text-bg-success">Publicado</span>
            <?php else: ?>
              <span class="badge text-bg-warning">Oculto</span>
            <?php endif; ?>
            <?= $producto['disponible'] ? '' : '<span class="badge text-bg-dark">No disp.</span>' ?>
          </td>
          <td class="text-nowrap">
            <a class="btn btn-sm btn-outline-primary" href="<?= url('panel/producto_form.php?id=' . $producto['id']) ?>"><i class="bi bi-pencil"></i></a>
            <a class="btn btn-sm btn-outline-secondary" title="Publicar/ocultar en catálogo" href="<?= csrf_url('?accion=publicar&id=' . $producto['id']) ?>"><i class="bi bi-eye"></i></a>
            <a class="btn btn-sm btn-outline-secondary" title="Disponible/no disponible" href="<?= csrf_url('?accion=disp&id=' . $producto['id']) ?>"><i class="bi bi-toggle-on"></i></a>
            <a class="btn btn-sm btn-outline-danger" title="Desactivar/activar" data-confirm="¿Cambiar el estado activo de este ítem?" href="<?= csrf_url('?accion=toggle&id=' . $producto['id']) ?>"><i class="bi bi-trash"></i></a>
          </td>
        </tr>
      <?php endforeach; ?>
      <?php if (!$productos): ?>
        <tr><td colspan="9" class="text-center text-muted py-4">No hay registros.</td></tr>
      <?php endif; ?>
    </tbody>
  </table>
</div>
<?php include __DIR__ . '/../includes/panel_footer.php'; ?>