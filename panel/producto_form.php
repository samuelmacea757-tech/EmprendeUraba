<?php
require_once __DIR__ . '/../includes/bootstrap.php';
$n = require_negocio(); $nid = $n['id'];
$p = null;
if (get('id')) { $p = q1('SELECT * FROM productos WHERE id=? AND negocio_id=?', [(int)get('id'), $nid]); if (!$p) { http_response_code(404); exit('No encontrado'); } }
$cats = qa('SELECT * FROM categorias_propias WHERE negocio_id=? ORDER BY nombre', [$nid]);
if (is_post()) {
    csrf_verify();
    try {
        $tipo = in_array(post('tipo'), ['producto', 'servicio']) ? post('tipo') : 'producto';
        if ($n['tipo_oferta'] === 'productos') $tipo = 'producto'; elseif ($n['tipo_oferta'] === 'servicios') $tipo = 'servicio';
        if (mb_strlen(post('nombre')) < 2) throw new RuntimeException('El nombre es obligatorio.');
        $precio = (float)str_replace(',', '.', post('precio')); $costo = (float)str_replace(',', '.', post('costo'));
        if ($precio < 0 || $costo < 0) throw new RuntimeException('Precio y costo no pueden ser negativos.');
        $catId = (int)post('categoria_id'); if ($catId && !qv('SELECT id FROM categorias_propias WHERE id=? AND negocio_id=?', [$catId, $nid])) $catId = 0;
        $foto = upload_image('foto', $p['foto'] ?? null);
        $stock = $tipo === 'producto' ? max(0, (int)post('stock')) : 0;
        $v = [$tipo, post('nombre'), post('descripcion'), $precio, $costo, $foto, $catId ?: null, (int)post('stock_minimo'), isset($_POST['disponible']) ? 1 : 0, isset($_POST['personalizado']) ? 1 : 0,
              post('talla') ?: null, post('color') ?: null, post('tamano') ?: null, post('material') ?: null, (int)post('duracion_min') ?: null, isset($_POST['publicado']) ? 1 : 0, isset($_POST['destacado']) ? 1 : 0];
        db()->beginTransaction();
        if ($p) {
            q('UPDATE productos SET tipo=?, nombre=?, descripcion=?, precio=?, costo=?, foto=?, categoria_id=?, stock_minimo=?, disponible=?, personalizado=?, talla=?, color=?, tamano=?, material=?, duracion_min=?, publicado=?, destacado=? WHERE id=? AND negocio_id=?', array_merge($v, [$p['id'], $nid]));
            $pid = (int)$p['id'];
            if ($tipo === 'producto' && $stock !== (int)$p['stock']) mover_stock($nid, $pid, 'ajuste', $stock, 'Ajuste desde la edición del producto');
        } else {
            q('INSERT INTO productos (tipo, nombre, descripcion, precio, costo, foto, categoria_id, stock_minimo, disponible, personalizado, talla, color, tamano, material, duracion_min, publicado, destacado, negocio_id) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)', array_merge($v, [$nid]));
            $pid = (int)db()->lastInsertId();
            if ($stock > 0) mover_stock($nid, $pid, 'entrada', $stock, 'Existencia inicial');
        }
        // Fotografías adicionales
        if (!empty($_FILES['extras']['name'][0])) foreach (array_keys($_FILES['extras']['name']) as $i) { $f = upload_image('extras', null, (string)$i); if ($f) q('INSERT INTO producto_fotos (producto_id, archivo) VALUES (?,?)', [$pid, $f]); }
        db()->commit();
        flash('success', 'Guardado correctamente.'); redirect('panel/productos.php');
    } catch (RuntimeException $e) { if (db()->inTransaction()) db()->rollBack(); flash('danger', $e->getMessage()); redirect('panel/producto_form.php' . ($p ? '?id=' . $p['id'] : '')); }
}
$title = $p ? 'Editar' : 'Nuevo producto o servicio'; $active = 'productos';
$d = $p ?? ['tipo' => $n['tipo_oferta'] === 'servicios' ? 'servicio' : 'producto', 'nombre' => '', 'descripcion' => '', 'precio' => '', 'costo' => '', 'foto' => null, 'categoria_id' => '', 'stock' => 0, 'stock_minimo' => 0, 'disponible' => 1,
            'personalizado' => 0, 'talla' => '', 'color' => '', 'tamano' => '', 'material' => '', 'duracion_min' => '', 'publicado' => 1, 'destacado' => 0];
include __DIR__ . '/../includes/panel_header.php';
?>
<h3><?= $title ?></h3>
<form method="post" enctype="multipart/form-data" class="card card-body"><?= csrf_field() ?>
<div class="row g-3">
  <div class="col-md-3"><label class="form-label">Tipo</label><select class="form-select" name="tipo" id="tipo" <?= $n['tipo_oferta'] !== 'ambos' ? 'disabled' : '' ?>><option value="producto" <?= $d['tipo'] === 'producto' ? 'selected' : '' ?>>Producto</option><option value="servicio" <?= $d['tipo'] === 'servicio' ? 'selected' : '' ?>>Servicio</option></select>
    <?php if ($n['tipo_oferta'] !== 'ambos'): ?><input type="hidden" name="tipo" value="<?= e($d['tipo']) ?>"><?php endif; ?></div>
  <div class="col-md-6"><label class="form-label">Nombre *</label><input class="form-control" name="nombre" required value="<?= e($d['nombre']) ?>"></div>
  <div class="col-md-3"><label class="form-label">Categoría propia</label><select class="form-select" name="categoria_id"><option value="">Sin categoría</option><?php foreach ($cats as $c): ?><option value="<?= $c['id'] ?>" <?= $d['categoria_id'] == $c['id'] ? 'selected' : '' ?>><?= e($c['nombre']) ?></option><?php endforeach; ?></select><div class="form-text"><a href="<?= url('panel/personalizar.php') ?>">Administrar categorías</a></div></div>
  <div class="col-12"><label class="form-label">Descripción</label><textarea class="form-control" name="descripcion" rows="3"><?= e($d['descripcion']) ?></textarea></div>
  <div class="col-md-3"><label class="form-label">Precio de venta *</label><input type="number" step="0.01" min="0" class="form-control" name="precio" required value="<?= e($d['precio']) ?>"></div>
  <div class="col-md-3"><label class="form-label">Costo de adquisición/producción</label><input type="number" step="0.01" min="0" class="form-control" name="costo" value="<?= e($d['costo']) ?>"></div>
  <div class="col-md-3" data-solo="producto"><label class="form-label">Cantidad disponible</label><input type="number" min="0" class="form-control" name="stock" value="<?= (int)$d['stock'] ?>"></div>
  <div class="col-md-3" data-solo="producto"><label class="form-label">Cantidad mínima (alerta)</label><input type="number" min="0" class="form-control" name="stock_minimo" value="<?= (int)$d['stock_minimo'] ?>"></div>
  <div class="col-md-3" data-solo="servicio"><label class="form-label">Duración (minutos)</label><input type="number" min="0" class="form-control" name="duracion_min" value="<?= e($d['duracion_min']) ?>"></div>
  <div class="col-6 col-md-3" data-solo="producto"><label class="form-label">Talla</label><input class="form-control" name="talla" value="<?= e($d['talla']) ?>"></div>
  <div class="col-6 col-md-3" data-solo="producto"><label class="form-label">Color</label><input class="form-control" name="color" value="<?= e($d['color']) ?>"></div>
  <div class="col-6 col-md-3" data-solo="producto"><label class="form-label">Tamaño</label><input class="form-control" name="tamano" value="<?= e($d['tamano']) ?>"></div>
  <div class="col-6 col-md-3" data-solo="producto"><label class="form-label">Material</label><input class="form-control" name="material" value="<?= e($d['material']) ?>"></div>
  <div class="col-md-6"><label class="form-label">Foto principal</label><div class="d-flex gap-2"><img id="pf" src="<?= img($d['foto']) ?>" width="64" height="64" style="object-fit:cover" class="rounded border"><input type="file" class="form-control" name="foto" accept="image/*" data-preview="#pf"></div></div>
  <div class="col-md-6"><label class="form-label">Fotos adicionales</label><input type="file" class="form-control" name="extras[]" accept="image/*" multiple></div>
  <div class="col-12 d-flex flex-wrap gap-4">
    <div class="form-check form-switch"><input class="form-check-input" type="checkbox" name="disponible" id="d1" <?= $d['disponible'] ? 'checked' : '' ?>><label class="form-check-label" for="d1">Disponible</label></div>
    <div class="form-check form-switch"><input class="form-check-input" type="checkbox" name="publicado" id="d2" <?= $d['publicado'] ? 'checked' : '' ?>><label class="form-check-label" for="d2">Publicar en el catálogo</label></div>
    <div class="form-check form-switch"><input class="form-check-input" type="checkbox" name="destacado" id="d3" <?= $d['destacado'] ? 'checked' : '' ?>><label class="form-check-label" for="d3">Destacado en la página principal</label></div>
    <div class="form-check form-switch"><input class="form-check-input" type="checkbox" name="personalizado" id="d4" <?= $d['personalizado'] ? 'checked' : '' ?>><label class="form-check-label" for="d4">Producto personalizado (bajo pedido)</label></div>
  </div>
</div>
<div class="mt-3 d-flex gap-2"><button class="btn btn-brand">Guardar</button><a class="btn btn-outline-secondary" href="<?= url('panel/productos.php') ?>">Cancelar</a></div>
</form>
<?php include __DIR__ . '/../includes/panel_footer.php'; ?>
