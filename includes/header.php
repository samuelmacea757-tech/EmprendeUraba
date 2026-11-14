
<?php
$u = current_user();
$title = $title ?? APP_NAME;

// Color principal de emprendeuraba
$color = $brand_color ?? '#0B2D6B';
?>

<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <title><?= e($title) ?> · <?= APP_NAME ?></title>

    <!-- Favicon -->
    <link rel="icon" href="<?= url('assets/img/Logo_renovado.png') ?>">

    <!-- Bootstrap 5 -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
          rel="stylesheet">

    <!-- Bootstrap Icons -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css"
          rel="stylesheet">

    <!-- Fuentes -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>

    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;500;600;700;800&family=Roboto:wght@400;500;700&display=swap"
          rel="stylesheet">

    <!-- CSS principal de emprendeuraba -->
    <link href="<?= url('assets/css/style.css') ?>" rel="stylesheet">

    <!-- Color dinámico de marca -->
    <style>
        :root {
            --brand: <?= e($color) ?>;
        }
    </style>
</head>

<body>

<!-- ==================================
     BARRA DE NAVEGACIÓN
=================================== -->

<nav class="navbar navbar-expand-lg navbar-dark bg-brand sticky-top shadow-sm">

    <div class="container">

        <!-- LOGOTIPO -->
        <a class="navbar-brand d-flex align-items-center gap-2 fw-bold"
           href="<?= url('index.php') ?>">

            <img src="<?= url('assets/img/Logo_renovado.png') ?>"
                 width="42"
                 height="42"
                 alt="Logo emprendeuraba">

            <span><?= APP_NAME ?></span>

        </a>

        <!-- BOTÓN MÓVIL -->
        <button class="navbar-toggler"
                type="button"
                data-bs-toggle="collapse"
                data-bs-target="#nav"
                aria-controls="nav"
                aria-expanded="false"
                aria-label="Abrir menú">

            <span class="navbar-toggler-icon"></span>

        </button>

        <!-- MENÚ PRINCIPAL -->
        <div id="nav" class="collapse navbar-collapse">

            <ul class="navbar-nav me-auto mb-2 mb-lg-0">

                <li class="nav-item">
                    <a class="nav-link"
                       href="<?= url('index.php') ?>">

                        <i class="bi bi-house-door"></i>
                        Inicio

                    </a>
                </li>

                <li class="nav-item">
                    <a class="nav-link"
                       href="<?= url('catalogo.php?t=emprendimientos') ?>">

                        <i class="bi bi-shop"></i>
                        Emprendimientos

                    </a>
                </li>

                <li class="nav-item">
                    <a class="nav-link"
                       href="<?= url('catalogo.php?t=productos') ?>">

                        <i class="bi bi-bag"></i>
                        Productos y servicios

                    </a>
                </li>

                <li class="nav-item">
                    <a class="nav-link"
                       href="<?= url('index.php#contacto') ?>">

                        <i class="bi bi-envelope"></i>
                        Contacto

                    </a>
                </li>

            </ul>

            <!-- OPCIONES DE USUARIO -->
            <div class="d-flex flex-wrap gap-2 align-items-center">

                <?php if ($u): ?>

                    <a class="btn btn-light btn-sm"
                       href="<?= url(home_for($u)) ?>">

                        <i class="bi bi-speedometer2"></i>
                        Mi panel

                    </a>

                    <a class="btn btn-outline-light btn-sm"
                       href="<?= url('auth/logout.php') ?>">

                        <i class="bi bi-box-arrow-right"></i>
                        Salir

                    </a>

                <?php else: ?>

                    <a class="btn btn-outline-light btn-sm"
                       href="<?= url('auth/login.php') ?>">

                        <i class="bi bi-person"></i>
                        Iniciar sesión

                    </a>

                    <a class="btn btn-warning btn-sm"
                       href="<?= url('auth/registro.php') ?>">

                        <i class="bi bi-person-plus"></i>
                        Registrarse

                    </a>

                <?php endif; ?>

            </div>

        </div>

    </div>

</nav>

<!-- ==================================
     CONTENIDO PRINCIPAL
=================================== -->

<main class="container py-4">

    <!-- MENSAJES DEL SISTEMA -->

    <?php foreach (flash() as [$t, $m]): ?>

        <div class="alert alert-<?= e($t) ?> alert-dismissible fade show"
             role="alert">

            <?= e($m) ?>

            <button type="button"
                    class="btn-close"
                    data-bs-dismiss="alert"
                    aria-label="Cerrar">
            </button>

        </div>

    <?php endforeach; ?>
