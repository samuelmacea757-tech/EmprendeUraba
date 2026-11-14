<?php
/**
 * Recuperación de contraseña: paso 2 (definir la contraseña nueva).
 *
 * Solo funciona con un token vigente (menos de 1 hora) y sin usar. Al
 * guardar la nueva contraseña se invalidan todas las sesiones abiertas.
 */

require_once __DIR__ . '/../includes/bootstrap.php';

$title = 'Nueva contraseña';
$token = get('token', post('token'));

$solicitud = consultar_uno(
    'SELECT * FROM password_resets WHERE token_hash=? AND usado=0 AND expira > NOW()',
    [hash('sha256', $token)]
);

if (!$solicitud) {
    flash('danger', 'El enlace no es válido o ya expiró.');
    redirect('auth/recuperar.php');
}

if (is_post()) {
    csrf_verify();

    $contrasena = $_POST['password'] ?? '';
    $contrasena_confirmada = $_POST['password2'] ?? '';
    $es_segura = strlen($contrasena) >= 8
        && preg_match('/[A-Za-z]/', $contrasena)
        && preg_match('/\d/', $contrasena);

    if (!$es_segura || $contrasena !== $contrasena_confirmada) {
        flash('danger', 'La contraseña debe tener 8+ caracteres con letras y números, y coincidir.');
        redirect('auth/restablecer.php?token=' . urlencode($token));
    }

    consultar(
        'UPDATE usuarios SET password_hash=?, session_token=NULL WHERE id=?',
        [password_hash($contrasena, PASSWORD_DEFAULT), $solicitud['usuario_id']]
    );
    consultar('UPDATE password_resets SET usado=1 WHERE usuario_id=?', [$solicitud['usuario_id']]);

    flash('success', 'Contraseña actualizada. Ya puedes iniciar sesión.');
    redirect('auth/login.php');
}

include __DIR__ . '/../includes/header.php';
?>
<div class="row justify-content-center">
  <div class="col-md-5">
    <div class="card shadow-sm">
      <div class="card-body p-4">
        <h4>Nueva contraseña</h4>
        <form method="post">
          <?= csrf_field() ?>
          <input type="hidden" name="token" value="<?= e($token) ?>">
          <div class="mb-3"><input type="password" name="password" class="form-control" placeholder="Nueva contraseña" required minlength="8"></div>
          <div class="mb-3"><input type="password" name="password2" class="form-control" placeholder="Repetir contraseña" required></div>
          <button class="btn btn-brand w-100">Guardar</button>
        </form>
      </div>
    </div>
  </div>
</div>
<?php include __DIR__ . '/../includes/footer.php'; ?>