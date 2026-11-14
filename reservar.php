<?php
/**
 * Reserva de un servicio por parte de un comprador.
 *
 * El comprador elige una fecha y ve los horarios libres (de 8:00 a 17:00,
 * cada hora). Una misma fecha y hora no puede reservarse dos veces para el
 * mismo servicio mientras la reserva esté pendiente o aceptada.
 * Parámetros GET: id (del servicio), fecha (opcional).
 */

require_once __DIR__ . '/includes/bootstrap.php';

/**
 * Horarios que ofrece el sistema, en formato HH:MM:SS.
 *
 * @return string[]
 */
function horarios_disponibles_del_dia(): array
{
    $horarios = [];
    for ($hora = 8; $hora <= 17; $hora++) {
        $horarios[] = sprintf('%02d:00:00', $hora);
    }

    return $horarios;
}

$comprador = require_login(['comprador']);

$servicio = consultar_uno(
    "SELECT p.*, e.nombre AS negocio, e.usuario_id AS dueno_id, e.id AS negocio_id, e.color_primario
     FROM productos p
     JOIN emprendimientos e ON e.id = p.negocio_id
     WHERE p.id = ? AND p.tipo = 'servicio' AND p.activo = 1 AND p.publicado = 1
       AND p.disponible = 1 AND e.activo = 1 AND e.suspendido = 0",
    [(int) get('id')]
);
if (!$servicio) {
    http_response_code(404);
    exit('Servicio no disponible.');
}

$title = 'Reservar ' . $servicio['nombre'];
$brand_color = $servicio['color_primario'];

// Fecha consultada: por defecto mañana; nunca una fecha pasada.
$fecha = get('fecha', date('Y-m-d', strtotime('+1 day')));
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $fecha) || $fecha < date('Y-m-d')) {
    $fecha = date('Y-m-d');
}

$horarios = horarios_disponibles_del_dia();
$horas_ocupadas = array_column(
    consultar_todos(
        "SELECT hora FROM reservas WHERE servicio_id=? AND fecha=? AND estado IN ('pendiente','aceptada')",
        [$servicio['id'], $fecha]
    ),
    'hora'
);

if (is_post()) {
    csrf_verify();

    $fecha_elegida = post('fecha');
    $hora_elegida = post('hora');

    try {
        $datos_validos = preg_match('/^\d{4}-\d{2}-\d{2}$/', $fecha_elegida)
            && $fecha_elegida >= date('Y-m-d')
            && in_array($hora_elegida, $horarios, true);
        if (!$datos_validos) {
            throw new RuntimeException('Fecha u hora inválida.');
        }

        db()->beginTransaction();

        // FOR UPDATE evita que dos personas reserven el mismo horario a la vez.
        $ya_ocupado = consultar_valor(
            "SELECT COUNT(*) FROM reservas
             WHERE servicio_id=? AND fecha=? AND hora=? AND estado IN ('pendiente','aceptada') FOR UPDATE",
            [$servicio['id'], $fecha_elegida, $hora_elegida]
        );
        if ($ya_ocupado) {
            throw new RuntimeException('Ese horario ya no está disponible.');
        }

        consultar(
            'INSERT INTO reservas (negocio_id, servicio_id, comprador_id, nombre, telefono, fecha, hora, notas)
             VALUES (?,?,?,?,?,?,?,?)',
            [
                $servicio['negocio_id'], $servicio['id'], $comprador['id'], post('nombre'), post('telefono'),
                $fecha_elegida, $hora_elegida, mb_substr(post('notas'), 0, 250),
            ]
        );
        db()->commit();

        notify(
            (int) $servicio['dueno_id'],
            'reserva',
            'Nueva reserva de ' . post('nombre') . ' para ' . $servicio['nombre'] . ' el '
                . fdate($fecha_elegida) . ' ' . substr($hora_elegida, 0, 5),
            'panel/reservas.php'
        );
        flash('success', 'Reserva solicitada. Recibirás una notificación cuando sea aceptada.');
        redirect('comprador/index.php');
    } catch (RuntimeException $error) {
        if (db()->inTransaction()) {
            db()->rollBack();
        }
        flash('danger', $error->getMessage());
        redirect('reservar.php?id=' . $servicio['id'] . '&fecha=' . urlencode($fecha_elegida));
    }
}

include __DIR__ . '/includes/header.php';
?>
<h3>Reservar: <?= e($servicio['nombre']) ?></h3>
<p class="text-muted"><?= e($servicio['negocio']) ?> · <?= money($servicio['precio']) ?></p>

<!-- Cambiar de día recarga la página con los horarios de esa fecha -->
<form class="row g-2 mb-3">
  <input type="hidden" name="id" value="<?= $servicio['id'] ?>">
  <div class="col-auto">
    <input type="date" name="fecha" min="<?= date('Y-m-d') ?>" value="<?= e($fecha) ?>" class="form-control" onchange="this.form.submit()">
  </div>
</form>

<form method="post" class="card card-body">
  <?= csrf_field() ?>
  <input type="hidden" name="fecha" value="<?= e($fecha) ?>">
  <h6>Horarios disponibles el <?= fdate($fecha) ?></h6>
  <div class="d-flex flex-wrap gap-2 mb-3">
    <?php foreach ($horarios as $horario): ?>
      <?php $esta_ocupado = in_array($horario, $horas_ocupadas, true); ?>
      <input type="radio" class="btn-check" name="hora" id="hora_<?= $horario ?>" value="<?= $horario ?>" <?= $esta_ocupado ? 'disabled' : 'required' ?>>
      <label class="btn btn-outline-primary <?= $esta_ocupado ? 'disabled' : '' ?>" for="hora_<?= $horario ?>"><?= substr($horario, 0, 5) ?></label>
    <?php endforeach; ?>
  </div>
  <div class="row g-2">
    <div class="col-md-4"><input class="form-control" name="nombre" required value="<?= e($comprador['nombre'] . ' ' . $comprador['apellido']) ?>"></div>
    <div class="col-md-3"><input class="form-control" name="telefono" required value="<?= e($comprador['telefono']) ?>" placeholder="Teléfono"></div>
    <div class="col-md-5"><input class="form-control" name="notas" placeholder="Notas (opcional)"></div>
  </div>
  <button class="btn btn-brand mt-3 align-self-start">Solicitar reserva</button>
</form>
<?php include __DIR__ . '/includes/footer.php'; ?>