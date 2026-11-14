<?php
/**
 * Crear o editar el emprendimiento del emprendedor.
 *
 * Cada emprendedor tiene un único negocio. Aquí se registran nombre,
 * descripción, categoría, logo, portada, contacto, ubicación, horarios,
 * redes sociales y métodos de pago, y se activa o desactiva el negocio.
 */

require_once __DIR__ . '/../includes/bootstrap.php';

/** Métodos de pago que el emprendedor puede ofrecer. */
const METODOS_DE_PAGO = ['Efectivo', 'Transferencia', 'Nequi', 'Daviplata', 'Tarjeta', 'Contra entrega'];

$usuario = require_login(['emprendedor']);
$negocio = negocio_actual(); // null si todavía no lo ha creado
$categorias = consultar_todos('SELECT id, nombre FROM categorias WHERE activo=1 ORDER BY nombre');

if (is_post()) {
    csrf_verify();

    try {
        $nombre = post('nombre');
        if (mb_strlen($nombre) < 3) {
            throw new RuntimeException('El nombre del negocio es obligatorio (mín. 3 caracteres).');
        }
        if (post('email') !== '' && !filter_var(post('email'), FILTER_VALIDATE_EMAIL)) {
            throw new RuntimeException('Correo de contacto inválido.');
        }
        foreach (['facebook', 'instagram'] as $red_social) {
            if (post($red_social) !== '' && !preg_match('#^https?://#i', post($red_social))) {
                throw new RuntimeException("El enlace de $red_social debe iniciar con http:// o https://");
            }
        }

        $logo = upload_image('logo', $negocio['logo'] ?? null);
        $portada = upload_image('portada', $negocio['portada'] ?? null);

        // Solo se aceptan métodos de pago de la lista permitida.
        $metodos_elegidos = array_intersect($_POST['metodos'] ?? [], METODOS_DE_PAGO);
        $metodos_de_pago = implode(', ', $metodos_elegidos) ?: 'Efectivo';

        $datos = [
            $nombre, post('descripcion'), (int) post('categoria_id') ?: null, $logo, $portada,
            post('telefono'), post('email'), post('direccion'), post('ciudad'), post('horario'),
            post('facebook'), post('instagram'), post('whatsapp'), $metodos_de_pago,
        ];

        if ($negocio) {
            consultar(
                'UPDATE emprendimientos
                 SET nombre=?, descripcion=?, categoria_id=?, logo=?, portada=?, telefono=?, email=?,
                     direccion=?, ciudad=?, horario=?, facebook=?, instagram=?, whatsapp=?, metodos_pago=?, activo=?
                 WHERE id=?',
                array_merge($datos, [isset($_POST['activo']) ? 1 : 0, $negocio['id']])
            );
            flash('success', 'Información del negocio actualizada.');
        } else {
            consultar(
                'INSERT INTO emprendimientos
                    (nombre, descripcion, categoria_id, logo, portada, telefono, email,
                     direccion, ciudad, horario, facebook, instagram, whatsapp, metodos_pago, usuario_id)
                 VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)',
                array_merge($datos, [$usuario['id']])
            );
            log_activity('negocio_creado', $nombre);
            flash('success', '¡Tu emprendimiento fue creado! Ahora personalízalo y agrega productos.');
            redirect('panel/personalizar.php');
        }
    } catch (RuntimeException $error) {
        flash('danger', $error->getMessage());
    }
    redirect('panel/negocio.php');
}

$title = $negocio ? 'Mi negocio' : 'Crear emprendimiento';
$active = 'negocio';

// Valores iniciales del formulario cuando el negocio aún no existe.
$negocio = $negocio ?? [
    'nombre' => '', 'descripcion' => '', 'categoria_id' => '', 'logo' => null, 'portada' => null,
    'telefono' => '', 'email' => '', 'direccion' => '', 'ciudad' => '', 'horario' => '',
    'facebook' => '', 'instagram' => '', 'whatsapp' => '', 'metodos_pago' => 'Efectivo', 'activo' => 1,
];
$metodos_actuales = array_map('trim', explode(',', $negocio['metodos_pago']));
$es_negocio_existente = !empty($negocio['id']);

include __DIR__ . '/../includes/panel_header.php';
?>
<h3><?= $title ?></h3>
<form method="post" enctype="multipart/form-data" class="card card-body">
  <?= csrf_field() ?>
  <div class="row g-3">
    <div class="col-md-6">
      <label class="form-label">Nombre del negocio *</label>
      <input class="form-control" name="nombre" required value="<?= e($negocio['nombre']) ?>">
    </div>
    <div class="col-md-6">
      <label class="form-label">Categoría comercial</label>
      <select class="form-select" name="categoria_id">
        <option value="">Selecciona…</option>
        <?php foreach ($categorias as $categoria): ?>
          <option value="<?= $categoria['id'] ?>" <?= $negocio['categoria_id'] == $categoria['id'] ? 'selected' : '' ?>><?= e($categoria['nombre']) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="col-12">
      <label class="form-label">Descripción</label>
      <textarea class="form-control" name="descripcion" rows="3"><?= e($negocio['descripcion']) ?></textarea>
    </div>
    <div class="col-md-6">
      <label class="form-label">Logotipo</label>
      <div class="d-flex gap-2 align-items-center">
        <img id="vista_logo" src="<?= img($negocio['logo'], 'assets/img/logo.svg') ?>" class="logo-sm rounded-circle border" alt="">
        <input type="file" class="form-control" name="logo" accept="image/*" data-preview="#vista_logo">
      </div>
    </div>
    <div class="col-md-6">
      <label class="form-label">Imagen de portada</label>
      <div class="d-flex gap-2 align-items-center">
        <img id="vista_portada" src="<?= img($negocio['portada']) ?>" style="width:90px;height:56px;object-fit:cover" class="rounded border" alt="">
        <input type="file" class="form-control" name="portada" accept="image/*" data-preview="#vista_portada">
      </div>
    </div>
    <div class="col-md-4"><label class="form-label">Teléfono</label><input class="form-control" name="telefono" value="<?= e($negocio['telefono']) ?>"></div>
    <div class="col-md-4"><label class="form-label">Correo de contacto</label><input type="email" class="form-control" name="email" value="<?= e($negocio['email']) ?>"></div>
    <div class="col-md-4"><label class="form-label">WhatsApp (con indicativo)</label><input class="form-control" name="whatsapp" placeholder="573001234567" value="<?= e($negocio['whatsapp']) ?>"></div>
    <div class="col-md-8"><label class="form-label">Dirección</label><input class="form-control" name="direccion" value="<?= e($negocio['direccion']) ?>"></div>
    <div class="col-md-4"><label class="form-label">Municipio / ciudad</label><input class="form-control" name="ciudad" value="<?= e($negocio['ciudad']) ?>" placeholder="Apartadó, Turbo, Carepa…"></div>
    <div class="col-12">
      <label class="form-label">Horarios de atención</label>
      <textarea class="form-control" name="horario" rows="2" placeholder="Lun-Vie 8:00-18:00&#10;Sáb 9:00-13:00"><?= e($negocio['horario']) ?></textarea>
    </div>
    <div class="col-md-6"><label class="form-label">Facebook (URL)</label><input class="form-control" name="facebook" value="<?= e($negocio['facebook']) ?>"></div>
    <div class="col-md-6"><label class="form-label">Instagram (URL)</label><input class="form-control" name="instagram" value="<?= e($negocio['instagram']) ?>"></div>
    <div class="col-12">
      <label class="form-label d-block">Métodos de pago aceptados</label>
      <?php foreach (METODOS_DE_PAGO as $metodo): ?>
        <div class="form-check form-check-inline">
          <input class="form-check-input" type="checkbox" name="metodos[]" value="<?= $metodo ?>" id="metodo_<?= $metodo ?>" <?= in_array($metodo, $metodos_actuales) ? 'checked' : '' ?>>
          <label class="form-check-label" for="metodo_<?= $metodo ?>"><?= $metodo ?></label>
        </div>
      <?php endforeach; ?>
    </div>
    <?php if ($es_negocio_existente): ?>
      <div class="col-12">
        <div class="form-check form-switch">
          <input class="form-check-input" type="checkbox" name="activo" id="negocio_activo" <?= $negocio['activo'] ? 'checked' : '' ?>>
          <label for="negocio_activo" class="form-check-label">Emprendimiento activo (visible en la plataforma)</label>
        </div>
      </div>
    <?php endif; ?>
  </div>
  <div class="mt-3">
    <button class="btn btn-brand"><?= $es_negocio_existente ? 'Guardar cambios' : 'Crear emprendimiento' ?></button>
  </div>
</form>
<?php include __DIR__ . '/../includes/panel_footer.php'; ?>