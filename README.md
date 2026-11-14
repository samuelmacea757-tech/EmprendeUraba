# EmprendeUrabá

Plataforma web multipropósito para la gestión comercial de pequeños emprendimientos.
Stack: HTML5, CSS3, Bootstrap 5, JavaScript, PHP 8+ (PDO), MySQL, Apache, Chart.js, Dompdf.

## Instalación
1. Copia la carpeta `emprendeuraba` en `htdocs` (XAMPP) o `/var/www/html`.
2. Importa `database/schema.sql` (phpMyAdmin → Importar). Crea la BD `emprendeuraba` y datos iniciales.
3. Edita `config/config.php`: credenciales de BD y `BASE_URL` (`/emprendeuraba`, o `''` si está en la raíz).
4. (PDF) En la carpeta del proyecto ejecuta `composer install` para instalar Dompdf. Sin Dompdf, los PDF se abren como vista imprimible.
5. Da permisos de escritura a `uploads/`.
6. Entra a `http://localhost/emprendeuraba/`.

**Administrador inicial:** `admin@emprendeuraba.com` / `Admin12345` (cámbiala al ingresar, en *Mi cuenta*).
Para producción pon `APP_DEV` en `false` y configura el envío de correo (`mail()`/SMTP) para recuperar contraseñas.

## Estructura
```
config/      configuración y conexión PDO (consultas preparadas)
database/    schema.sql
includes/    bootstrap, auth/roles, helpers, lógica de ventas/inventario, layouts
assets/      css, js, imágenes
uploads/     imágenes subidas (sin ejecución de scripts)
auth/        login, registro, logout, recuperar y restablecer contraseña
comprador/   pedidos y reservas del comprador
panel/       panel del emprendedor (dashboard, negocio, productos, inventario, clientes,
             pedidos, ventas, finanzas, reservas, comprobantes, reportes, personalizar)
admin/       administración general (usuarios, negocios, categorías, problemas, parámetros, respaldos)
index.php, catalogo.php, emprendimiento.php, producto.php   páginas públicas
pedido_crear.php, reservar.php, comprobante.php, cuenta.php, notificaciones.php, reportar.php
```

## Flujo principal
Comprador hace pedido → emprendedor lo confirma → se crea cliente + venta, se descuenta inventario →
se registran pagos (generan ingresos) → comprobante imprimible/PDF → reportes y dashboard.

## Seguridad implementada
Contraseñas con `password_hash`; consultas preparadas; escape de salida (XSS); token CSRF en formularios y acciones;
roles (admin/emprendedor/comprador) y aislamiento por `negocio_id`; sesiones con cookies HttpOnly/SameSite, regeneración de ID
y cierre remoto de sesiones; bloqueo temporal tras 5 intentos fallidos; validación de imágenes (MIME real, tamaño, nombre aleatorio);
bitácora de actividad; respaldo SQL descargable (Admin → Supervisión y respaldos).
