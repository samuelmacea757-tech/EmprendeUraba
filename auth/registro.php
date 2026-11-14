<?php
/**
 * Registro de nuevos usuarios (compradores y emprendedores).
 *
 * El rol administrador no se puede elegir aquí: solo lo asigna otro
 * administrador desde el panel (Admin > Usuarios).
 */

require_once __DIR__ . '/../includes/bootstrap.php';

/**
 * Valida una contraseña: mínimo 8 caracteres, con letras y números.
 */
function contrasena_es_segura(string $contrasena): bool
{
    return strlen($contrasena) >= 8
        && preg_match('/[A-Za-z]/', $contrasena)
        && preg_match('/\d/', $contrasena);
}

if (current_user()) {
    redirect(home_for(current_user()));
}

$title = 'Registro';
$roles_permitidos = ['emprendedor', 'comprador'];
$rol = in_array(get('rol'), $roles_permitidos, true) ? get('rol') : 'comprador';
$errores = [];

if (is_post()) {
    csrf_verify();

    $rol = in_array(post('rol'), $roles_permitidos, true) ? post('rol') : 'comprador';
    $nombre = post('nombre');
    $apellido = post('apellido');
    $correo = strtolower(post('email'));
    $telefono = post('telefono');
    $contrasena = $_POST['password'] ?? '';
    $contrasena_confirmada = $_POST['password2'] ?? '';

    // --- Validación de los datos ingresados ---
    if (mb_strlen($nombre) < 2) {
        $errores[] = 'Ingresa tu nombre.';
    }
    if (mb_strlen($apellido) < 2) {
        $errores[] = 'Ingresa tu apellido.';
    }
    if (!filter_var($correo, FILTER_VALIDATE_EMAIL)) {
        $errores[] = 'Correo electrónico inválido.';
    }
    if ($telefono !== '' && !preg_match('/^[0-9+\s()-]{7,20}$/', $telefono)) {
        $errores[] = 'Teléfono inválido.';
    }
    if (!contrasena_es_segura($contrasena)) {
        $errores[] = 'La contraseña debe tener mínimo 8 caracteres, con letras y números.';
    }
    if ($contrasena !== $contrasena_confirmada) {
        $errores[] = 'Las contraseñas no coinciden.';
    }
    if (!$errores && consultar_valor('SELECT id FROM usuarios WHERE email=?', [$correo])) {
        $errores[] = 'Ese correo ya está registrado.';
    }

    // --- Creación de la cuenta ---
    if (!$errores) {
        consultar(
            'INSERT INTO usuarios (nombre, apellido, email, telefono, password_hash, rol) VALUES (?,?,?,?,?,?)',
            [$nombre, $apellido, $correo, $telefono ?: null, password_hash($contrasena, PASSWORD_DEFAULT), $rol]
        );
        $usuario_id = (int) db()->lastInsertId();

        login_user(consultar_uno('SELECT * FROM usuarios WHERE id=?', [$usuario_id]));
        log_activity('registro', "$correo ($rol)");

        flash('success', '¡Bienvenido a ' . APP_NAME . '!');
        redirect($rol === 'emprendedor' ? 'panel/negocio.php' : 'index.php');
    }
}

include __DIR__ . '/../includes/header.php';
?>
<div class="row justify-content-center">
  <div class="col-md-7 col-lg-6">
    <div class="card shadow-sm">
      <div class="card-body p-4">
        <h4 class="mb-3">Crear cuenta</h4>
        <?php foreach ($errores as $mensaje_error): ?>
          <div class="alert alert-danger py-2"><?= e($mensaje_error) ?></div>
        <?php endforeach; ?>
        <form method="post">
          <?= csrf_field() ?>
          <div class="btn-group w-100 mb-3">
            <input type="radio" class="btn-check" name="rol" id="rol_comprador" value="comprador" <?= $rol === 'comprador' ? 'checked' : '' ?>>
            <label class="btn btn-outline-secondary" for="rol_comprador"><i class="bi bi-bag"></i> Soy comprador</label>
            <input type="radio" class="btn-check" name="rol" id="rol_emprendedor" value="emprendedor" <?= $rol === 'emprendedor' ? 'checked' : '' ?>>
            <label class="btn btn-outline-secondary" for="rol_emprendedor"><i class="bi bi-shop"></i> Soy emprendedor</label>
          </div>
          <div class="row g-2">
            <div class="col-6"><input class="form-control" name="nombre" placeholder="Nombres" required value="<?= e(post('nombre')) ?>"></div>
            <div class="col-6"><input class="form-control" name="apellido" placeholder="Apellidos" required value="<?= e(post('apellido')) ?>"></div>
            <div class="col-12"><input type="email" class="form-control" name="email" placeholder="Correo electrónico" required value="<?= e(post('email')) ?>"></div>
            <div class="col-12"><input class="form-control" name="telefono" placeholder="Teléfono" value="<?= e(post('telefono')) ?>"></div>
            <div class="col-6"><input type="password" class="form-control" name="password" placeholder="Contraseña" required minlength="8"></div>
            <div class="col-6"><input type="password" class="form-control" name="password2" placeholder="Repetir contraseña" required></div>
          </div>
          <button class="btn btn-brand w-100 mt-3">Registrarme</button>
        </form>
        <div class="mt-3 small text-center">¿Ya tienes cuenta? <a href="<?= url('auth/login.php') ?>">Inicia sesión</a></div>
      </div>
    </div>
  </div>
</div>
<?php include __DIR__ . '/../includes/footer.php'; ?>