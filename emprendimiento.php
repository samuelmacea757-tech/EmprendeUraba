<?php
/**
 * Página pública de un emprendimiento: información, contacto, horarios,
 * métodos de pago y catálogo, con el formulario para hacer pedidos.
 *
 * Parámetro GET: id (del emprendimiento). Filtros opcionales: q, cat.
 * Solo los compradores con sesión iniciada ven los campos de cantidad.
 */

require_once __DIR__ . '/includes/bootstrap.php';
require_once __DIR__ . '/includes/cards.php';

$negocio_id = (int) get('id');
$negocio = consultar_uno(
    'SELECT e.*, c.nombre AS categoria
     FROM emprendimientos e
     LEFT JOIN categorias c ON c.id = e.categoria_id
     WHERE e.id = ? AND e.activo = 1 AND e.suspendido = 0',
    [$negocio_id]
);

if (!$negocio) {
    http_response_code(404);
    exit('Emprendimiento no encontrado o no disponible.');
}

$title = $negocio['nombre'];
$brand_color = $negocio['color_primario'];
$texto_busqueda = get('q');
$categoria_id = (int) get('cat');

// Catálogo del negocio con filtros opcionales.
$sql = 'SELECT p.*, cp.nombre AS categoria_nombre
        FROM productos p
        LEFT JOIN categorias_propias cp ON cp.id = p.categoria_id
        WHERE p.negocio_id = ? AND p.activo = 1 AND p.publicado = 1';
$parametros = [$negocio_id];

if ($texto_busqueda !== '') {
    $sql .= ' AND (p.nombre LIKE ? OR p.descripcion LIKE ?)';
    array_push($parametros, "%$texto_busqueda%", "%$texto_busqueda%");
}
if ($categoria_id) {
    $sql .= ' AND p.categoria_id = ?';
    $parametros[] = $categoria_id;
}
$productos = consultar_todos($sql . ' ORDER BY p.tipo, p.nombre', $parametros);

$categorias_del_negocio = consultar_todos(
    'SELECT * FROM categorias_propias WHERE negocio_id = ? ORDER BY nombre',
    [$negocio_id]
);

$usuario = current_user();
$es_comprador = $usuario && $usuario['rol'] === 'comprador';

include __DIR__ . '/includes/header.php';
?>
<img class="cover-lg mb-0" src="<?= img($negocio['portada']) ?>" alt="Portada">
<div class="d-flex flex-column flex-md-row gap-3 px-3 mb-4">
  <img class="logo-lg rounded-circle" src="<?= img($negocio['logo'], 'assets/img/logo.svg') ?>" alt="Logo">
  <div class="pt-md-2">
    <h2 class="mb-0"><?= e($negocio['nombre']) ?></h2>
    <span class="badge bg-brand"><?= e($negocio['categoria'] ?? '') ?></span>
    <p class="mt-2 mb-0"><?= nl2br(e($negocio['descripcion'])) ?></p>
  </div>
</div>

<div class="row g-3 mb-4">
  <div class="col-md-6">
    <div class="card h-100"><div class="card-body">
      <h6><i class="bi bi-geo-alt text-brand"></i> Contacto y ubicación</h6>
      <div class="small"><?= e($negocio['direccion']) ?> <?= e($negocio['ciudad']) ?></div>
      <?php if ($negocio['telefono']): ?><div class="small"><i class="bi bi-telephone"></i> <?= e($negocio['telefono']) ?></div><?php endif; ?>
      <?php if ($negocio['email']): ?><div class="small"><i class="bi bi-envelope"></i> <?= e($negocio['email']) ?></div><?php endif; ?>
      <div class="mt-2 d-flex gap-2">
        <?php if ($negocio['whatsapp']): ?>
          <a class="btn btn-sm btn-success" target="_blank" rel="noopener" href="https://wa.me/<?= e(preg_replace('/\D/', '', $negocio['whatsapp'])) ?>"><i class="bi bi-whatsapp"></i> WhatsApp</a>
        <?php endif; ?>
        <?php if ($negocio['facebook']): ?>
          <a class="btn btn-sm btn-outline-primary" target="_blank" rel="noopener" href="<?= e($negocio['facebook']) ?>"><i class="bi bi-facebook"></i></a>
        <?php endif; ?>
        <?php if ($negocio['instagram']): ?>
          <a class="btn btn-sm btn-outline-danger" target="_blank" rel="noopener" href="<?= e($negocio['instagram']) ?>"><i class="bi bi-instagram"></i></a>
        <?php endif; ?>
      </div>
    </div></div>
  </div>
  <div class="col-md-6">
    <div class="card h-100"><div class="card-body">
      <h6><i class="bi bi-clock text-brand"></i> Horario de atención</h6>
      <div class="small"><?= nl2br(e($negocio['horario'] ?: 'No especificado')) ?></div>
      <h6 class="mt-3"><i class="bi bi-credit-card text-brand"></i> Métodos de pago</h6>
      <div class="small"><?= e($negocio['metodos_pago']) ?></div>
    </div></div>
  </div>
</div>

<form class="row g-2 mb-3">
  <input type="hidden" name="id" value="<?= $negocio_id ?>">
  <div class="col-8 col-md-5">
    <input class="form-control" name="q" value="<?= e($texto_busqueda) ?>" placeholder="Buscar en este catálogo…">
  </div>
  <div class="col-4 col-md-3">
    <select name="cat" class="form-select" onchange="this.form.submit()">
      <option value="">Categorías</option>
      <?php foreach ($categorias_del_negocio as $categoria): ?>
        <option value="<?= $categoria['id'] ?>" <?= $categoria_id == $categoria['id'] ? 'selected' : '' ?>><?= e($categoria['nombre']) ?></option>
      <?php endforeach; ?>
    </select>
  </div>
  <div class="col-12 col-md-2"><button class="btn btn-brand w-100">Buscar</button></div>
</form>

<!-- Un solo formulario envía todas las cantidades elegidas a pedido_crear.php -->
<form method="post" action="<?= url('pedido_crear.php') ?>">
  <?= csrf_field() ?>
  <input type="hidden" name="negocio_id" value="<?= $negocio_id ?>">

  <div class="row g-3">
    <?php foreach ($productos as $producto): ?>
      <?php $esta_agotado = producto_esta_agotado($producto); ?>
      <div class="col-6 col-md-4 col-lg-3">
        <div class="card h-100 shadow-sm">
          <a href="<?= url('producto.php?id=' . $producto['id']) ?>"><img src="<?= img($producto['foto']) ?>" class="card-img-top thumb" alt=""></a>
          <div class="card-body d-flex flex-column">
            <div class="small text-muted"><?= e($producto['categoria_nombre'] ?? '') ?></div>
            <h6><?= e($producto['nombre']) ?></h6>
            <p class="small text-muted mb-2"><?= e(mb_strimwidth((string) $producto['descripcion'], 0, 70, '…')) ?></p>
            <div class="mt-auto">
              <div class="d-flex justify-content-between mb-2">
                <strong><?= money($producto['precio']) ?></strong>
                <?php if ($producto['tipo'] === 'servicio'): ?>
                  <span class="badge text-bg-info">Servicio</span>
                <?php elseif ($esta_agotado): ?>
                  <span class="badge text-bg-secondary">Agotado</span>
                <?php else: ?>
                  <span class="badge text-bg-success">Disponible</span>
                <?php endif; ?>
              </div>
              <?php if ($producto['tipo'] === 'servicio'): ?>
                <a href="<?= url('reservar.php?id=' . $producto['id']) ?>" class="btn btn-sm btn-outline-primary w-100"><i class="bi bi-calendar-plus"></i> Reservar</a>
              <?php elseif (!$esta_agotado && $es_comprador): ?>
                <input type="number" min="0" max="<?= (int) $producto['stock'] ?>" value="0" name="qty[<?= $producto['id'] ?>]"
                       class="form-control form-control-sm qty mb-1" data-precio="<?= (float) $producto['precio'] ?>" placeholder="Cantidad">
                <input type="text" maxlength="200" name="obs[<?= $producto['id'] ?>]" class="form-control form-control-sm" placeholder="Observación (opcional)">
              <?php endif; ?>
            </div>
          </div>
        </div>
      </div>
    <?php endforeach; ?>
  </div>
  <?php if (!$productos): ?>
    <div class="alert alert-light border">Este emprendimiento aún no tiene productos publicados.</div>
  <?php endif; ?>

  <?php if ($es_comprador && $productos): ?>
    <div class="card mt-4"><div class="card-body">
      <h5>Tu pedido</h5>
      <div class="row g-2">
        <div class="col-md-4"><label class="form-label">Nombre</label><input class="form-control" name="nombre" required value="<?= e($usuario['nombre'] . ' ' . $usuario['apellido']) ?>"></div>
        <div class="col-md-4"><label class="form-label">Teléfono</label><input class="form-control" name="telefono" required value="<?= e($usuario['telefono']) ?>"></div>
        <div class="col-md-4"><label class="form-label">Fecha de entrega deseada</label><input type="date" class="form-control" name="fecha_entrega" min="<?= date('Y-m-d') ?>"></div>
        <div class="col-md-6"><label class="form-label">Dirección de entrega</label><input class="form-control" name="direccion"></div>
        <div class="col-md-6"><label class="form-label">Observaciones generales</label><input class="form-control" name="observaciones" maxlength="500"></div>
      </div>
      <div class="d-flex justify-content-between align-items-center mt-3">
        <h5 class="mb-0">Total: <span id="totalPedido">$0</span></h5>
        <button class="btn btn-brand btn-lg"><i class="bi bi-bag-check"></i> Enviar pedido</button>
      </div>
    </div></div>
  <?php elseif (!$usuario): ?>
    <div class="alert alert-info mt-4">
      Para hacer pedidos o reservas
      <a href="<?= url('auth/login.php?next=' . urlencode($_SERVER['REQUEST_URI'])) ?>">inicia sesión</a> o
      <a href="<?= url('auth/registro.php?rol=comprador') ?>">regístrate como comprador</a>.
    </div>
  <?php endif; ?>
</form>
<?php include __DIR__ . '/includes/footer.php'; ?>