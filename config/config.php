<?php
/**
 * Configuración general de EmprendeUrabá.
 *
 * Es el único archivo que se debe editar al instalar el sistema en otro
 * servidor. Todas las constantes se escriben en MAYÚSCULAS.
 */

// --- Conexión a la base de datos (MySQL) ---
define('DB_HOST', 'localhost');
define('DB_NAME', 'emprendeuraba');
define('DB_USER', 'root');
define('DB_PASS', '');

// Ruta base de la aplicación en el servidor: '/emprendeuraba' si está en una
// subcarpeta de htdocs, o '' si está en la raíz del dominio.
define('BASE_URL', '/emprendeuraba');

define('APP_NAME', 'EmprendeUrabá');

// true: muestra en pantalla el enlace de recuperación de contraseña (útil
// cuando no hay servidor de correo). En producción debe ser false.
define('APP_DEV', true);

// --- Carga de imágenes ---
define('UPLOAD_DIR', __DIR__ . '/../uploads/');
define('UPLOAD_MAX_BYTES', 2 * 1024 * 1024); // 2 MB

date_default_timezone_set('America/Bogota');