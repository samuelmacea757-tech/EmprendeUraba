<?php
require_once __DIR__ . '/../includes/bootstrap.php';
require_login(['admin']);
// Copia de seguridad: descarga un .sql con estructura y datos
if (get('backup')) {
    csrf_verify();
    header('Content-Type: application/sql'); header('Content-Disposition: attachment; filename="respaldo-emprendeuraba-' . date('Ymd-His') . '.sql"');
    echo "-- Respaldo EmprendeUrabá " . date('c') . "\nSET FOREIGN_KEY_CHECKS=0;\n";
    foreach (array_column(qa('SHOW TABLES'), 'Tables_in_' . DB_NAME) as $t) {
        $c = q1("SHOW CREATE TABLE `$t`"); echo "\nDROP TABLE IF EXISTS `$t`;\n" . $c['Create Table'] . ";\n";
        $st = db()->query("SELECT * FROM `$t`");
        while ($r = $st->fetch()) echo "INSERT INTO `$t` VALUES (" . implode(',', array_map(fn($v) => $v === null ? 'NULL' : db()->quote((string)$v), array_values($r))) . ");\n";
    }
    echo "\nSET FOREIGN_KEY_CHECKS=1;\n"; log_activity('respaldo'); exit;
}
$title = 'Supervisión y respaldos'; $active = 'sistema';
$logs = qa('SELECT l.*, u.email FROM logs l LEFT JOIN usuarios u ON u.id=l.usuario_id ORDER BY l.id DESC LIMIT 100');
$info = ['PHP' => PHP_VERSION, 'MySQL' => qv('SELECT VERSION()'), 'Servidor' => $_SERVER['SERVER_SOFTWARE'] ?? '—', 'Carpeta uploads escribible' => is_writable(UPLOAD_DIR) ? 'Sí' : 'NO',
         'Dompdf instalado' => is_file(__DIR__ . '/../vendor/autoload.php') ? 'Sí' : 'No (ejecuta composer install)', 'Tamaño BD (MB)' => qv('SELECT ROUND(SUM(data_length+index_length)/1048576,2) FROM information_schema.tables WHERE table_schema=?', [DB_NAME]),
         'Errores de login (24 h)' => qv("SELECT COUNT(*) FROM logs WHERE accion='login_fallido' AND creado_en > DATE_SUB(NOW(), INTERVAL 1 DAY)")];
include __DIR__ . '/../includes/panel_header.php';
?>
<h3>Supervisión del sistema</h3>
<div class="row g-3 mb-4"><div class="col-lg-5"><div class="card"><ul class="list-group list-group-flush"><?php foreach ($info as $k => $v): ?><li class="list-group-item d-flex justify-content-between"><span><?= $k ?></span><strong><?= e($v) ?></strong></li><?php endforeach; ?></ul></div></div>
<div class="col-lg-7"><div class="card card-body"><h6>Copia de seguridad</h6><p class="small text-muted">Descarga un respaldo completo de la base de datos. Para recuperar la información ante un fallo, importa el archivo desde la terminal:<br><code>mysql -u usuario -p emprendeuraba &lt; respaldo.sql</code> o desde phpMyAdmin → Importar.</p>
<a class="btn btn-brand align-self-start" href="<?= csrf_url('?backup=1') ?>"><i class="bi bi-download"></i> Descargar respaldo .sql</a></div></div></div>
<h5>Actividad reciente</h5>
<div class="table-responsive"><table class="table table-sm mb-0"><thead><tr><th>Fecha</th><th>Usuario</th><th>Acción</th><th>Detalle</th><th>IP</th></tr></thead><tbody>
<?php foreach ($logs as $l): ?><tr><td><?= fdate($l['creado_en'], true) ?></td><td><?= e($l['email']) ?></td><td><?= e($l['accion']) ?></td><td><?= e($l['detalle']) ?></td><td><?= e($l['ip']) ?></td></tr><?php endforeach; ?></tbody></table></div>
<?php include __DIR__ . '/../includes/panel_footer.php'; ?>
