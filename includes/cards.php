<?php
/**
 * Tarjetas reutilizables para el catálogo público.
 *
 * Cada función devuelve el HTML de una tarjeta (columna de Bootstrap), de
 * modo que index.php y catalogo.php muestren los mismos componentes.
 */

/**
 * Indica si un producto o servicio no se puede pedir en este momento.
 * Los servicios nunca se "agotan" por existencias.
 */
function producto_esta_agotado(array $producto): bool
{
    $sin_existencias = $producto['tipo'] === 'producto' && $producto['stock'] <= 0;

    return !$producto['disponible'] || $sin_existencias;
}

/**
 * Tarjeta de un producto o servicio.
 *
 * @param array $producto Fila de productos con la clave 'negocio_nombre'.
 */
function product_card(array $producto): string
{
    if ($producto['tipo'] === 'servicio') {
        $etiqueta = '<span class="badge text-bg-info">Servicio</span>';
    } elseif (producto_esta_agotado($producto)) {
        $etiqueta = '<span class="badge text-bg-secondary">Agotado</span>';
    } else {
        $etiqueta = '<span class="badge text-bg-success">Disponible</span>';
    }

    $enlace_detalle = url('producto.php?id=' . $producto['id']);

    $html = '<div class="col-6 col-md-4 col-lg-3"><div class="card h-100 shadow-sm card-hover">';
    $html .= '<a href="' . $enlace_detalle . '"><img src="' . img($producto['foto']) . '" class="card-img-top thumb" alt="' . e($producto['nombre']) . '"></a>';
    $html .= '<div class="card-body d-flex flex-column">';
    $html .= '<div class="small text-muted">' . e($producto['negocio_nombre'] ?? '') . '</div>';
    $html .= '<h6 class="card-title">' . e($producto['nombre']) . '</h6>';
    $html .= '<div class="mt-auto d-flex justify-content-between align-items-center">';
    $html .= '<strong>' . money($producto['precio']) . '</strong>' . $etiqueta;
    $html .= '</div></div></div></div>';

    return $html;
}

/**
 * Tarjeta de un emprendimiento.
 *
 * @param array $negocio Fila de emprendimientos con la clave 'categoria'.
 */
function negocio_card(array $negocio): string
{
    $enlace_negocio = url('emprendimiento.php?id=' . $negocio['id']);
    $descripcion_corta = mb_strimwidth((string) $negocio['descripcion'], 0, 90, '…');

    $html = '<div class="col-12 col-md-6 col-lg-4">';
    $html .= '<a class="text-decoration-none text-reset" href="' . $enlace_negocio . '">';
    $html .= '<div class="card h-100 shadow-sm card-hover">';
    $html .= '<img src="' . img($negocio['portada']) . '" class="card-img-top cover-sm" alt="">';
    $html .= '<div class="card-body d-flex gap-3">';
    $html .= '<img src="' . img($negocio['logo'], 'assets/img/logo.svg') . '" class="logo-sm rounded-circle border" alt="">';
    $html .= '<div><h6 class="mb-1">' . e($negocio['nombre']) . '</h6>';
    $html .= '<div class="small text-muted mb-1">' . e($negocio['categoria'] ?? 'Sin categoría') . ' · ' . e($negocio['ciudad'] ?? '') . '</div>';
    $html .= '<p class="small mb-0">' . e($descripcion_corta) . '</p>';
    $html .= '</div></div></div></a></div>';

    return $html;
}