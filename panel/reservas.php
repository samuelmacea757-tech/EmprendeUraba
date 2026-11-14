<?php
require_once __DIR__ . '/../includes/bootstrap.php';
$n = require_negocio(); require_modulo($n, 'reservas'); $id = $n['id'];
if (is_post()) {
    csrf_verify();
    $r = q1('SELECT r.*, s.nombre servicio FROM reservas r JOIN productos s ON s.id=r.servicio_id WHERE r.id=? AND r.negocio_id=?', [(int)post('id'), $id]);
    if ($r) {
        $a = post('accion'); $msg = null;
        if ($a === 'aceptar' && $r['estado'] === 'pendiente') { q("UPDATE reservas SET estado='aceptada' WHERE id=?", [$r['id']]); $msg = 'Tu reserva fue aceptada'; }
        elseif ($a === 'rechazar' && $r['estado'] === 'pendiente') { q("UPDATE reservas SET estado='rechazada' WHERE id=?", [$r['id']]); $msg = 'Tu reserva fue rechazada'; }
        elseif ($a === 'cancelar' && in_array($r['estado'], ['pendiente', 'aceptada'])) { q("UPDATE reservas SET estado='cancelada' WHERE id=?", [$r['id']]); $msg = 'Tu reserva fue cancelada por el negocio'; }
        elseif ($a === 'finalizar' && $r['estado'] === 'aceptada') { q("UPDATE reservas SET estado='finalizada' WHERE id=?", [$r['id']]); }
        elseif ($a === 'fecha') {
            $f = post('fecha'); $h = post('hora');
            if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $f) && preg_match('/^\d{2}:\d{2}$/', $h)) {
                if (qv("SELECT COUNT(*) FROM reservas WHERE servicio_id=? AND fecha=? AND hora=? AND estado IN ('pendiente','aceptada') AND id<>?", [$r['servicio_id'], $f, $h . ':00', $r['id']])) flash('danger', 'Ese horario ya está ocupado.');
                else { q('UPDATE reservas SET fecha=?, hora=? WHERE id=?', [$f, $h . ':00', $r['id']]); $msg = 'La fecha de tu reserva cambió a ' . fdate($f) . ' ' . $h; }
            }
        }
        if ($msg && $r['comprador_id']) notify((int)$r['comprador_id'], 'reserva', $msg . ': ' . $r['servicio'], 'comprador/index.php');
        if ($msg) flash('success', 'Reserva actualizada.');
    }
    redirect('panel/reservas.php');
}
$title = 'Reservas'; $active = 'reservas';
$est = get('estado');
$sql = 'SELECT r.*, s.nombre servicio FROM reservas r JOIN productos s ON s.id=r.servicio_id WHERE r.negocio_id=?'; $par = [$id];
if ($est !== '') { $sql .= ' AND r.estado=?'; $par[] = $est; }
$lista = qa($sql . ' ORDER BY r.fecha DESC, r.hora DESC LIMIT 200', $par);
include __DIR__ . '/../includes/panel_header.php';
?>
<h3>Reservas</h3>
<ul class="nav nav-pills mb-3"><?php foreach (['' => 'Todas', 'pendiente' => 'Pendientes', 'aceptada' => 'Aceptadas', 'finalizada' => 'Finalizadas', 'cancelada' => 'Canceladas'] as $k => $l): ?><li class="nav-item"><a class="nav-link <?= $est === $k ? 'active' : '' ?>" href="?estado=<?= $k ?>"><?= $l ?></a></li><?php endforeach; ?></ul>
<div class="table-responsive"><table class="table align-middle mb-0"><thead><tr><th>Servicio</th><th>Cliente</th><th>Fecha</th><th>Hora</th><th>Estado</th><th>Acciones</th></tr></thead><tbody>
<?php foreach ($lista as $r): ?><tr><td><?= e($r['servicio']) ?></td><td><?= e($r['nombre']) ?><div class="small text-muted"><?= e($r['telefono']) ?> <?= e($r['notas']) ?></div></td><td><?= fdate($r['fecha']) ?></td><td><?= substr($r['hora'], 0, 5) ?></td><td><?= badge_estado($r['estado']) ?></td>
<td class="text-nowrap"><form method="post" class="d-inline"><?= csrf_field() ?><input type="hidden" name="id" value="<?= $r['id'] ?>">
<?php if ($r['estado'] === 'pendiente'): ?><button name="accion" value="aceptar" class="btn btn-sm btn-success">Aceptar</button> <button name="accion" value="rechazar" class="btn btn-sm btn-outline-danger">Rechazar</button><?php endif; ?>
<?php if ($r['estado'] === 'aceptada'): ?><button name="accion" value="finalizar" class="btn btn-sm btn-outline-success">Finalizar</button><?php endif; ?>
<?php if (in_array($r['estado'], ['pendiente', 'aceptada'])): ?><button name="accion" value="cancelar" class="btn btn-sm btn-outline-secondary" data-confirm="¿Cancelar la reserva?">Cancelar</button>
<button type="button" class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#fm" data-id="<?= $r['id'] ?>" data-fecha="<?= $r['fecha'] ?>" data-hora="<?= substr($r['hora'], 0, 5) ?>">Cambiar fecha</button><?php endif; ?></form></td></tr><?php endforeach; ?>
<?php if (!$lista): ?><tr><td colspan="6" class="text-center text-muted py-4">Sin reservas.</td></tr><?php endif; ?></tbody></table></div>
<div class="modal fade" id="fm"><div class="modal-dialog"><form method="post" class="modal-content"><?= csrf_field() ?><input type="hidden" name="accion" value="fecha"><input type="hidden" name="id" id="rid">
  <div class="modal-header"><h6>Modificar fecha</h6><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div><div class="modal-body row g-2"><div class="col-7"><input type="date" name="fecha" id="rf" class="form-control" required></div><div class="col-5"><input type="time" name="hora" id="rh" class="form-control" required></div></div>
  <div class="modal-footer"><button class="btn btn-brand">Guardar</button></div></form></div></div>
<?php $page_js = '<script>document.getElementById("fm").addEventListener("show.bs.modal",e=>{rid.value=e.relatedTarget.dataset.id;rf.value=e.relatedTarget.dataset.fecha;rh.value=e.relatedTarget.dataset.hora;});</script>'; include __DIR__ . '/../includes/panel_footer.php'; ?>
