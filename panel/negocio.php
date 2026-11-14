<?php
require_once __DIR__ . '/../includes/bootstrap.php';
$u = require_login(['emprendedor']);
$n = negocio_actual();
$cats = qa('SELECT id, nombre FROM categorias WHERE activo=1 ORDER BY nombre');
if (is_post()) {
    csrf_verify();
    try {
        $nombre = post('nombre'); if (mb_strlen($nombre) < 3) throw new RuntimeException('El nombre del negocio es obligatorio (mín. 3 caracteres).');
        if (post('email') !== '' && !filter_var(post('email'), FILTER_VALIDATE_EMAIL)) throw new RuntimeException('Correo de contacto inválido.');
        foreach (['facebook', 'instagram'] as $r) if (post($r) !== '' && !preg_match('#^https?://#i', post($r))) throw new RuntimeException("El enlace de $r debe iniciar con http:// o https://");
        $logo = upload_image('logo', $n['logo'] ?? null); $portada = upload_image('portada', $n['portada'] ?? null);
        $metodos = implode(', ', array_intersect($_POST['metodos'] ?? [], ['Efectivo', 'Transferencia', 'Nequi', 'Daviplata', 'Tarjeta', 'Contra entrega']));
        $d = [$nombre, post('descripcion'), (int)post('categoria_id') ?: null, $logo, $portada, post('telefono'), post('email'), post('direccion'), post('ciudad'), post('horario'),
              post('facebook'), post('instagram'), post('whatsapp'), $metodos ?: 'Efectivo'];
        if ($n) {
            q('UPDATE emprendimientos SET nombre=?, descripcion=?, categoria_id=?, logo=?, portada=?, telefono=?, email=?, direccion=?, ciudad=?, horario=?, facebook=?, instagram=?, whatsapp=?, metodos_pago=?, activo=? WHERE id=?', array_merge($d, [isset($_POST['activo']) ? 1 : 0, $n['id']]));
            flash('success', 'Información del negocio actualizada.');
        } else {
            q('INSERT INTO emprendimientos (nombre, descripcion, categoria_id, logo, portada, telefono, email, direccion, ciudad, horario, facebook, instagram, whatsapp, metodos_pago, usuario_id) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)', array_merge($d, [$u['id']]));
            flash('success', '¡Tu emprendimiento fue creado! Ahora personalízalo y agrega productos.');
            log_activity('negocio_creado', $nombre); redirect('panel/personalizar.php');
        }
    } catch (RuntimeException $e) { flash('danger', $e->getMessage()); }
    redirect('panel/negocio.php');
}
$title = $n ? 'Mi negocio' : 'Crear emprendimiento'; $active = 'negocio';
$n = $n ?? array_fill_keys(['nombre', 'descripcion', 'categoria_id', 'logo', 'portada', 'telefono', 'email', 'direccion', 'ciudad', 'horario', 'facebook', 'instagram', 'whatsapp'], '') + ['metodos_pago' => 'Efectivo', 'activo' => 1];
$mp = array_map('trim', explode(',', $n['metodos_pago']));
include __DIR__ . '/../includes/panel_header.php';
?>
<h3><?= $title ?></h3>
<form method="post" enctype="multipart/form-data" class="card card-body"><?= csrf_field() ?>
<div class="row g-3">
  <div class="col-md-6"><label class="form-label">Nombre del negocio *</label><input class="form-control" name="nombre" required value="<?= e($n['nombre']) ?>"></div>
  <div class="col-md-6"><label class="form-label">Categoría comercial</label><select class="form-select" name="categoria_id"><option value="">Selecciona…</option><?php foreach ($cats as $c): ?><option value="<?= $c['id'] ?>" <?= $n['categoria_id'] == $c['id'] ? 'selected' : '' ?>><?= e($c['nombre']) ?></option><?php endforeach; ?></select></div>
  <div class="col-12"><label class="form-label">Descripción</label><textarea class="form-control" name="descripcion" rows="3"><?= e($n['descripcion']) ?></textarea></div>
  <div class="col-md-6"><label class="form-label">Logotipo</label><div class="d-flex gap-2 align-items-center"><img id="pl" src="<?= img($n['logo'], 'assets/img/logo.svg') ?>" class="logo-sm rounded-circle border"><input type="file" class="form-control" name="logo" accept="image/*" data-preview="#pl"></div></div>
  <div class="col-md-6"><label class="form-label">Imagen de portada</label><div class="d-flex gap-2 align-items-center"><img id="pp" src="<?= img($n['portada']) ?>" style="width:90px;height:56px;object-fit:cover" class="rounded border"><input type="file" class="form-control" name="portada" accept="image/*" data-preview="#pp"></div></div>
  <div class="col-md-4"><label class="form-label">Teléfono</label><input class="form-control" name="telefono" value="<?= e($n['telefono']) ?>"></div>
  <div class="col-md-4"><label class="form-label">Correo de contacto</label><input type="email" class="form-control" name="email" value="<?= e($n['email']) ?>"></div>
  <div class="col-md-4"><label class="form-label">WhatsApp (con indicativo)</label><input class="form-control" name="whatsapp" placeholder="573001234567" value="<?= e($n['whatsapp']) ?>"></div>
  <div class="col-md-8"><label class="form-label">Dirección</label><input class="form-control" name="direccion" value="<?= e($n['direccion']) ?>"></div>
  <div class="col-md-4"><label class="form-label">Municipio / ciudad</label><input class="form-control" name="ciudad" value="<?= e($n['ciudad']) ?>" placeholder="Apartadó, Turbo, Carepa…"></div>
  <div class="col-12"><label class="form-label">Horarios de atención</label><textarea class="form-control" name="horario" rows="2" placeholder="Lun-Vie 8:00-18:00&#10;Sáb 9:00-13:00"><?= e($n['horario']) ?></textarea></div>
  <div class="col-md-6"><label class="form-label">Facebook (URL)</label><input class="form-control" name="facebook" value="<?= e($n['facebook']) ?>"></div>
  <div class="col-md-6"><label class="form-label">Instagram (URL)</label><input class="form-control" name="instagram" value="<?= e($n['instagram']) ?>"></div>
  <div class="col-12"><label class="form-label d-block">Métodos de pago aceptados</label>
    <?php foreach (['Efectivo', 'Transferencia', 'Nequi', 'Daviplata', 'Tarjeta', 'Contra entrega'] as $m): ?><div class="form-check form-check-inline"><input class="form-check-input" type="checkbox" name="metodos[]" value="<?= $m ?>" id="m<?= $m ?>" <?= in_array($m, $mp) ? 'checked' : '' ?>><label class="form-check-label" for="m<?= $m ?>"><?= $m ?></label></div><?php endforeach; ?></div>
  <?php if (!empty($n['id'])): ?><div class="col-12"><div class="form-check form-switch"><input class="form-check-input" type="checkbox" name="activo" id="act" <?= $n['activo'] ? 'checked' : '' ?>><label for="act" class="form-check-label">Emprendimiento activo (visible en la plataforma)</label></div></div><?php endif; ?>
</div>
<div class="mt-3"><button class="btn btn-brand"><?= !empty($n['id']) ? 'Guardar cambios' : 'Crear emprendimiento' ?></button></div>
</form>
<?php include __DIR__ . '/../includes/panel_footer.php'; ?>
