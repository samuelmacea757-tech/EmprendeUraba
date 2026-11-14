<?php
require_once __DIR__ . '/../includes/bootstrap.php';
require_login(['admin']);
$title = 'Administración'; $active = 'index'; $chart = true;
$s = [
 'Usuarios' => qv('SELECT COUNT(*) FROM usuarios'), 'Emprendedores' => qv("SELECT COUNT(*) FROM usuarios WHERE rol='emprendedor'"), 'Compradores' => qv("SELECT COUNT(*) FROM usuarios WHERE rol='comprador'"),
 'Emprendimientos' => qv('SELECT COUNT(*) FROM emprendimientos'), 'Productos/servicios' => qv('SELECT COUNT(*) FROM productos WHERE activo=1'), 'Pedidos' => qv('SELECT COUNT(*) FROM pedidos'),
 'Ventas (total $)' => money(qv('SELECT COALESCE(SUM(total),0) FROM ventas')), 'Reportes abiertos' => qv("SELECT COUNT(*) FROM problemas WHERE estado<>'resuelto'")];
$cat = qa('SELECT c.nombre, COUNT(e.id) t FROM categorias c LEFT JOIN emprendimientos e ON e.categoria_id=c.id GROUP BY c.id');
$reg = qa("SELECT DATE_FORMAT(creado_en,'%Y-%m') m, COUNT(*) t FROM usuarios GROUP BY m ORDER BY m DESC LIMIT 6");
$page_js = '<script>new Chart(document.getElementById("a1"),{type:"bar",data:{labels:' . json_encode(array_column($cat, 'nombre')) . ',datasets:[{label:"Emprendimientos",data:' . json_encode(array_map('intval', array_column($cat, 't'))) . ',backgroundColor:"#0f766e"}]}});
new Chart(document.getElementById("a2"),{type:"line",data:{labels:' . json_encode(array_reverse(array_column($reg, 'm'))) . ',datasets:[{label:"Usuarios nuevos",data:' . json_encode(array_map('intval', array_reverse(array_column($reg, 't')))) . ',borderColor:"#f59e0b"}]}});</script>';
include __DIR__ . '/../includes/panel_header.php';
?>
<h3>Estadísticas generales de la plataforma</h3>
<div class="row g-3 mb-4"><?php foreach ($s as $l => $v): ?><div class="col-6 col-lg-3"><div class="card stat-card shadow-sm"><div class="card-body"><div class="small text-muted"><?= $l ?></div><div class="num"><?= e($v) ?></div></div></div></div><?php endforeach; ?></div>
<div class="row g-3"><div class="col-lg-6"><div class="card card-body"><h6>Emprendimientos por categoría</h6><canvas id="a1"></canvas></div></div><div class="col-lg-6"><div class="card card-body"><h6>Registros por mes</h6><canvas id="a2"></canvas></div></div></div>
<?php include __DIR__ . '/../includes/panel_footer.php'; ?>
