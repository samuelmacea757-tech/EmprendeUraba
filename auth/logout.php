<?php
/**
 * Cierre de sesión.
 *
 * Destruye la sesión actual, abre una nueva solo para mostrar el aviso y
 * vuelve a la página principal.
 */

require_once __DIR__ . '/../includes/bootstrap.php';

log_activity('logout');

$_SESSION = [];
session_destroy();
session_start();

flash('success', 'Sesión cerrada.');
redirect('index.php');