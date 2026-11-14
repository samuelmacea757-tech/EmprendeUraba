-- EmprendeUrabá - Esquema de base de datos (MySQL 8 / MariaDB 10.4+)
CREATE DATABASE IF NOT EXISTS emprendeuraba CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE emprendeuraba;

CREATE TABLE usuarios (
  id INT AUTO_INCREMENT PRIMARY KEY,
  nombre VARCHAR(80) NOT NULL,
  apellido VARCHAR(80) NOT NULL,
  email VARCHAR(150) NOT NULL UNIQUE,
  telefono VARCHAR(30) NULL,
  password_hash VARCHAR(255) NOT NULL,
  rol ENUM('admin','emprendedor','comprador') NOT NULL DEFAULT 'comprador',
  foto VARCHAR(255) NULL,
  estado ENUM('activo','suspendido','desactivado') NOT NULL DEFAULT 'activo',
  session_token VARCHAR(64) NULL,
  pref_notif TINYINT(1) NOT NULL DEFAULT 1,
  creado_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE password_resets (
  id INT AUTO_INCREMENT PRIMARY KEY,
  usuario_id INT NOT NULL,
  token_hash CHAR(64) NOT NULL,
  expira DATETIME NOT NULL,
  usado TINYINT(1) NOT NULL DEFAULT 0,
  FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE categorias (
  id INT AUTO_INCREMENT PRIMARY KEY,
  nombre VARCHAR(80) NOT NULL,
  icono VARCHAR(40) NOT NULL DEFAULT 'bi-shop',
  activo TINYINT(1) NOT NULL DEFAULT 1
) ENGINE=InnoDB;

CREATE TABLE emprendimientos (
  id INT AUTO_INCREMENT PRIMARY KEY,
  usuario_id INT NOT NULL UNIQUE,
  nombre VARCHAR(120) NOT NULL,
  descripcion TEXT NULL,
  categoria_id INT NULL,
  logo VARCHAR(255) NULL,
  portada VARCHAR(255) NULL,
  telefono VARCHAR(30) NULL,
  email VARCHAR(150) NULL,
  direccion VARCHAR(200) NULL,
  ciudad VARCHAR(80) NULL,
  horario TEXT NULL,
  facebook VARCHAR(200) NULL,
  instagram VARCHAR(200) NULL,
  whatsapp VARCHAR(30) NULL,
  metodos_pago VARCHAR(255) NOT NULL DEFAULT 'Efectivo',
  color_primario VARCHAR(7) NOT NULL DEFAULT '#0f766e',
  tipo_oferta ENUM('productos','servicios','ambos') NOT NULL DEFAULT 'ambos',
  modulos VARCHAR(255) NULL,
  activo TINYINT(1) NOT NULL DEFAULT 1,
  suspendido TINYINT(1) NOT NULL DEFAULT 0,
  creado_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE,
  FOREIGN KEY (categoria_id) REFERENCES categorias(id) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE categorias_propias (
  id INT AUTO_INCREMENT PRIMARY KEY,
  negocio_id INT NOT NULL,
  nombre VARCHAR(80) NOT NULL,
  FOREIGN KEY (negocio_id) REFERENCES emprendimientos(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE productos (
  id INT AUTO_INCREMENT PRIMARY KEY,
  negocio_id INT NOT NULL,
  tipo ENUM('producto','servicio') NOT NULL DEFAULT 'producto',
  nombre VARCHAR(150) NOT NULL,
  descripcion TEXT NULL,
  precio DECIMAL(12,2) NOT NULL DEFAULT 0,
  costo DECIMAL(12,2) NOT NULL DEFAULT 0,
  foto VARCHAR(255) NULL,
  categoria_id INT NULL,
  stock INT NOT NULL DEFAULT 0,
  stock_minimo INT NOT NULL DEFAULT 0,
  disponible TINYINT(1) NOT NULL DEFAULT 1,
  personalizado TINYINT(1) NOT NULL DEFAULT 0,
  talla VARCHAR(50) NULL, color VARCHAR(50) NULL, tamano VARCHAR(50) NULL, material VARCHAR(80) NULL,
  duracion_min INT NULL,
  publicado TINYINT(1) NOT NULL DEFAULT 1,
  destacado TINYINT(1) NOT NULL DEFAULT 0,
  activo TINYINT(1) NOT NULL DEFAULT 1,
  creado_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (negocio_id) REFERENCES emprendimientos(id) ON DELETE CASCADE,
  FOREIGN KEY (categoria_id) REFERENCES categorias_propias(id) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE producto_fotos (
  id INT AUTO_INCREMENT PRIMARY KEY,
  producto_id INT NOT NULL,
  archivo VARCHAR(255) NOT NULL,
  FOREIGN KEY (producto_id) REFERENCES productos(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE inventario_movimientos (
  id INT AUTO_INCREMENT PRIMARY KEY,
  negocio_id INT NOT NULL,
  producto_id INT NOT NULL,
  tipo ENUM('entrada','salida','ajuste') NOT NULL,
  cantidad INT NOT NULL,
  stock_resultante INT NOT NULL,
  motivo VARCHAR(200) NOT NULL,
  referencia VARCHAR(40) NULL,
  usuario_id INT NULL,
  creado_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (negocio_id) REFERENCES emprendimientos(id) ON DELETE CASCADE,
  FOREIGN KEY (producto_id) REFERENCES productos(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE compras_insumos (
  id INT AUTO_INCREMENT PRIMARY KEY,
  negocio_id INT NOT NULL,
  producto_id INT NULL,
  descripcion VARCHAR(200) NOT NULL,
  proveedor VARCHAR(120) NULL,
  cantidad INT NOT NULL DEFAULT 1,
  costo_total DECIMAL(12,2) NOT NULL,
  fecha DATE NOT NULL,
  FOREIGN KEY (negocio_id) REFERENCES emprendimientos(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE clientes (
  id INT AUTO_INCREMENT PRIMARY KEY,
  negocio_id INT NOT NULL,
  usuario_id INT NULL,
  nombres VARCHAR(80) NOT NULL,
  apellidos VARCHAR(80) NULL,
  telefono VARCHAR(30) NULL,
  email VARCHAR(150) NULL,
  direccion VARCHAR(200) NULL,
  observaciones TEXT NULL,
  creado_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (negocio_id) REFERENCES emprendimientos(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE pedidos (
  id INT AUTO_INCREMENT PRIMARY KEY,
  negocio_id INT NOT NULL,
  comprador_id INT NULL,
  cliente_id INT NULL,
  referencia VARCHAR(30) NOT NULL UNIQUE,
  nombre_comprador VARCHAR(160) NOT NULL,
  telefono VARCHAR(30) NULL,
  email VARCHAR(150) NULL,
  direccion_entrega VARCHAR(200) NULL,
  observaciones TEXT NULL,
  total DECIMAL(12,2) NOT NULL DEFAULT 0,
  estado ENUM('pendiente','confirmado','en_preparacion','listo','entregado','rechazado','cancelado') NOT NULL DEFAULT 'pendiente',
  fecha_solicitud DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  fecha_entrega DATE NULL,
  motivo VARCHAR(255) NULL,
  venta_id INT NULL,
  FOREIGN KEY (negocio_id) REFERENCES emprendimientos(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE pedido_items (
  id INT AUTO_INCREMENT PRIMARY KEY,
  pedido_id INT NOT NULL,
  producto_id INT NULL,
  nombre VARCHAR(150) NOT NULL,
  precio DECIMAL(12,2) NOT NULL,
  cantidad INT NOT NULL,
  observacion VARCHAR(255) NULL,
  FOREIGN KEY (pedido_id) REFERENCES pedidos(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE ventas (
  id INT AUTO_INCREMENT PRIMARY KEY,
  negocio_id INT NOT NULL,
  cliente_id INT NULL,
  pedido_id INT NULL,
  comprador_id INT NULL,
  referencia VARCHAR(30) NOT NULL UNIQUE,
  subtotal DECIMAL(12,2) NOT NULL,
  descuento DECIMAL(12,2) NOT NULL DEFAULT 0,
  total DECIMAL(12,2) NOT NULL,
  metodo_pago VARCHAR(60) NULL,
  estado_pago ENUM('pendiente','parcial','pagado') NOT NULL DEFAULT 'pendiente',
  monto_pagado DECIMAL(12,2) NOT NULL DEFAULT 0,
  notas VARCHAR(255) NULL,
  fecha DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (negocio_id) REFERENCES emprendimientos(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE venta_items (
  id INT AUTO_INCREMENT PRIMARY KEY,
  venta_id INT NOT NULL,
  producto_id INT NULL,
  nombre VARCHAR(150) NOT NULL,
  precio DECIMAL(12,2) NOT NULL,
  costo DECIMAL(12,2) NOT NULL DEFAULT 0,
  cantidad INT NOT NULL,
  subtotal DECIMAL(12,2) NOT NULL,
  FOREIGN KEY (venta_id) REFERENCES ventas(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE pagos (
  id INT AUTO_INCREMENT PRIMARY KEY,
  venta_id INT NOT NULL,
  negocio_id INT NOT NULL,
  monto DECIMAL(12,2) NOT NULL,
  metodo VARCHAR(60) NULL,
  fecha DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (venta_id) REFERENCES ventas(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE movimientos_financieros (
  id INT AUTO_INCREMENT PRIMARY KEY,
  negocio_id INT NOT NULL,
  tipo ENUM('ingreso','gasto') NOT NULL,
  categoria VARCHAR(80) NOT NULL,
  concepto VARCHAR(200) NOT NULL,
  monto DECIMAL(12,2) NOT NULL,
  fecha DATE NOT NULL,
  venta_id INT NULL,
  creado_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (negocio_id) REFERENCES emprendimientos(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE reservas (
  id INT AUTO_INCREMENT PRIMARY KEY,
  negocio_id INT NOT NULL,
  servicio_id INT NOT NULL,
  comprador_id INT NULL,
  nombre VARCHAR(160) NOT NULL,
  telefono VARCHAR(30) NULL,
  fecha DATE NOT NULL,
  hora TIME NOT NULL,
  estado ENUM('pendiente','aceptada','rechazada','cancelada','finalizada') NOT NULL DEFAULT 'pendiente',
  notas VARCHAR(255) NULL,
  creado_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (negocio_id) REFERENCES emprendimientos(id) ON DELETE CASCADE,
  FOREIGN KEY (servicio_id) REFERENCES productos(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE notificaciones (
  id INT AUTO_INCREMENT PRIMARY KEY,
  usuario_id INT NOT NULL,
  tipo VARCHAR(30) NOT NULL,
  mensaje VARCHAR(255) NOT NULL,
  enlace VARCHAR(255) NULL,
  leida TINYINT(1) NOT NULL DEFAULT 0,
  clave VARCHAR(80) NULL,
  creado_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_clave (usuario_id, clave),
  FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE problemas (
  id INT AUTO_INCREMENT PRIMARY KEY,
  usuario_id INT NOT NULL,
  asunto VARCHAR(150) NOT NULL,
  descripcion TEXT NOT NULL,
  estado ENUM('abierto','en_proceso','resuelto') NOT NULL DEFAULT 'abierto',
  respuesta TEXT NULL,
  creado_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE parametros (
  clave VARCHAR(60) PRIMARY KEY,
  valor VARCHAR(255) NOT NULL,
  descripcion VARCHAR(200) NULL
) ENGINE=InnoDB;

CREATE TABLE logs (
  id INT AUTO_INCREMENT PRIMARY KEY,
  usuario_id INT NULL,
  accion VARCHAR(60) NOT NULL,
  detalle VARCHAR(255) NULL,
  ip VARCHAR(45) NULL,
  creado_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- ===== Datos iniciales =====
INSERT INTO categorias (nombre, icono) VALUES
 ('Alimentos y bebidas','bi-cup-hot'),('Moda y accesorios','bi-bag'),('Belleza y cuidado personal','bi-flower1'),
 ('Artesanías','bi-palette'),('Tecnología y reparaciones','bi-laptop'),('Servicios profesionales','bi-briefcase'),
 ('Agro y campo','bi-tree'),('Hogar y construcción','bi-house'),('Educación y capacitación','bi-book'),('Otros','bi-grid');

INSERT INTO parametros (clave, valor, descripcion) VALUES
 ('contacto_email','contacto@emprendeuraba.com','Correo de contacto'),
 ('contacto_telefono','+57 300 000 0000','Teléfono de contacto'),
 ('contacto_direccion','Urabá, Antioquia, Colombia','Dirección'),
 ('descripcion','EmprendeUrabá reúne a los emprendedores de la región para que vendan, administren y hagan crecer su negocio desde un solo lugar.','Descripción general');

-- Administrador inicial (correo: admin@emprendeuraba.com / clave: Admin12345) -> CÁMBIALA al ingresar
INSERT INTO usuarios (nombre, apellido, email, password_hash, rol) VALUES
 ('Administrador','General','admin@emprendeuraba.com','$2y$10$NMQuZvapCDXGDcwe5c9.HeeksNlECaOGNP40A69orNKQpoBzY2/QS','admin');
