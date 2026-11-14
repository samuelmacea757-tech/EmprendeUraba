<?php
/**
 * Detalle público de un producto o servicio.
 *
 * Muestra fotos (carrusel si hay varias), precio, disponibilidad y
 * características. Parámetro GET: id.
 */

require_once __DIR__ . '/includes/bootstrap.php';
require_once __DIR__ . '/includes/cards.php';

$producto = consultar_uno(
    'SELECT p.*, e.nombre AS negocio, e.color_primario, e.id AS negocio_id
     FROM productos p
     JOIN emprendimientos e ON e.id = p.negocio_id
     WHERE p.id = ? AND p.activo = 1 AND p.publicado = 1 AND e.activo = 1 AND e.suspendido = 0',
    [(int) get('id')]
);

if (!$producto) {
    http_response_code(404);
    exit('Producto no encontrado.');
}

$title = $producto['nombre'];
$brand_color = $producto['color_primario']; // el encabezado usa el color del negocio

// Foto principal primero, luego las fotos adicionales.
$fotos_adicionales = array_column(
    consultar_todos('SELECT archivo FROM producto_fotos WHERE producto_id = ?', [$producto['id']]),
    'archivo'
);
$fotos = array_merge($producto['foto'] ? [$producto['foto']] : [], $fotos_adicionales);

$esta_agotado = producto_esta_agotado($producto);
$caracteristicas = ['talla' => 'Talla', 'color' => 'Color', 'tamano' => 'Tamaño', 'material' => 'Material'];

include __DIR__ . '/includes/header.php';
?>
<a href="<?= url('emprendimiento.php?id=' . $producto['negocio_id']) ?>" class="small">&larr; <?= e($producto['negocio']) ?></a>

<div class="row g-4 mt-1">
  <div class="col-md-6">
    <?php if (count($fotos) > 1): ?>
      <div id="carrusel_fotos" class="carousel slide">
        <div class="carousel-inner">
          <?php foreach ($fotos as $posicion => $foto): ?>
            <div class="carousel-item <?= $posicion ? '' : 'active' ?>">
              <img src="<?= img($foto) ?>" class="d-block w-100 rounded" alt="">
            </div>
          <?php endforeach; ?>
        </div>
        <button class="carousel-control-prev" data-bs-target="#carrusel_fotos" data-bs-slide="prev"><span class="carousel-control-prev-icon"></span></button>
        <button class="carousel-control-next" data-bs-target="#carrusel_fotos" data-bs-slide="next"><span class="carousel-control-next-icon"></span></button>
      </div>
    <?php else: ?>
      <img src="<?= img($fotos[0] ?? null) ?>" class="img-fluid rounded w-100" alt="">
    <?php endif; ?>
  </div>

  <div class="col-md-6">
    <h2><?= e($producto['nombre']) ?></h2>
    <h3 class="text-brand"><?= money($producto['precio']) ?></h3>
    <p>
      <?php if ($producto['tipo'] === 'servicio'): ?>
        <span class="badge text-bg-info">Servicio</span>
      <?php elseif ($esta_agotado): ?>
        <span class="badge text-bg-secondary">Agotado</span>
      <?php else: ?>
        <span class="badge text-bg-success">Disponible</span>
      <?php endif; ?>
      <?php if ($producto['personalizado']): ?><span class="badge text-bg-warning">Personalizable</span><?php endif; ?>
    </p>
    <p><?= nl2br(e($producto['descripcion'])) ?></p>

    <ul class="list-unstyled small">
      <?php foreach ($caracteristicas as $campo => $etiqueta): ?>
        <?php if ($producto[$campo]): ?>
          <li><strong><?= $etiqueta ?>:</strong> <?= e($producto[$campo]) ?></li>
        <?php endif; ?>
      <?php endforeach; ?>
      <?php if ($producto['duracion_min']): ?>
        <li><strong>Duración:</strong> <?= (int) $producto['duracion_min'] ?> min</li>
      <?php endif; ?>
    </ul>

    <?php if ($producto['tipo'] === 'servicio'): ?>
      <a class="btn btn-brand" href="<?= url('reservar.php?id=' . $producto['id']) ?>"><i class="bi bi-calendar-plus"></i> Reservar</a>
    <?php else: ?>
      <a class="btn btn-brand" href="<?= url('emprendimiento.php?id=' . $producto['negocio_id']) ?>"><i class="bi bi-bag"></i> Pedir en el catálogo</a>
    <?php endif; ?>
  </div>
</div>
<?php include __DIR__ . '/includes/footer.php'; ?>