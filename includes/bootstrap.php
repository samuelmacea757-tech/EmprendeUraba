<?php
/**
 * Arranque común del sistema.
 *
 * Es la primera línea de cada página: inicia la sesión de forma segura,
 * envía cabeceras de seguridad y carga la configuración, la base de datos
 * y las funciones compartidas.
 */

require_once __DIR__ . '/../config/config.php';

// Segundos de inactividad tras los cuales se cierra la sesión (2 horas).
const TIEMPO_MAXIMO_INACTIVIDAD = 7200;

if (session_status() === PHP_SESSION_NONE) {
    session_name('EUSESS');
    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/',
        'httponly' => true,       // la cookie no es accesible desde JavaScript
        'samesite' => 'Lax',      // reduce el riesgo de CSRF
        'secure' => !empty($_SERVER['HTTPS']),
    ]);
    session_start();

    // Expiración por inactividad.
    $ultima_actividad = $_SESSION['last'] ?? null;
    if ($ultima_actividad !== null && time() - $ultima_actividad > TIEMPO_MAXIMO_INACTIVIDAD) {
        session_unset();
        session_destroy();
        session_start();
    }
    $_SESSION['last'] = time();
}

header('X-Frame-Options: SAMEORIGIN');
header('X-Content-Type-Options: nosniff');

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/functions.php';
require_once __DIR__ . '/auth.php';