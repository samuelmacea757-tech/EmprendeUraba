<?php
require_once __DIR__ . '/../includes/bootstrap.php';
$me = require_login(['admin']);
if (is_post()) {
    csrf_verify(); $a = post('accion'); $uid = (int)post('id');
    if ($a === 'crear_admin') {
        $email = strtolower(post('email')); $pw = $_POST['password'] ?? '';
        if (mb_strlen(post('nombre')) < 2 || mb_strlen(post('apellido')) < 2) flash('danger', 'Nombre y apellido son obligatorios.');
        elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) flash('danger', 'Correo inválido.');
        elseif (strlen($pw) < 8 || !preg_match('/[A-Za-z]/', $pw) || !preg_match('/\d/', $pw)) flash('danger', 'La contraseña debe tener 8+ caracteres con letras y números.');
        elseif (qv('SELECT id FROM usuarios WHERE email=?', [$email])) flash('danger', 'Ese correo ya está registrado.');
        else {
            q("INSERT INTO usuarios (nombre, apellido, email, password_hash, rol) VALUES (?,?,?,?, 'admin')", [post('nombre'), post('apellido'), $email, password_hash($pw, PASSWORD_DEFAULT)]);
            log_activity('admin_crear_admin', $email); flash('success', 'Administrador creado.');
        }
    } elseif ($uid !== (int)$me['id'] && in_array($a, ['promover', 'quitar_admin'])) {
        $x = q1('SELECT * FROM usuarios WHERE id=?', [$uid]);
        if ($x && $a === 'promover' && $x['rol'] !== 'admin') {
            q("UPDATE usuarios SET rol='admin' WHERE id=?", [$uid]); log_activity('admin_promover', $x['email']); flash('success', 'Usuario promovido a administrador.');
        } elseif ($x && $a === 'quitar_admin' && $x['rol'] === 'admin') {
            q("UPDATE usuarios SET rol='comprador', session_token=NULL WHERE id=?", [$uid]); log_activity('admin_quitar_admin', $x['email']); flash('success', 'Rol de administrador retirado (quedó como comprador).');
        }
    } elseif ($uid !== (int)$me['id'] && in_array(post('estado'), ['activo', 'suspendido'])) {
        q('UPDATE usuarios SET estado=?, session_token=NULL WHERE id=?', [post('estado'), $uid]); log_activity('admin_usuario_' . post('estado'), "#$uid"); flash('success', 'Cuenta actualizada.');
    }
    redirect('admin/usuarios.php');
}
$title = 'Usuarios'; $active = 'usuarios'; $qs = get('q'); $rol = get('rol');
$sql = 'SELECT * FROM usuarios WHERE 1'; $par = [];
if ($qs !== '') { $sql .= ' AND (nombre LIKE ? OR apellido LIKE ? OR email LIKE ?)'; array_push($par, "%$qs%", "%$qs%", "%$qs%"); }
if (in_array($rol, ['admin', 'emprendedor', 'comprador'])) { $sql .= ' AND rol=?'; $par[] = $rol; }
$lista = qa($sql . ' ORDER BY creado_en DESC LIMIT 300', $par);
include __DIR__ . '/../includes/panel_header.php';
?>
<h3>Usuarios registrados</h3>
<div class="card card-body mb-3"><h6>Crear administrador</h6>
<form method="post" class="row g-2"><?= csrf_field() ?><input type="hidden" name="accion" value="crear_admin">
  <div class="col-md-2"><input class="form-control" name="nombre" placeholder="Nombres" required></div><div class="col-md-2"><input class="form-control" name="apellido" placeholder="Apellidos" required></div>
  <div class="col-md-3"><input type="email" class="form-control" name="email" placeholder="Correo" required></div><div class="col-md-3"><input type="password" class="form-control" name="password" placeholder="Contraseña (8+, letras y números)" required minlength="8"></div>
  <div class="col-md-2"><button class="btn btn-brand w-100">Crear admin</button></div></form></div>
<form class="row g-2 mb-3"><div class="col-md-4"><input class="form-control" name="q" value="<?= e($qs) ?>" placeholder="Buscar…"></div><div class="col-md-2"><select name="rol" class="form-select" onchange="this.form.submit()"><option value="">Todos</option><?php foreach (['admin', 'emprendedor', 'comprador'] as $r): ?><option <?= $rol === $r ? 'selected' : '' ?>><?= $r ?></option><?php endforeach; ?></select></div></form>
<div class="table-responsive"><table class="table align-middle mb-0"><thead><tr><th>Nombre</th><th>Correo</th><th>Rol</th><th>Registro</th><th>Estado</th><th></th></tr></thead><tbody>
<?php foreach ($lista as $x): ?><tr><td><?= e($x['nombre'] . ' ' . $x['apellido']) ?></td><td><?= e($x['email']) ?></td><td><?= e($x['rol']) ?></td><td><?= fdate($x['creado_en']) ?></td><td><?= badge_estado($x['estado']) ?></td>
<td class="text-nowrap"><?php if ($x['id'] != $me['id']): ?><form method="post" class="d-inline"><?= csrf_field() ?><input type="hidden" name="id" value="<?= $x['id'] ?>">
<?php if ($x['estado'] === 'activo'): ?><button name="estado" value="suspendido" class="btn btn-sm btn-outline-danger" data-confirm="¿Suspender cuenta?">Suspender</button><?php else: ?><button name="estado" value="activo" class="btn btn-sm btn-outline-success">Activar</button><?php endif; ?>
<?php if ($x['rol'] === 'admin'): ?><button name="accion" value="quitar_admin" class="btn btn-sm btn-outline-warning" data-confirm="¿Quitar el rol de administrador?">Quitar admin</button>
<?php else: ?><button name="accion" value="promover" class="btn btn-sm btn-outline-primary" data-confirm="¿Dar permisos de administrador a este usuario?">Hacer admin</button><?php endif; ?></form><?php endif; ?></td></tr><?php endforeach; ?></tbody></table></div>
<?php include __DIR__ . '/../includes/panel_footer.php'; ?>