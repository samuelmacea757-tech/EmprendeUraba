<?php
/**
 * Página principal pública.
 *
 * Muestra la presentación de la plataforma, el buscador, las categorías de
 * negocios, los emprendimientos recientes, los productos destacados y los
 * datos de contacto. No requiere sesión.
 */

require_once __DIR__ . '/includes/bootstrap.php';
require_once __DIR__ . '/includes/cards.php';

$title = 'Inicio';

// Categorías con la cantidad de negocios visibles en cada una.
$categorias = consultar_todos(
    'SELECT c.*,
            (SELECT COUNT(*) FROM emprendimientos e
              WHERE e.categoria_id = c.id AND e.activo = 1 AND e.suspendido = 0) AS total
     FROM categorias c
     WHERE c.activo = 1
     ORDER BY c.nombre'
);

// Los 6 emprendimientos más recientes que estén activos y no suspendidos.
$negocios_recientes = consultar_todos(
    'SELECT e.*, c.nombre AS categoria
     FROM emprendimientos e
     LEFT JOIN categorias c ON c.id = e.categoria_id
     WHERE e.activo = 1 AND e.suspendido = 0
     ORDER BY e.creado_en DESC
     LIMIT 6'
);

// Productos publicados: primero los marcados como destacados.
$productos_destacados = consultar_todos(
    'SELECT p.*, e.nombre AS negocio_nombre
     FROM productos p
     JOIN emprendimientos e ON e.id = p.negocio_id
     WHERE p.activo = 1 AND p.publicado = 1 AND e.activo = 1 AND e.suspendido = 0
     ORDER BY p.destacado DESC, p.creado_en DESC
     LIMIT 8'
);

include __DIR__ . '/includes/header.php';
?>
<section class="hero mb-5 text-center">
  <img src="<?= url('assets/img/Logo_renovado.png') ?>" width="72" class="mb-3" alt="Logo_renovado.png">
  <h1 class="display-5 fw-bold"><?= APP_NAME ?></h1>
  <p class="lead mx-auto" style="max-width:700px"><?= e(param('descripcion')) ?></p>

  <form class="row g-2 justify-content-center mt-4" action="<?= url('catalogo.php') ?>">
    <div class="col-12 col-md-5">
      <input class="form-control form-control-lg" name="q" placeholder="Busca un negocio, producto o servicio…">
    </div>
    <div class="col-6 col-md-3">
      <select class="form-select form-select-lg" name="t">
        <option value="productos">Productos y servicios</option>
        <option value="emprendimientos">Emprendimientos</option>
      </select>
    </div>
    <div class="col-6 col-md-2">
      <button class="btn btn-warning btn-lg w-100"><i class="bi bi-search"></i> Buscar</button>
    </div>
  </form>

  <?php if (!current_user()): ?>
    <div class="mt-4">
      <a href="<?= url('auth/registro.php?rol=emprendedor') ?>" class="btn btn-outline-light">Quiero registrar mi emprendimiento</a>
    </div>
  <?php endif; ?>
</section>

<h4 class="mb-3">Categorías de negocios</h4>
<div class="row g-3 mb-5">
  <?php foreach ($categorias as $categoria): ?>
    <div class="col-6 col-md-3 col-lg-2">
      <a class="text-decoration-none text-reset" href="<?= url('catalogo.php?t=emprendimientos&cat=' . $categoria['id']) ?>">
        <div class="cat-pill">
          <i class="bi <?= e($categoria['icono']) ?>"></i>
          <div class="small fw-semibold mt-1"><?= e($categoria['nombre']) ?></div>
          <div class="small text-muted"><?= (int) $categoria['total'] ?> negocios</div>
        </div>
      </a>
    </div>
  <?php endforeach; ?>
</div>

<div class="d-flex justify-content-between align-items-end mb-3">
  <h4 class="mb-0">Emprendimientos registrados</h4>
  <a href="<?= url('catalogo.php?t=emprendimientos') ?>">Ver todos</a>
</div>
<div class="row g-3 mb-5">
  <?php
  foreach ($negocios_recientes as $negocio) {
      echo negocio_card($negocio);
  }
  if (!$negocios_recientes) {
      echo '<p class="text-muted">Aún no hay emprendimientos registrados. ¡Sé el primero!</p>';
  }
  ?>
</div>

<div class="d-flex justify-content-between align-items-end mb-3">
  <h4 class="mb-0">Productos y servicios destacados</h4>
  <a href="<?= url('catalogo.php') ?>">Ver todos</a>
</div>
<div class="row g-3 mb-5">
  <?php
  foreach ($productos_destacados as $producto) {
      echo product_card($producto);
  }
  if (!$productos_destacados) {
      echo '<p class="text-muted">Todavía no hay productos publicados.</p>';
  }
  ?>
</div>

<section id="contacto" class="card shadow-sm">
  <div class="card-body">
    <h4>Contacto</h4>
    <div class="row">
      <div class="col-md-4"><i class="bi bi-envelope text-brand"></i> <?= e(param('contacto_email')) ?></div>
      <div class="col-md-4"><i class="bi bi-telephone text-brand"></i> <?= e(param('contacto_telefono')) ?></div>
      <div class="col-md-4"><i class="bi bi-geo-alt text-brand"></i> <?= e(param('contacto_direccion')) ?></div>
    </div>
  </div>
</section>
<?php include __DIR__ . '/includes/footer.php'; ?>