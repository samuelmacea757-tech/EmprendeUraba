<?php
/**
 * Recuperación de contraseña: paso 1 (solicitar el enlace).
 *
 * Se genera un token aleatorio de un solo uso, válido por 1 hora. En la base
 * de datos solo se guarda su hash (SHA-256), nunca el token original.
 * Por seguridad el mensaje es el mismo exista o no el correo.
 */

require_once __DIR__ . '/../includes/bootstrap.php';

$title = 'Recuperar contraseña';
$enlace_de_recuperacion = null;

if (is_post()) {
    csrf_verify();

    $usuario = consultar_uno(
        "SELECT * FROM usuarios WHERE email=? AND estado='activo'",
        [strtolower(post('email'))]
    );

    if ($usuario) {
        $token = bin2hex(random_bytes(32));
        consultar(
            'INSERT INTO password_resets (usuario_id, token_hash, expira) VALUES (?,?,DATE_ADD(NOW(), INTERVAL 1 HOUR))',
            [$usuario['id'], hash('sha256', $token)]
        );

        $protocolo = !empty($_SERVER['HTTPS']) ? 'https' : 'http';
        $enlace_de_recuperacion = $protocolo . '://' . $_SERVER['HTTP_HOST'] . url('auth/restablecer.php?token=' . $token);

        @mail(
            $usuario['email'],
            'Recuperar contraseña - ' . APP_NAME,
            "Usa este enlace (válido 1 hora):\n" . $enlace_de_recuperacion,
            'From: no-reply@emprendeuraba.com'
        );
        log_activity('recuperar_clave', $usuario['email']);
    }

    flash('success', 'Si el correo está registrado, recibirás un enlace para restablecer tu contraseña.');

    // En modo desarrollo (sin correo) se queda en la página para mostrar el enlace.
    if (!APP_DEV || !$enlace_de_recuperacion) {
        redirect('auth/login.php');
    }
}

include __DIR__ . '/../includes/header.php';
?>
<div class="row justify-content-center">
  <div class="col-md-5">
    <div class="card shadow-sm">
      <div class="card-body p-4">
        <h4>Recuperar contraseña</h4>
        <?php if ($enlace_de_recuperacion && APP_DEV): ?>
          <div class="alert alert-warning small">
            Modo desarrollo (sin correo): <a href="<?= e($enlace_de_recuperacion) ?>">abrir enlace de restablecimiento</a>
          </div>
        <?php endif; ?>
        <form method="post">
          <?= csrf_field() ?>
          <div class="mb-3">
            <label class="form-label">Correo electrónico</label>
            <input type="email" name="email" class="form-control" required>
          </div>
          <button class="btn btn-brand w-100">Enviar enlace</button>
        </form>
      </div>
    </div>
  </div>
</div>
<?php include __DIR__ . '/../includes/footer.php'; ?>