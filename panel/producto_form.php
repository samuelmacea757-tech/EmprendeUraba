<?php
/**
 * Formulario para crear o editar un producto o servicio.
 *
 * Parámetro GET: id (si se está editando). Registra nombre, descripción,
 * precio, costo, fotos, categoría propia, existencias, disponibilidad,
 * características (talla, color, tamaño, material) y si es personalizado.
 * Si el negocio solo ofrece productos o solo servicios, el tipo se fija solo.
 */

require_once __DIR__ . '/../includes/bootstrap.php';

$negocio = require_negocio();
$negocio_id = $negocio['id'];

$producto = null;
if (get('id')) {
    // El filtro por negocio_id impide editar productos de otro emprendedor.
    $producto = consultar_uno('SELECT * FROM productos WHERE id=? AND negocio_id=?', [(int) get('id'), $negocio_id]);
    if (!$producto) {
        http_response_code(404);
        exit('No encontrado');
    }
}

$categorias = consultar_todos('SELECT * FROM categorias_propias WHERE negocio_id=? ORDER BY nombre', [$negocio_id]);

if (is_post()) {
    csrf_verify();

    try {
        // Tipo según lo que ofrece el negocio (ver Personalizar).
        $tipo = in_array(post('tipo'), ['producto', 'servicio']) ? post('tipo') : 'producto';
        if ($negocio['tipo_oferta'] === 'productos') {
            $tipo = 'producto';
        } elseif ($negocio['tipo_oferta'] === 'servicios') {
            $tipo = 'servicio';
        }

        if (mb_strlen(post('nombre')) < 2) {
            throw new RuntimeException('El nombre es obligatorio.');
        }
        $precio = (float) str_replace(',', '.', post('precio'));
        $costo = (float) str_replace(',', '.', post('costo'));
        if ($precio < 0 || $costo < 0) {
            throw new RuntimeException('Precio y costo no pueden ser negativos.');
        }

        // La categoría debe pertenecer a este negocio.
        $categoria_id = (int) post('categoria_id');
        if ($categoria_id && !consultar_valor('SELECT id FROM categorias_propias WHERE id=? AND negocio_id=?', [$categoria_id, $negocio_id])) {
            $categoria_id = 0;
        }

        $foto = upload_image('foto', $producto['foto'] ?? null);
        $stock = $tipo === 'producto' ? max(0, (int) post('stock')) : 0;

        $datos = [
            $tipo, post('nombre'), post('descripcion'), $precio, $costo, $foto, $categoria_id ?: null,
            (int) post('stock_minimo'), isset($_POST['disponible']) ? 1 : 0, isset($_POST['personalizado']) ? 1 : 0,
            post('talla') ?: null, post('color') ?: null, post('tamano') ?: null, post('material') ?: null,
            (int) post('duracion_min') ?: null, isset($_POST['publicado']) ? 1 : 0, isset($_POST['destacado']) ? 1 : 0,
        ];

        db()->beginTransaction();

        if ($producto) {
            consultar(
                'UPDATE productos
                 SET tipo=?, nombre=?, descripcion=?, precio=?, costo=?, foto=?, categoria_id=?, stock_minimo=?,
                     disponible=?, personalizado=?, talla=?, color=?, tamano=?, material=?, duracion_min=?,
                     publicado=?, destacado=?
                 WHERE id=? AND negocio_id=?',
                array_merge($datos, [$producto['id'], $negocio_id])
            );
            $producto_id = (int) $producto['id'];

            // Si cambió la cantidad, se registra como ajuste en el historial de inventario.
            if ($tipo === 'producto' && $stock !== (int) $producto['stock']) {
                mover_stock($negocio_id, $producto_id, 'ajuste', $stock, 'Ajuste desde la edición del producto');
            }
        } else {
            consultar(
                'INSERT INTO productos
                    (tipo, nombre, descripcion, precio, costo, foto, categoria_id, stock_minimo, disponible,
                     personalizado, talla, color, tamano, material, duracion_min, publicado, destacado, negocio_id)
                 VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)',
                array_merge($datos, [$negocio_id])
            );
            $producto_id = (int) db()->lastInsertId();

            if ($stock > 0) {
                mover_stock($negocio_id, $producto_id, 'entrada', $stock, 'Existencia inicial');
            }
        }

        // Fotografías adicionales (input múltiple "extras[]").
        if (!empty($_FILES['extras']['name'][0])) {
            foreach (array_keys($_FILES['extras']['name']) as $posicion) {
                $archivo = upload_image('extras', null, (string) $posicion);
                if ($archivo) {
                    consultar('INSERT INTO producto_fotos (producto_id, archivo) VALUES (?,?)', [$producto_id, $archivo]);
                }
            }
        }

        db()->commit();
        flash('success', 'Guardado correctamente.');
        redirect('panel/productos.php');
    } catch (RuntimeException $error) {
        if (db()->inTransaction()) {
            db()->rollBack();
        }
        flash('danger', $error->getMessage());
        redirect('panel/producto_form.php' . ($producto ? '?id=' . $producto['id'] : ''));
    }
}

$title = $producto ? 'Editar' : 'Nuevo producto o servicio';
$active = 'productos';

// Valores iniciales cuando es un producto nuevo.
$datos_formulario = $producto ?? [
    'tipo' => $negocio['tipo_oferta'] === 'servicios' ? 'servicio' : 'producto',
    'nombre' => '', 'descripcion' => '', 'precio' => '', 'costo' => '', 'foto' => null, 'categoria_id' => '',
    'stock' => 0, 'stock_minimo' => 0, 'disponible' => 1, 'personalizado' => 0,
    'talla' => '', 'color' => '', 'tamano' => '', 'material' => '', 'duracion_min' => '',
    'publicado' => 1, 'destacado' => 0,
];

include __DIR__ . '/../includes/panel_header.php';
?>
<h3><?= $title ?></h3>
<form method="post" enctype="multipart/form-data" class="card card-body">
  <?= csrf_field() ?>
  <div class="row g-3">
    <div class="col-md-3">
      <label class="form-label">Tipo</label>
      <select class="form-select" name="tipo" id="tipo" <?= $negocio['tipo_oferta'] !== 'ambos' ? 'disabled' : '' ?>>
        <option value="producto" <?= $datos_formulario['tipo'] === 'producto' ? 'selected' : '' ?>>Producto</option>
        <option value="servicio" <?= $datos_formulario['tipo'] === 'servicio' ? 'selected' : '' ?>>Servicio</option>
      </select>
      <?php if ($negocio['tipo_oferta'] !== 'ambos'): ?>
        <input type="hidden" name="tipo" value="<?= e($datos_formulario['tipo']) ?>">
      <?php endif; ?>
    </div>
    <div class="col-md-6">
      <label class="form-label">Nombre *</label>
      <input class="form-control" name="nombre" required value="<?= e($datos_formulario['nombre']) ?>">
    </div>
    <div class="col-md-3">
      <label class="form-label">Categoría propia</label>
      <select class="form-select" name="categoria_id">
        <option value="">Sin categoría</option>
        <?php foreach ($categorias as $categoria): ?>
          <option value="<?= $categoria['id'] ?>" <?= $datos_formulario['categoria_id'] == $categoria['id'] ? 'selected' : '' ?>><?= e($categoria['nombre']) ?></option>
        <?php endforeach; ?>
      </select>
      <div class="form-text"><a href="<?= url('panel/personalizar.php') ?>">Administrar categorías</a></div>
    </div>
    <div class="col-12">
      <label class="form-label">Descripción</label>
      <textarea class="form-control" name="descripcion" rows="3"><?= e($datos_formulario['descripcion']) ?></textarea>
    </div>
    <div class="col-md-3"><label class="form-label">Precio de venta *</label><input type="number" step="0.01" min="0" class="form-control" name="precio" required value="<?= e($datos_formulario['precio']) ?>"></div>
    <div class="col-md-3"><label class="form-label">Costo de adquisición/producción</label><input type="number" step="0.01" min="0" class="form-control" name="costo" value="<?= e($datos_formulario['costo']) ?>"></div>

    <!-- Los campos con data-solo se muestran u ocultan según el tipo (ver assets/js/app.js) -->
    <div class="col-md-3" data-solo="producto"><label class="form-label">Cantidad disponible</label><input type="number" min="0" class="form-control" name="stock" value="<?= (int) $datos_formulario['stock'] ?>"></div>
    <div class="col-md-3" data-solo="producto"><label class="form-label">Cantidad mínima (alerta)</label><input type="number" min="0" class="form-control" name="stock_minimo" value="<?= (int) $datos_formulario['stock_minimo'] ?>"></div>
    <div class="col-md-3" data-solo="servicio"><label class="form-label">Duración (minutos)</label><input type="number" min="0" class="form-control" name="duracion_min" value="<?= e($datos_formulario['duracion_min']) ?>"></div>
    <div class="col-6 col-md-3" data-solo="producto"><label class="form-label">Talla</label><input class="form-control" name="talla" value="<?= e($datos_formulario['talla']) ?>"></div>
    <div class="col-6 col-md-3" data-solo="producto"><label class="form-label">Color</label><input class="form-control" name="color" value="<?= e($datos_formulario['color']) ?>"></div>
    <div class="col-6 col-md-3" data-solo="producto"><label class="form-label">Tamaño</label><input class="form-control" name="tamano" value="<?= e($datos_formulario['tamano']) ?>"></div>
    <div class="col-6 col-md-3" data-solo="producto"><label class="form-label">Material</label><input class="form-control" name="material" value="<?= e($datos_formulario['material']) ?>"></div>

    <div class="col-md-6">
      <label class="form-label">Foto principal</label>
      <div class="d-flex gap-2">
        <img id="vista_foto" src="<?= img($datos_formulario['foto']) ?>" width="64" height="64" style="object-fit:cover" class="rounded border" alt="">
        <input type="file" class="form-control" name="foto" accept="image/*" data-preview="#vista_foto">
      </div>
    </div>
    <div class="col-md-6">
      <label class="form-label">Fotos adicionales</label>
      <input type="file" class="form-control" name="extras[]" accept="image/*" multiple>
    </div>

    <div class="col-12 d-flex flex-wrap gap-4">
      <div class="form-check form-switch"><input class="form-check-input" type="checkbox" name="disponible" id="chk_disponible" <?= $datos_formulario['disponible'] ? 'checked' : '' ?>><label class="form-check-label" for="chk_disponible">Disponible</label></div>
      <div class="form-check form-switch"><input class="form-check-input" type="checkbox" name="publicado" id="chk_publicado" <?= $datos_formulario['publicado'] ? 'checked' : '' ?>><label class="form-check-label" for="chk_publicado">Publicar en el catálogo</label></div>
      <div class="form-check form-switch"><input class="form-check-input" type="checkbox" name="destacado" id="chk_destacado" <?= $datos_formulario['destacado'] ? 'checked' : '' ?>><label class="form-check-label" for="chk_destacado">Destacado en la página principal</label></div>
      <div class="form-check form-switch"><input class="form-check-input" type="checkbox" name="personalizado" id="chk_personalizado" <?= $datos_formulario['personalizado'] ? 'checked' : '' ?>><label class="form-check-label" for="chk_personalizado">Producto personalizado (bajo pedido)</label></div>
    </div>
  </div>
  <div class="mt-3 d-flex gap-2">
    <button class="btn btn-brand">Guardar</button>
    <a class="btn btn-outline-secondary" href="<?= url('panel/productos.php') ?>">Cancelar</a>
  </div>
</form>
<?php include __DIR__ . '/../includes/panel_footer.php'; ?>