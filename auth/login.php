<?php
/**
 * Inicio de sesión con correo y contraseña.
 *
 * Rol: visitantes sin sesión.
 * Seguridad: tras 5 intentos fallidos se bloquea el acceso durante 5 minutos
 * y cada intento fallido queda en la bitácora.
 */

require_once __DIR__ . '/../includes/bootstrap.php';

const MAXIMO_INTENTOS_FALLIDOS = 5;
const SEGUNDOS_DE_BLOQUEO = 300;

/**
 * Decide a dónde enviar al usuario tras iniciar sesión.
 * Solo acepta rutas internas del sitio (evita redirecciones a páginas externas).
 */
function ruta_despues_de_login(string $destino_solicitado, array $usuario): string
{
    $prefijo_interno = BASE_URL . '/';

    if ($destino_solicitado !== '' && str_starts_with($destino_solicitado, $prefijo_interno)) {
        return substr($destino_solicitado, strlen($prefijo_interno));
    }

    return home_for($usuario);
}

if (current_user()) {
    redirect(home_for(current_user()));
}

$title = 'Iniciar sesión';
$destino_solicitado = get('next');

if (is_post()) {
    csrf_verify();

    $_SESSION['intentos'] = $_SESSION['intentos'] ?? 0;
    $_SESSION['bloqueo'] = $_SESSION['bloqueo'] ?? 0;

    if (time() < $_SESSION['bloqueo']) {
        flash('danger', 'Demasiados intentos. Espera unos minutos.');
        redirect('auth/login.php');
    }

    $correo = strtolower(post('email'));
    // La contraseña no se recorta: debe compararse exactamente como se registró.
    $contrasena = $_POST['password'] ?? '';
    $usuario = consultar_uno('SELECT * FROM usuarios WHERE email=?', [$correo]);

    if ($usuario && password_verify($contrasena, $usuario['password_hash'])) {
        if ($usuario['estado'] !== 'activo') {
            flash('danger', 'Tu cuenta está ' . $usuario['estado'] . '. Contacta al administrador.');
            redirect('auth/login.php');
        }

        $_SESSION['intentos'] = 0;
        login_user($usuario);

        // Actualiza el hash si el algoritmo por defecto de PHP cambió.
        if (password_needs_rehash($usuario['password_hash'], PASSWORD_DEFAULT)) {
            consultar(
                'UPDATE usuarios SET password_hash=? WHERE id=?',
                [password_hash($contrasena, PASSWORD_DEFAULT), $usuario['id']]
            );
        }

        redirect(ruta_despues_de_login($destino_solicitado, $usuario));
    }

    $_SESSION['intentos']++;
    if ($_SESSION['intentos'] >= MAXIMO_INTENTOS_FALLIDOS) {
        $_SESSION['bloqueo'] = time() + SEGUNDOS_DE_BLOQUEO;
        $_SESSION['intentos'] = 0;
    }

    log_activity('login_fallido', $correo);
    flash('danger', 'Correo o contraseña incorrectos.');
    redirect('auth/login.php' . ($destino_solicitado !== '' ? '?next=' . urlencode($destino_solicitado) : ''));
}

include __DIR__ . '/../includes/header.php';
?>
<div class="row justify-content-center">
  <div class="col-md-5">
    <div class="card shadow-sm">
      <div class="card-body p-4">
        <h4 class="mb-3">Iniciar sesión</h4>
        <form method="post">
          <?= csrf_field() ?>
          <div class="mb-3">
            <label class="form-label">Correo electrónico</label>
            <input type="email" name="email" class="form-control" required autofocus>
          </div>
          <div class="mb-3">
            <label class="form-label">Contraseña</label>
            <input type="password" name="password" class="form-control" required>
          </div>
          <button class="btn btn-brand w-100">Entrar</button>
        </form>
        <div class="d-flex justify-content-between mt-3 small">
          <a href="<?= url('auth/recuperar.php') ?>">¿Olvidaste tu contraseña?</a>
          <a href="<?= url('auth/registro.php') ?>">Crear cuenta</a>
        </div>
      </div>
    </div>
  </div>
</div>
<?php include __DIR__ . '/../includes/footer.php'; ?>