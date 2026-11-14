
<?php
// ==========================================
// CONFIGURACIÓN GENERAL DEL PANEL
// ==========================================

$u = current_user();
$title = $title ?? 'Panel';
$active = $active ?? '';

// Obtener negocio del emprendedor
$N = $u['rol'] === 'emprendedor' ? negocio_actual() : null;

// Color personalizado del negocio o color oficial
$brand_color = $N['color_primario'] ?? '#0B2D6B';

// ==========================================
// MENÚ DEL ADMINISTRADOR
// ==========================================

if ($u['rol'] === 'admin') {

    $menu = [
        ['admin/index.php','bi-speedometer2','Resumen','index'],
        ['admin/usuarios.php','bi-people','Usuarios','usuarios'],
        ['admin/emprendimientos.php','bi-shop','Emprendimientos','emprendimientos'],
        ['admin/categorias.php','bi-tags','Categorías','categorias'],
        ['admin/problemas.php','bi-bug','Reportes de problemas','problemas'],
        ['admin/parametros.php','bi-sliders','Parámetros','parametros'],
        ['admin/sistema.php','bi-hdd-network','Supervisión y respaldos','sistema']
    ];

// ==========================================
// MENÚ DEL EMPRENDEDOR
// ==========================================

} elseif ($u['rol'] === 'emprendedor') {

    $menu = [
        ['panel/index.php','bi-speedometer2','Resumen','index'],
        ['panel/negocio.php','bi-shop','Mi negocio','negocio'],
        ['panel/productos.php','bi-box-seam','Productos y servicios','productos']
    ];

    foreach ([
        ['inventario','panel/inventario.php','bi-boxes','Inventario'],
        ['clientes','panel/clientes.php','bi-people','Clientes'],
        ['pedidos','panel/pedidos.php','bi-bag-check','Pedidos'],
        ['ventas','panel/ventas.php','bi-cash-coin','Ventas y pagos'],
        ['finanzas','panel/finanzas.php','bi-graph-up-arrow','Finanzas'],
        ['reservas','panel/reservas.php','bi-calendar-check','Reservas']
    ] as [$m,$h,$i,$l]) {

        if ($N && modulo_activo($N, $m)) {
            $menu[] = [$h,$i,$l,basename($h,'.php')];
        }
    }

    if ($N && modulo_activo($N,'ventas')) {
        $menu[] = [
            'panel/comprobantes.php',
            'bi-receipt',
            'Comprobantes',
            'comprobantes'
        ];
    }

    if ($N && modulo_activo($N,'reportes')) {
        $menu[] = [
            'panel/reportes.php',
            'bi-file-earmark-bar-graph',
            'Reportes',
            'reportes'
        ];
    }

    $menu[] = [
        'panel/personalizar.php',
        'bi-palette',
        'Personalizar',
        'personalizar'
    ];

// ==========================================
// MENÚ DEL COMPRADOR
// ==========================================

} else {

    $menu = [
        ['comprador/index.php','bi-bag','Mis pedidos y reservas','index']
    ];
}

// ==========================================
// OPCIONES GENERALES
// ==========================================

$menu[] = [
    'notificaciones.php',
    'bi-bell',
    'Notificaciones',
    'notificaciones'
];

$menu[] = [
    'reportar.php',
    'bi-flag',
    'Reportar un problema',
    'reportar'
];

$menu[] = [
    'cuenta.php',
    'bi-person-gear',
    'Mi cuenta',
    'cuenta'
];

// Cantidad de notificaciones
$nc = notif_count((int)$u['id']);

?>

<!DOCTYPE html>
<html lang="es">

<head>

    <meta charset="UTF-8">

    <meta name="viewport"
          content="width=device-width, initial-scale=1">

    <title><?= e($title) ?> · <?= APP_NAME ?></title>

    <!-- Favicon -->
    <link rel="icon"
          href="<?= url('assets/img/Logo_renovado.png') ?>">

    <!-- Bootstrap 5 -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
          rel="stylesheet">

    <!-- Bootstrap Icons -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css"
          rel="stylesheet">

    <!-- Tipografías -->
    <link rel="preconnect" href="https://fonts.googleapis.com">

    <link rel="preconnect"
          href="https://fonts.gstatic.com"
          crossorigin>

    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;500;600;700;800&family=Roboto:wght@400;500;700&display=swap"
          rel="stylesheet">

    <!-- CSS principal -->
    <link href="<?= url('assets/css/style.css') ?>"
          rel="stylesheet">

    <!-- Color personalizado -->
    <style>
        :root {
            --brand: <?= e($brand_color) ?>;
        }
    </style>

</head>

<body class="bg-light">

<!-- ==========================================
     BARRA SUPERIOR
=========================================== -->

<nav class="navbar navbar-dark bg-brand sticky-top shadow-sm">

    <div class="container-fluid">

        <!-- Botón menú móvil -->
        <button class="btn btn-outline-light btn-sm d-lg-none me-2"
                type="button"
                data-bs-toggle="offcanvas"
                data-bs-target="#side"
                aria-controls="side">

            <i class="bi bi-list"></i>

        </button>

        <!-- Logotipo -->
        <a class="navbar-brand fw-bold me-auto d-flex align-items-center"
           href="<?= url('index.php') ?>">

            <img src="<?= url('assets/img/Logo_renovado.png') ?>"
                 width="36"
                 height="36"
                 class="me-2"
                 alt="Logo emprendeuraba">

            <span><?= APP_NAME ?></span>

        </a>

        <!-- Notificaciones -->
        <a class="btn btn-sm btn-outline-light me-2 position-relative"
           href="<?= url('notificaciones.php') ?>">

            <i class="bi bi-bell"></i>

            <?php if ($nc): ?>

                <span class="badge bg-danger position-absolute top-0 start-100 translate-middle">

                    <?= $nc ?>

                </span>

            <?php endif; ?>

        </a>

        <!-- Información del usuario -->
        <span class="text-white me-3 d-none d-md-inline small">

            <?= e($u['nombre']) ?>

            <span class="badge bg-light text-dark">

                <?= e($u['rol']) ?>

            </span>

        </span>

        <!-- Cerrar sesión -->
        <a class="btn btn-sm btn-light"
           href="<?= url('auth/logout.php') ?>">

            <i class="bi bi-box-arrow-right"></i>
            Salir

        </a>

    </div>

</nav>

<!-- ==========================================
     ESTRUCTURA DEL PANEL
=========================================== -->

<div class="container-fluid">

    <div class="row">

        <!-- ==================================
             MENÚ LATERAL
        =================================== -->

        <div class="offcanvas-lg offcanvas-start col-lg-2 p-0 bg-white border-end sidebar"
             id="side">

            <!-- Encabezado móvil -->
            <div class="offcanvas-header d-lg-none">

                <h6 class="fw-bold text-brand">
                    Menú de navegación
                </h6>

                <button class="btn-close"
                        data-bs-dismiss="offcanvas"
                        data-bs-target="#side">
                </button>

            </div>

            <!-- Opciones del menú -->
            <div class="list-group list-group-flush w-100 pt-lg-3">

                <?php foreach ($menu as [$h,$i,$l,$k]): ?>

                    <a href="<?= url($h) ?>"
                       class="list-group-item list-group-item-action d-flex align-items-center
                       <?= $active === $k ? 'active-menu' : '' ?>">

                        <i class="bi <?= $i ?> me-2"></i>

                        <?= e($l) ?>

                    </a>

                <?php endforeach; ?>

                <!-- Ver catálogo del negocio -->
                <?php if ($N): ?>

                    <a class="list-group-item list-group-item-action text-success"
                       target="_blank"
                       rel="noopener noreferrer"
                       href="<?= url('emprendimiento.php?id=' . $N['id']) ?>">

                        <i class="bi bi-box-arrow-up-right me-2"></i>

                        Ver mi catálogo

                    </a>

                <?php endif; ?>

            </div>

        </div>

        <!-- ==================================
             CONTENIDO PRINCIPAL
        =================================== -->

        <main class="col-lg-10 p-3 p-md-4">

            <!-- Mensajes del sistema -->

            <?php foreach (flash() as [$t, $m]): ?>

                <div class="alert alert-<?= e($t) ?> alert-dismissible fade show"
                     role="alert">

                    <?= e($m) ?>

                    <button class="btn-close"
                            data-bs-dismiss="alert"
                            aria-label="Cerrar">
                    </button>

                </div>

            <?php endforeach; ?>
