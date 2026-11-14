<?php
/**
 * Comprobante de pago de una venta: se puede ver, imprimir o descargar en PDF.
 *
 * Acceso: solo el emprendedor dueño del negocio o el comprador de esa venta.
 * Parámetros GET: id (de la venta), pdf=1 (descarga en PDF).
 */

require_once __DIR__ . '/includes/bootstrap.php';

$usuario = require_login(['emprendedor', 'comprador']);

$venta = consultar_uno(
    'SELECT v.*, e.nombre AS negocio, e.direccion, e.ciudad, e.telefono AS negocio_telefono,
            e.email AS negocio_email, e.usuario_id AS dueno_id, e.color_primario,
            c.nombres, c.apellidos, c.telefono AS cliente_telefono, c.email AS cliente_email
     FROM ventas v
     JOIN emprendimientos e ON e.id = v.negocio_id
     LEFT JOIN clientes c ON c.id = v.cliente_id
     WHERE v.id = ?',
    [(int) get('id')]
);

// Control de acceso: dueño del negocio o comprador de la venta.
$es_dueno = $venta && $usuario['rol'] === 'emprendedor' && (int) $venta['dueno_id'] === (int) $usuario['id'];
$es_comprador = $venta && $usuario['rol'] === 'comprador' && (int) $venta['comprador_id'] === (int) $usuario['id'];
if (!$es_dueno && !$es_comprador) {
    http_response_code(403);
    exit('Acceso denegado.');
}

$items = consultar_todos('SELECT * FROM venta_items WHERE venta_id = ?', [$venta['id']]);
$nombre_cliente = trim(($venta['nombres'] ?? '') . ' ' . ($venta['apellidos'] ?? '')) ?: 'Cliente general';

// El HTML del comprobante se arma una vez y se reutiliza en pantalla y en PDF.
ob_start();
?>
<div style="font-family:Arial,sans-serif;max-width:700px;margin:auto;color:#222">
  <table width="100%">
    <tr>
      <td>
        <h2 style="margin:0;color:<?= e($venta['color_primario']) ?>"><?= e($venta['negocio']) ?></h2>
        <div style="font-size:12px">
          <?= e($venta['direccion']) ?> <?= e($venta['ciudad']) ?><br>
          <?= e($venta['negocio_telefono']) ?> <?= e($venta['negocio_email']) ?>
        </div>
      </td>
      <td align="right">
        <h3 style="margin:0">COMPROBANTE DE PAGO</h3>
        <div style="font-size:13px">N.º <strong><?= e($venta['referencia']) ?></strong><br>Fecha: <?= fdate($venta['fecha'], true) ?></div>
      </td>
    </tr>
  </table>
  <hr>
  <div style="font-size:13px">
    <strong>Cliente:</strong> <?= e($nombre_cliente) ?> <?= e($venta['cliente_telefono'] ?? '') ?> <?= e($venta['cliente_email'] ?? '') ?>
  </div>
  <table width="100%" cellpadding="6" style="border-collapse:collapse;margin-top:12px;font-size:13px">
    <tr style="background:#f1f5f9">
      <th align="left">Producto / servicio</th><th>Cant.</th><th align="right">Precio</th><th align="right">Subtotal</th>
    </tr>
    <?php foreach ($items as $item): ?>
      <tr style="border-bottom:1px solid #e2e8f0">
        <td><?= e($item['nombre']) ?></td>
        <td align="center"><?= $item['cantidad'] ?></td>
        <td align="right"><?= money($item['precio']) ?></td>
        <td align="right"><?= money($item['subtotal']) ?></td>
      </tr>
    <?php endforeach; ?>
    <tr><td colspan="3" align="right">Subtotal</td><td align="right"><?= money($venta['subtotal']) ?></td></tr>
    <tr><td colspan="3" align="right">Descuento</td><td align="right">-<?= money($venta['descuento']) ?></td></tr>
    <tr><td colspan="3" align="right"><strong>TOTAL</strong></td><td align="right"><strong><?= money($venta['total']) ?></strong></td></tr>
  </table>
  <p style="font-size:13px">
    <strong>Método de pago:</strong> <?= e($venta['metodo_pago'] ?: '—') ?> &nbsp;
    <strong>Estado del pago:</strong> <?= e(strtoupper($venta['estado_pago'])) ?> (pagado <?= money($venta['monto_pagado']) ?>)
  </p>
  <p style="font-size:11px;color:#64748b;text-align:center">Generado con <?= APP_NAME ?></p>
</div>
<?php
$html_comprobante = ob_get_clean();

if (get('pdf')) {
    render_pdf(
        '<html><head><meta charset="UTF-8"></head><body>' . $html_comprobante . '</body></html>',
        'comprobante-' . $venta['referencia'] . '.pdf'
    );
}

$title = 'Comprobante ' . $venta['referencia'];
$active = 'comprobantes';
include __DIR__ . '/includes/panel_header.php';
?>
<div class="no-print mb-3 d-flex gap-2">
  <button class="btn btn-brand" onclick="window.print()"><i class="bi bi-printer"></i> Imprimir</button>
  <a class="btn btn-outline-danger" href="?id=<?= $venta['id'] ?>&pdf=1"><i class="bi bi-file-earmark-pdf"></i> Descargar PDF</a>
  <a class="btn btn-outline-secondary" href="<?= url($usuario['rol'] === 'emprendedor' ? 'panel/comprobantes.php' : 'comprador/index.php') ?>">Volver</a>
</div>
<div class="card"><div class="card-body"><?= $html_comprobante ?></div></div>
<?php include __DIR__ . '/includes/panel_footer.php'; ?>