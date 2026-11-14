<?php
/**
 * Catálogo general público: búsqueda de emprendimientos y de productos o
 * servicios, con filtros por categoría y por tipo.
 *
 * Parámetros GET: t (productos|emprendimientos), q (texto), cat (id de
 * categoría), tipo (producto|servicio).
 */

require_once __DIR__ . '/includes/bootstrap.php';
require_once __DIR__ . '/includes/cards.php';

$title = 'Catálogo';

$vista = get('t', 'productos');
$texto_busqueda = get('q');
$categoria_id = (int) get('cat');
$tipo_producto = get('tipo');
$buscando_negocios = $vista === 'emprendimientos';

$categorias = consultar_todos('SELECT id, nombre FROM categorias WHERE activo=1 ORDER BY nombre');

// Se arma la consulta con filtros opcionales; los valores siempre van como
// parámetros preparados.
$parametros = [];

if ($buscando_negocios) {
    $sql = 'SELECT e.*, c.nombre AS categoria
            FROM emprendimientos e
            LEFT JOIN categorias c ON c.id = e.categoria_id
            WHERE e.activo = 1 AND e.suspendido = 0';

    if ($texto_busqueda !== '') {
        $sql .= ' AND (e.nombre LIKE ? OR e.descripcion LIKE ?)';
        array_push($parametros, "%$texto_busqueda%", "%$texto_busqueda%");
    }
    if ($categoria_id) {
        $sql .= ' AND e.categoria_id = ?';
        $parametros[] = $categoria_id;
    }
    $sql .= ' ORDER BY e.nombre LIMIT 60';
} else {
    $sql = 'SELECT p.*, e.nombre AS negocio_nombre
            FROM productos p
            JOIN emprendimientos e ON e.id = p.negocio_id
            WHERE p.activo = 1 AND p.publicado = 1 AND e.activo = 1 AND e.suspendido = 0';

    if ($texto_busqueda !== '') {
        $sql .= ' AND (p.nombre LIKE ? OR p.descripcion LIKE ?)';
        array_push($parametros, "%$texto_busqueda%", "%$texto_busqueda%");
    }
    if ($categoria_id) {
        $sql .= ' AND e.categoria_id = ?';
        $parametros[] = $categoria_id;
    }
    if (in_array($tipo_producto, ['producto', 'servicio'], true)) {
        $sql .= ' AND p.tipo = ?';
        $parametros[] = $tipo_producto;
    }
    $sql .= ' ORDER BY p.destacado DESC, p.nombre LIMIT 80';
}

$resultados = consultar_todos($sql, $parametros);

include __DIR__ . '/includes/header.php';
?>
<h3 class="mb-3">Explorar <?= $buscando_negocios ? 'emprendimientos' : 'productos y servicios' ?></h3>

<form class="row g-2 mb-4">
  <div class="col-12 col-md-4">
    <input class="form-control" name="q" value="<?= e($texto_busqueda) ?>" placeholder="Buscar…">
  </div>
  <div class="col-6 col-md-2">
    <select name="t" class="form-select">
      <option value="productos" <?= !$buscando_negocios ? 'selected' : '' ?>>Productos/servicios</option>
      <option value="emprendimientos" <?= $buscando_negocios ? 'selected' : '' ?>>Emprendimientos</option>
    </select>
  </div>
  <div class="col-6 col-md-3">
    <select name="cat" class="form-select">
      <option value="">Todas las categorías</option>
      <?php foreach ($categorias as $categoria): ?>
        <option value="<?= $categoria['id'] ?>" <?= $categoria_id == $categoria['id'] ? 'selected' : '' ?>><?= e($categoria['nombre']) ?></option>
      <?php endforeach; ?>
    </select>
  </div>
  <?php if (!$buscando_negocios): ?>
    <div class="col-6 col-md-2">
      <select name="tipo" class="form-select">
        <option value="">Todos</option>
        <option value="producto" <?= $tipo_producto === 'producto' ? 'selected' : '' ?>>Productos</option>
        <option value="servicio" <?= $tipo_producto === 'servicio' ? 'selected' : '' ?>>Servicios</option>
      </select>
    </div>
  <?php endif; ?>
  <div class="col-6 col-md-1">
    <button class="btn btn-brand w-100"><i class="bi bi-search"></i></button>
  </div>
</form>

<div class="row g-3">
  <?php
  foreach ($resultados as $fila) {
      echo $buscando_negocios ? negocio_card($fila) : product_card($fila);
  }
  ?>
</div>
<?php if (!$resultados): ?>
  <div class="alert alert-light border">No se encontraron resultados.</div>
<?php endif; ?>
<?php include __DIR__ . '/includes/footer.php'; ?>