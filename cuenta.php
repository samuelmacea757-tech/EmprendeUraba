<?php
/**
 * Configuración de la cuenta (todos los roles).
 *
 * Permite editar datos personales y foto, cambiar la contraseña, cerrar las
 * demás sesiones abiertas y desactivar la cuenta.
 */

require_once __DIR__ . '/includes/bootstrap.php';

/**
 * Valida una contraseña nueva: 8+ caracteres, con letras y números.
 */
function contrasena_nueva_es_valida(string $contrasena): bool
{
    return strlen($contrasena) >= 8
        && preg_match('/[A-Za-z]/', $contrasena)
        && preg_match('/\d/', $contrasena);
}

$usuario = require_login();

if (is_post()) {
    csrf_verify();
    $accion = post('accion');

    try {
        if ($accion === 'perfil') {
            $correo = strtolower(post('email'));
            if (!filter_var($correo, FILTER_VALIDATE_EMAIL)) {
                throw new RuntimeException('Correo inválido.');
            }
            if (consultar_valor('SELECT id FROM usuarios WHERE email=? AND id<>?', [$correo, $usuario['id']])) {
                throw new RuntimeException('Ese correo ya está en uso.');
            }
            if (post('nombre') === '' || post('apellido') === '') {
                throw new RuntimeException('Nombre y apellido son obligatorios.');
            }

            $foto = upload_image('foto', $usuario['foto']);
            consultar(
                'UPDATE usuarios SET nombre=?, apellido=?, email=?, telefono=?, foto=?, pref_notif=? WHERE id=?',
                [
                    post('nombre'), post('apellido'), $correo, post('telefono') ?: null, $foto,
                    isset($_POST['pref_notif']) ? 1 : 0, $usuario['id'],
                ]
            );
            flash('success', 'Datos actualizados.');
        } elseif ($accion === 'clave') {
            $contrasena_nueva = $_POST['nueva'] ?? '';

            if (!password_verify($_POST['actual'] ?? '', $usuario['password_hash'])) {
                throw new RuntimeException('La contraseña actual es incorrecta.');
            }
            if (!contrasena_nueva_es_valida($contrasena_nueva) || $contrasena_nueva !== ($_POST['nueva2'] ?? '')) {
                throw new RuntimeException('La nueva contraseña debe tener 8+ caracteres con letras y números, y coincidir.');
            }

            consultar('UPDATE usuarios SET password_hash=? WHERE id=?', [password_hash($contrasena_nueva, PASSWORD_DEFAULT), $usuario['id']]);
            log_activity('cambio_clave');
            flash('success', 'Contraseña cambiada.');
        } elseif ($accion === 'sesiones') {
            // Un token nuevo invalida las sesiones de otros dispositivos.
            $token_nuevo = bin2hex(random_bytes(16));
            consultar('UPDATE usuarios SET session_token=? WHERE id=?', [$token_nuevo, $usuario['id']]);
            $_SESSION['stoken'] = $token_nuevo;
            flash('success', 'Se cerraron las demás sesiones abiertas.');
        } elseif ($accion === 'desactivar') {
            if (!password_verify($_POST['actual'] ?? '', $usuario['password_hash'])) {
                throw new RuntimeException('Contraseña incorrecta.');
            }

            consultar("UPDATE usuarios SET estado='desactivado' WHERE id=?", [$usuario['id']]);
            if ($usuario['rol'] === 'emprendedor') {
                consultar('UPDATE emprendimientos SET activo=0 WHERE usuario_id=?', [$usuario['id']]);
            }
            log_activity('cuenta_desactivada');
            redirect('auth/logout.php');
        }
    } catch (RuntimeException $error) {
        flash('danger', $error->getMessage());
    }

    redirect('cuenta.php');
}

$title = 'Mi cuenta';
$active = 'cuenta';
include __DIR__ . '/includes/panel_header.php';
?>
<h3>Mi cuenta</h3>
<div class="row g-4">
  <div class="col-lg-7">
    <div class="card"><div class="card-body">
      <h5>Información personal</h5>
      <form method="post" enctype="multipart/form-data">
        <?= csrf_field() ?>
        <input type="hidden" name="accion" value="perfil">
        <div class="d-flex align-items-center gap-3 mb-3">
          <img id="vista_foto" src="<?= img($usuario['foto'], 'assets/img/logo.svg') ?>" class="rounded-circle border" width="72" height="72" style="object-fit:cover" alt="">
          <input type="file" name="foto" accept="image/*" class="form-control" data-preview="#vista_foto">
        </div>
        <div class="row g-2">
          <div class="col-6"><label class="form-label">Nombres</label><input class="form-control" name="nombre" value="<?= e($usuario['nombre']) ?>" required></div>
          <div class="col-6"><label class="form-label">Apellidos</label><input class="form-control" name="apellido" value="<?= e($usuario['apellido']) ?>" required></div>
          <div class="col-md-7"><label class="form-label">Correo</label><input type="email" class="form-control" name="email" value="<?= e($usuario['email']) ?>" required></div>
          <div class="col-md-5"><label class="form-label">Teléfono</label><input class="form-control" name="telefono" value="<?= e($usuario['telefono']) ?>"></div>
        </div>
        <div class="form-check mt-3">
          <input class="form-check-input" type="checkbox" name="pref_notif" id="pref_notif" <?= $usuario['pref_notif'] ? 'checked' : '' ?>>
          <label for="pref_notif" class="form-check-label">Recibir notificaciones dentro del sistema</label>
        </div>
        <div class="small text-muted mt-2">Cuenta creada el <?= fdate($usuario['creado_en']) ?> · Rol: <?= e($usuario['rol']) ?></div>
        <button class="btn btn-brand mt-3">Guardar cambios</button>
      </form>
    </div></div>
  </div>

  <div class="col-lg-5">
    <div class="card mb-3"><div class="card-body">
      <h5>Cambiar contraseña</h5>
      <form method="post">
        <?= csrf_field() ?>
        <input type="hidden" name="accion" value="clave">
        <input type="password" class="form-control mb-2" name="actual" placeholder="Contraseña actual" required>
        <input type="password" class="form-control mb-2" name="nueva" placeholder="Nueva contraseña" required minlength="8">
        <input type="password" class="form-control mb-2" name="nueva2" placeholder="Repetir nueva" required>
        <button class="btn btn-outline-primary">Actualizar</button>
      </form>
    </div></div>

    <div class="card mb-3"><div class="card-body">
      <h5>Sesiones</h5>
      <p class="small text-muted">Cierra la sesión en otros dispositivos.</p>
      <form method="post">
        <?= csrf_field() ?>
        <input type="hidden" name="accion" value="sesiones">
        <button class="btn btn-outline-secondary">Cerrar otras sesiones</button>
      </form>
    </div></div>

    <div class="card border-danger"><div class="card-body">
      <h5 class="text-danger">Desactivar cuenta</h5>
      <form method="post">
        <?= csrf_field() ?>
        <input type="hidden" name="accion" value="desactivar">
        <input type="password" class="form-control mb-2" name="actual" placeholder="Confirma tu contraseña" required>
        <button class="btn btn-danger" data-confirm="¿Desactivar tu cuenta?">Desactivar</button>
      </form>
    </div></div>
  </div>
</div>
<?php include __DIR__ . '/includes/panel_footer.php'; ?>