-- 1) Tablas base (sin dependencias)
CREATE TABLE estado (
  id_estado INT(11) NOT NULL AUTO_INCREMENT,
  nombre_estado VARCHAR(20) NOT NULL,
  descripcion_estado VARCHAR(100) NOT NULL,
  PRIMARY KEY (id_estado)
);

CREATE TABLE estados_inscripciones (
  id_estado INT(11) NOT NULL AUTO_INCREMENT,
  nombre_estado VARCHAR(50) NOT NULL,
  PRIMARY KEY (id_estado)
);

CREATE TABLE permisos (
  id_permiso INT(11) NOT NULL AUTO_INCREMENT,
  nombre_permiso VARCHAR(20) NOT NULL,
  descripcion_permiso VARCHAR(100) NOT NULL,
  PRIMARY KEY (id_permiso)
);

CREATE TABLE modalidades (
  id_modalidad INT(11) NOT NULL AUTO_INCREMENT,
  nombre_modalidad VARCHAR(255) NOT NULL,
  descripcion_modalidad TEXT DEFAULT NULL,
  PRIMARY KEY (id_modalidad)
);

CREATE TABLE cursos (
  id_curso INT(11) NOT NULL AUTO_INCREMENT,
  nombre_curso VARCHAR(100) NOT NULL,
  descripcion_curso VARCHAR(255) NOT NULL,
  duracion INT(10) NOT NULL,
  objetivos VARCHAR(100) NOT NULL,
  id_complejidad INT(11) DEFAULT NULL,
  PRIMARY KEY (id_curso)
);

CREATE TABLE banner (
  id_banner INT(11) NOT NULL AUTO_INCREMENT,
  nombre_banner VARCHAR(100) NOT NULL,
  imagen_banner VARCHAR(100) NOT NULL,
  PRIMARY KEY (id_banner)
);

-- VISTA MATERIALIZADA/tabla auxiliar sin PK definida en el dump
CREATE TABLE v_cursos_rrhh (
  id_usuario INT(11) DEFAULT NULL,
  id_curso INT(11) DEFAULT NULL,
  tipo_curso VARCHAR(13) DEFAULT NULL,
  cantidad BIGINT(21) DEFAULT NULL
);

-- 2) Usuarios (depende de estado y permisos)
CREATE TABLE usuarios (
  id_usuario INT(11) NOT NULL AUTO_INCREMENT,
  email VARCHAR(50) NOT NULL,
  nombre VARCHAR(255) DEFAULT NULL,
  apellido VARCHAR(255) DEFAULT NULL,
  telefono VARCHAR(50) DEFAULT NULL,
  clave VARCHAR(60) NOT NULL,
  id_estado INT(11) NOT NULL,
  id_permiso INT(11) NOT NULL,
  token_verificacion VARCHAR(64) CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL,
  token_expiracion DATETIME DEFAULT NULL,
  verificado TINYINT(1) UNSIGNED NOT NULL DEFAULT 0,
  google_sub VARCHAR(255) DEFAULT NULL,
  PRIMARY KEY (id_usuario),
  UNIQUE KEY uq_usuarios_email (email),
  UNIQUE KEY google_sub (google_sub),
  KEY fk_id_estado_usuario (id_estado),
  KEY fk_permisos (id_permiso),
  CONSTRAINT fk_id_estado_usuario FOREIGN KEY (id_estado) REFERENCES estado (id_estado),
  CONSTRAINT fk_permisos FOREIGN KEY (id_permiso) REFERENCES permisos (id_permiso)
);

-- 3) Tablas auxiliares sin FKs en el dump (se crean ahora para disponibilidad)
CREATE TABLE curso_modalidad (
  id_curso INT(11) NOT NULL,
  id_modalidad INT(11) NOT NULL
  -- El dump no trae FKs ni PK aquí; se respeta tal cual
);

CREATE TABLE compra_items (
  id_item INT(11) NOT NULL AUTO_INCREMENT,
  id_compra INT(11) NOT NULL,
  id_curso INT(11) NOT NULL,
  id_modalidad INT(11) DEFAULT NULL,
  cantidad INT(11) NOT NULL DEFAULT 1,
  precio_unitario DECIMAL(10,2) NOT NULL,
  titulo_snapshot VARCHAR(150) DEFAULT NULL,
  descripcion_snapshot TEXT DEFAULT NULL,
  creado_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP(),
  PRIMARY KEY (id_item)
  -- El dump no declara FKs aquí; se respeta tal cual
);

CREATE TABLE empresa_trabajadores (
  id INT(11) NOT NULL AUTO_INCREMENT,
  id_empresa INT(11) NOT NULL,
  id_trabajador INT(11) NOT NULL,
  asignado_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP(),
  PRIMARY KEY (id)
  -- Sin FKs en el dump
);

-- 4) Históricos/precios que refieren a cursos
CREATE TABLE curso_precio_hist (
  id INT(11) NOT NULL AUTO_INCREMENT,
  id_curso INT(11) NOT NULL,
  precio DECIMAL(10,2) NOT NULL,
  moneda CHAR(3) NOT NULL DEFAULT 'ARS',
  vigente_desde DATETIME NOT NULL,
  vigente_hasta DATETIME DEFAULT NULL,
  comentario VARCHAR(255) DEFAULT NULL,
  creado_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP(),
  KEY FK_id_curso (id_curso),
  PRIMARY KEY (id),
  CONSTRAINT FK_id_curso FOREIGN KEY (id_curso) REFERENCES cursos (id_curso)
);

-- 5) Checkouts (dependen de usuarios/cursos/estados_inscripciones)
CREATE TABLE checkout_capacitaciones (
  id_capacitacion INT(11) NOT NULL AUTO_INCREMENT,
  creado_por INT(11) DEFAULT NULL,
  id_curso INT(11) NOT NULL,
  nombre VARCHAR(100) DEFAULT NULL,
  apellido VARCHAR(100) DEFAULT NULL,
  email VARCHAR(150) DEFAULT NULL,
  telefono VARCHAR(50) DEFAULT NULL,
  dni VARCHAR(40) DEFAULT NULL,
  direccion VARCHAR(200) DEFAULT NULL,
  ciudad VARCHAR(120) DEFAULT NULL,
  provincia VARCHAR(120) DEFAULT NULL,
  pais VARCHAR(100) DEFAULT NULL,
  acepta_tyc TINYINT(1) NOT NULL DEFAULT 0,
  precio_total DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  moneda VARCHAR(10) NOT NULL DEFAULT 'ARS',
  id_estado INT(11) NOT NULL DEFAULT 1,
  creado_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP(),
  PRIMARY KEY (id_capacitacion),
  KEY fk_checkout_creado_por (creado_por),
  KEY fk_checkout_id_curso (id_curso),
  KEY fk_checkout_id_estado (id_estado),
  CONSTRAINT fk_checkout_creado_por FOREIGN KEY (creado_por) REFERENCES usuarios (id_usuario),
  CONSTRAINT fk_checkout_id_curso FOREIGN KEY (id_curso) REFERENCES cursos (id_curso),
  CONSTRAINT fk_checkout_id_estado FOREIGN KEY (id_estado) REFERENCES estados_inscripciones (id_estado)
);

CREATE TABLE checkout_certificaciones (
  id_certificacion INT(11) NOT NULL AUTO_INCREMENT,
  creado_por INT(11) DEFAULT NULL,
  acepta_tyc TINYINT(1) NOT NULL DEFAULT 0,
  precio_total DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  moneda VARCHAR(10) NOT NULL DEFAULT 'ARS',
  id_curso INT(11) NOT NULL,
  pdf_path VARCHAR(255) DEFAULT NULL,
  pdf_nombre VARCHAR(255) DEFAULT NULL,
  pdf_mime VARCHAR(120) DEFAULT 'application/pdf',
  observaciones VARCHAR(255) DEFAULT NULL,
  id_estado INT(11) NOT NULL DEFAULT 1,
  creado_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP(),
  nombre VARCHAR(100) DEFAULT NULL,
  apellido VARCHAR(100) DEFAULT NULL,
  email VARCHAR(255) DEFAULT NULL,
  telefono VARCHAR(30) DEFAULT NULL,
  PRIMARY KEY (id_certificacion),
  KEY fk_certificaciones_usuarios (creado_por),
  KEY fk_certificaciones_cursos (id_curso),
  KEY fk_certificaciones_estados (id_estado),
  CONSTRAINT fk_certificaciones_usuarios FOREIGN KEY (creado_por) REFERENCES usuarios (id_usuario),
  CONSTRAINT fk_certificaciones_cursos FOREIGN KEY (id_curso) REFERENCES cursos (id_curso),
  CONSTRAINT fk_certificaciones_estados FOREIGN KEY (id_estado) REFERENCES estados_inscripciones (id_estado)
);

-- 6) Pagos (dependen de checkouts)
CREATE TABLE checkout_pagos (
  id_pago INT(11) NOT NULL AUTO_INCREMENT,
  id_certificacion INT(11) DEFAULT NULL,
  id_capacitacion INT(11) DEFAULT NULL,
  metodo VARCHAR(40) NOT NULL,
  estado VARCHAR(30) NOT NULL DEFAULT 'pendiente',
  monto DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  moneda VARCHAR(10) NOT NULL DEFAULT 'ARS',
  comprobante_path VARCHAR(255) DEFAULT NULL,
  comprobante_nombre VARCHAR(255) DEFAULT NULL,
  comprobante_mime VARCHAR(120) DEFAULT NULL,
  comprobante_tamano INT(10) UNSIGNED DEFAULT NULL,
  observaciones VARCHAR(255) DEFAULT NULL,
  creado_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP(),
  actualizado_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP() ON UPDATE CURRENT_TIMESTAMP(),
  PRIMARY KEY (id_pago),
  KEY fk_pagos_capacitaciones (id_capacitacion),
  KEY fk_pagos_certificaciones (id_certificacion),
  CONSTRAINT fk_pagos_capacitaciones FOREIGN KEY (id_capacitacion) REFERENCES checkout_capacitaciones (id_capacitacion),
  CONSTRAINT fk_pagos_certificaciones FOREIGN KEY (id_certificacion) REFERENCES checkout_certificaciones (id_certificacion)
);

CREATE TABLE checkout_mercadopago (
  id_mp INT(11) NOT NULL AUTO_INCREMENT,
  id_pago INT(11) NOT NULL,
  preference_id VARCHAR(80) NOT NULL,
  init_point VARCHAR(255) NOT NULL,
  sandbox_init_point VARCHAR(255) DEFAULT NULL,
  external_reference VARCHAR(120) DEFAULT NULL,
  status VARCHAR(60) NOT NULL DEFAULT 'pendiente',
  status_detail VARCHAR(120) DEFAULT NULL,
  payment_id VARCHAR(60) DEFAULT NULL,
  payment_type VARCHAR(80) DEFAULT NULL,
  payer_email VARCHAR(150) DEFAULT NULL,
  payload LONGTEXT DEFAULT NULL,
  creado_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP(),
  actualizado_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP() ON UPDATE CURRENT_TIMESTAMP(),
  PRIMARY KEY (id_mp),
  KEY fk_id_pago_checkout_pagos (id_pago),
  CONSTRAINT fk_id_pago_checkout_pagos FOREIGN KEY (id_pago) REFERENCES checkout_pagos (id_pago)
);

-- 7) Históricos de estado (dependen de checkouts y estados_inscripciones)
CREATE TABLE historico_estado_capacitaciones (
  id_historico INT(11) NOT NULL AUTO_INCREMENT,
  id_capacitacion INT(11) NOT NULL,
  id_estado INT(11) NOT NULL,
  cambiado_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP(),
  PRIMARY KEY (id_historico),
  KEY fk_id_capacitaciones (id_capacitacion),
  KEY fk_id_estad__end (id_estado),
  CONSTRAINT fk_id_capacitaciones FOREIGN KEY (id_capacitacion) REFERENCES checkout_capacitaciones (id_capacitacion),
  CONSTRAINT fk_id_estad__end FOREIGN KEY (id_estado) REFERENCES estados_inscripciones (id_estado)
);

CREATE TABLE historico_estado_certificaciones (
  id_historico INT(11) NOT NULL AUTO_INCREMENT,
  id_certificacion INT(11) NOT NULL,
  id_estado INT(11) NOT NULL,
  cambiado_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP(),
  PRIMARY KEY (id_historico),
  KEY fk_id_estado (id_estado),
  KEY fk_id_certificaciones (id_certificacion),
  CONSTRAINT fk_id_certificaciones FOREIGN KEY (id_certificacion) REFERENCES checkout_certificaciones (id_certificacion),
  CONSTRAINT fk_id_estado FOREIGN KEY (id_estado) REFERENCES estados_inscripciones (id_estado)
);

-- 8) Asignaciones (depende de usuarios/cursos/checkouts)
CREATE TABLE asignaciones_cursos (
  id_asignacion INT(11) NOT NULL AUTO_INCREMENT,
  id_asignado INT(11) NOT NULL,
  id_asignado_por INT(11) DEFAULT NULL,
  id_curso INT(11) NOT NULL,
  tipo_curso ENUM('capacitacion','certificacion') NOT NULL,
  id_checkout_capacitacion INT(11) DEFAULT NULL,
  id_checkout_certificacion INT(11) DEFAULT NULL,
  id_estado TINYINT(4) NOT NULL DEFAULT 1,
  observaciones TEXT DEFAULT NULL,
  creado_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP(),
  actualizado_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP() ON UPDATE CURRENT_TIMESTAMP(),
  PRIMARY KEY (id_asignacion),
  UNIQUE KEY uq_asignacion_unica (id_asignado,id_curso,tipo_curso),
  KEY idx_asignado (id_asignado),
  KEY idx_curso (id_curso),
  KEY idx_estado (id_estado),
  KEY fk_asig_usuario_creador (id_asignado_por),
  KEY fk_asig_checkout_cap (id_checkout_capacitacion),
  KEY fk_asig_checkout_cert (id_checkout_certificacion),
  CONSTRAINT fk_asig_checkout_cap FOREIGN KEY (id_checkout_capacitacion) REFERENCES checkout_capacitaciones (id_capacitacion) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT fk_asig_checkout_cert FOREIGN KEY (id_checkout_certificacion) REFERENCES checkout_certificaciones (id_certificacion) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT fk_asig_curso FOREIGN KEY (id_curso) REFERENCES cursos (id_curso) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT fk_asig_usuario FOREIGN KEY (id_asignado) REFERENCES usuarios (id_usuario) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT fk_asig_usuario_creador FOREIGN KEY (id_asignado_por) REFERENCES usuarios (id_usuario) ON DELETE SET NULL ON UPDATE CASCADE
);

-- 9) Recuperaciones (depende de usuarios)
CREATE TABLE recuperaciones_contrasena (
  id_reset INT(11) NOT NULL AUTO_INCREMENT,
  id_usuario INT(11) NOT NULL,
  token VARCHAR(128) NOT NULL,
  expiracion DATETIME NOT NULL,
  utilizado TINYINT(1) NOT NULL DEFAULT 0,
  creado_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP(),
  usado_en DATETIME DEFAULT NULL,
  PRIMARY KEY (id_reset),
  KEY fk_id_usuario_usuarios_end (id_usuario),
  CONSTRAINT fk_id_usuario_usuarios_end FOREIGN KEY (id_usuario) REFERENCES usuarios (id_usuario)
);


-- 1) Catálogos / tablas base
INSERT INTO estado (id_estado, nombre_estado, descripcion_estado) VALUES
(1, 'registrado', 'El usuario se registro pero no inicio sesion nunca en la pagina'),
(2, 'logueado', 'El usuario inicio la sesion en la pagina');

INSERT INTO permisos (id_permiso, nombre_permiso, descripcion_permiso) VALUES
(1, 'admin', 'Permiso de edicion'),
(2, 'usuario', 'Solo vista '),
(3, 'rrhh', 'Permisos para asignar empleados y cursos a esos empleados'),
(4, 'Trabajador_asignado', 'Perfil asignado a los trabajadores de cada empresa');

INSERT INTO estados_inscripciones (id_estado, nombre_estado) VALUES
(1, 'pendiente'),
(2, 'aprobado'),
(3, 'pagado'),
(4, 'rechazado');

INSERT INTO modalidades (id_modalidad, nombre_modalidad, descripcion_modalidad) VALUES
(1, 'Presencial', 'Clases que se realizan en un lugar físico, con la presencia del instructor y los estudiantes.'),
(2, 'Online', 'Clases que se realizan completamente en línea, sin necesidad de presencia física.'),
(3, 'Híbrido', 'Combinación de clases presenciales y en línea.');

-- 2) Usuarios
INSERT INTO usuarios (id_usuario, email, nombre, apellido, telefono, clave, id_estado, id_permiso, token_verificacion, token_expiracion, verificado, google_sub) VALUES
(1, 'pruebas@institutodeoperadores.com', 'Pruebas', 'Melillo', NULL, '$2y$10$G5oCy7VKVCfM0lcW9q/Ig.tETnIh3Gy9X5UmiPLiWBC8cm/E6Ffwa', 2, 2, NULL, NULL, 1, '104201308450167003224'),
(2, 'administracion@institutodeoperadores.com', '', '', NULL, '$2y$10$BOYvdEyEt4tDuXinuc1b3ek57vlRgcYZXmdn4AJsLZ5KcYJjajSma', 2, 1, NULL, NULL, 1, NULL),
(3, 'pruebaa@mail.com', 'prueba', '1', NULL, '$2y$10$kCMOMQEBqP7zTcKGjq0iNuZmf5R5h6qPmHqmeUEqssB8XqDPeg05u', 1, 4, NULL, NULL, 0, NULL),
(37, 'tomi22129@gmail.com', 'Tomas', 'Rap', '+5222222', '$2y$10$.bL/wZ5PgGWaWM5IO5YLGefzVANtVjcAT20iM.pnQE6THESfwfywq', 1, 3, NULL, NULL, 1, '116105777166954342116'),
(39, 'tomasraptopulos@gmail.com', 'Tomas', 'Raptopulos', '571319798', '$2y$10$/oK3tHteDx3z/5VfxBsw6uO0mXQLupeijAu3Gy3k7wCvW4MfGahgK', 1, 4, NULL, NULL, 1, '114916926473181042070'),
(41, 'juanimelillo@gmail.com', 'Juani', 'Melillo', NULL, '$2y$10$KfEzcYTP.yarL7uOLfRZJO1agR1gyKG8K6hNFPIxsq8AxQuvoBvDa', 2, 1, NULL, NULL, 1, '118284451710276177062'),
(43, 'torus22129@gmail.com', 'torus', 'Raptopulos', '+543571319798', '$2y$10$iOZCQFZMZYlFxOv4gqZIquAzZM2rtwsTlCThcKLTQGXkxD1z7cpdi', 2, 4, NULL, NULL, 1, NULL),
(44, 'juanimelillo36@gmail.com', 'juan ignacion', 'melillo', NULL, '$2y$10$reYwVrVx1QQfBe2IXHpuYOCrKUFmHoU55wRqemb6uNmI69Cw0zv.e', 2, 2, NULL, NULL, 1, '100766004167398182985'),
(45, 'condorigaston31@gmail.com', 'Gaston jonatan', 'Condori', '3875006344', '$2y$10$sPBETWUaWa5NlmhubVZTxueIV9akA7E/Cck/DGPUQSTSFa/j9Hozu', 1, 2, '33c45ab201e667db72c91cf9e68f6d8faa9b4bf92c57a7aeb916ad4b37508982', '2025-10-08 11:44:10', 0, NULL);

-- 3) Cursos y precios
INSERT INTO cursos (id_curso, nombre_curso, descripcion_curso, duracion, objetivos, id_complejidad) VALUES
(1, 'Operador de Grúa Móvil', 'Curso para la operación segura de grúas móviles.', 40, 'Capacitar al operador en maniobras seguras y normativas vigentes.', 2),
(2, 'Operador de Grúa Móvil de Pluma Articulada', 'Curso especializado en grúas de pluma articulada.', 40, 'Formar operadores en uso seguro y eficiente de grúas articuladas.', 2),
(3, 'Operador de Hidroelevador', 'Curso para la operación de equipos hidro elevadores.', 30, 'Enseñar técnicas seguras para trabajos en altura.', 2),
(4, 'Operador de Autoelevador', 'Curso de manejo de autoelevadores.', 30, 'Formar operadores en seguridad y eficiencia en el uso de autoelevadores.', 2),
(5, 'Operador Rigger', 'Curso sobre funciones de rigger en maniobras de izaje.', 35, 'Capacitar en señalización, amarres y seguridad en izajes.', 2),
(6, 'Operador de Motoniveladora', 'Curso de operación de motoniveladoras para obras viales.', 50, 'Desarrollar habilidades en nivelación y movimiento de suelos.', 2),
(7, 'Operador de Cargadora', 'Curso para la operación de cargadoras frontales.', 40, 'Formar en técnicas de carga y seguridad laboral.', 2),
(8, 'Operador de Retroexcavadora', 'Curso de uso de retroexcavadoras en obras civiles.', 45, 'Capacitar en excavación, movimiento de tierras y seguridad.', 2),
(9, 'Operador de Excavadora', 'Curso de manejo de excavadoras hidráulicas.', 45, 'Enseñar técnicas de excavación y normativas de seguridad.', 2),
(10, 'Operador de Topador', 'Curso de operación de topadores para movimiento de suelos.', 50, 'Formar operadores en nivelación, empuje y seguridad en obra.', 2);

INSERT INTO curso_precio_hist (id, id_curso, precio, moneda, vigente_desde, vigente_hasta, comentario, creado_en) VALUES
(1, 1, 120000.00, 'ARS', '2025-07-01 00:00:00', NULL, 'Ajuste invierno 2025', '2025-09-16 19:40:17'),
(2, 1, 110000.00, 'ARS', '2025-03-01 00:00:00', '2025-06-30 23:59:59', 'Ajuste Q2 2025', '2025-09-16 19:40:17'),
(3, 1, 95000.00, 'ARS', '2024-11-01 00:00:00', '2025-02-28 23:59:59', 'Tarifa fin 2024', '2025-09-16 19:40:17'),
(4, 2, 85000.00, 'ARS', '2025-06-20 00:00:00', NULL, 'Tarifa vigente 2025-06', '2025-09-16 19:40:17'),
(5, 3, 99000.00, 'ARS', '2025-07-01 00:00:00', NULL, 'Ajuste mitad de año', '2025-09-16 19:40:17'),
(6, 3, 92000.00, 'ARS', '2025-05-01 00:00:00', '2025-06-30 23:59:59', 'Tarifa promo mayo-junio', '2025-09-16 19:40:17'),
(7, 4, 150000.00, 'ARS', '2025-07-01 00:00:00', NULL, 'Revisión anual 2025', '2025-09-16 19:40:17'),
(8, 4, 135000.00, 'ARS', '2024-12-01 00:00:00', '2025-06-30 23:59:59', 'Tarifa 2024-2025 H1', '2025-09-16 19:40:17'),
(9, 5, 70000.00, 'ARS', '2025-08-15 00:00:00', NULL, 'Lanzamiento agosto 2025', '2025-09-16 19:40:17'),
(10, 6, 130000.00, 'ARS', '2025-07-15 00:00:00', '2025-09-15 22:06:59', 'Ajuste julio 2025', '2025-09-16 19:40:17'),
(11, 6, 120000.00, 'ARS', '2025-02-01 00:00:00', '2025-07-14 23:59:59', 'Revisión Q1-Q2 2025', '2025-09-16 19:40:17'),
(12, 6, 105000.00, 'ARS', '2024-07-01 00:00:00', '2025-01-31 23:59:59', 'Tarifa 2do semestre 2024', '2025-09-16 19:40:17'),
(13, 7, 65000.00, 'ARS', '2025-01-10 00:00:00', NULL, 'Tarifa base 2025', '2025-09-16 19:40:17'),
(14, 8, 115000.00, 'ARS', '2025-06-01 00:00:00', NULL, 'Ajuste mitad de año', '2025-09-16 19:40:17'),
(15, 8, 98000.00, 'ARS', '2025-03-10 00:00:00', '2025-05-31 23:59:59', 'Tarifa post lanzamiento', '2025-09-16 19:40:17'),
(16, 9, 140000.00, 'ARS', '2025-04-01 00:00:00', NULL, 'Revisión abril 2025', '2025-09-16 19:40:17'),
(17, 9, 125000.00, 'ARS', '2024-09-01 00:00:00', '2025-03-31 23:59:59', 'Tarifa 2024/2025', '2025-09-16 19:40:17'),
(18, 10, 90000.00, 'ARS', '2025-02-01 00:00:00', '2025-09-26 00:02:59', 'Tarifa 2025', '2025-09-18 00:03:38'),
(20, 6, 1200000.00, 'ARS', '2025-09-27 21:58:00', NULL, 'comentario', '2025-09-16 21:59:03'),
(21, 6, 1300000.00, 'ARS', '2025-09-15 22:07:00', '2025-09-26 22:06:59', 'comentario', '2025-09-16 22:07:34'),
(22, 6, 180000.00, 'ARS', '2025-09-26 22:07:00', '2025-09-27 21:57:59', 'mas', '2025-09-16 22:07:55'),
(23, 10, 999999.00, 'ARS', '2025-09-26 00:03:00', NULL, 'inflacion loquita', '2025-09-18 00:03:38');

-- 4) Checkouts (dep. usuarios/cursos/estados)
INSERT INTO checkout_capacitaciones (id_capacitacion, creado_por, id_curso, nombre, apellido, email, telefono, dni, direccion, ciudad, provincia, pais, acepta_tyc, precio_total, moneda, id_estado, creado_en) VALUES
(6, 37, 1, 'juan ignacio', 'melillo', 'juanimelillo@gmail.com', '+543571311240', '42695517', 'alem 972', 'Almafuerte, Córdoba, Argentina', 'Cordoba', 'Argentina', 1, 120000.00, 'ARS', 1, '2025-09-23 15:04:26'),
(7, 37, 1, 'juan ignacio', 'melillo', 'juanimelillo@gmail.com', '+543571311240', '42695517', 'alem 972', 'Almafuerte, Córdoba, Argentina', 'Cordoba', 'Argentina', 1, 120000.00, 'ARS', 1, '2025-09-23 15:05:50'),
(20, NULL, 1, 'juan ignacio', 'melillo', 'juanimelillo@gmail.com', '+543571311240', '42695517', 'alem 972', 'Almafuerte, Córdoba, Argentina', 'Cordoba', 'Argentina', 1, 120000.00, 'ARS', 1, '2025-09-23 20:19:27'),
(22, NULL, 1, 'juan ignacio', 'melillo', 'juanimelillo@gmail.com', '+543571311240', '42695517', 'alem 972', 'Almafuerte, Córdoba, Argentina', 'Cordoba', 'Argentina', 1, 120000.00, 'ARS', 1, '2025-09-23 20:23:09'),
(23, NULL, 1, 'juan ignacio', 'melillo', 'juanimelillo@gmail.com', '+543571311240', NULL, 'alem 972', 'Almafuerte, Córdoba, Argentina', 'Cordoba', 'Argentina', 1, 120000.00, 'ARS', 1, '2025-09-23 20:27:49'),
(24, NULL, 1, 'juan ignacio', 'melillo', 'juanimelillo@gmail.com', '+543571311240', '123123123', 'alem 972', 'Almafuerte, Córdoba, Argentina', 'Cordoba', 'Argentina', 1, 120000.00, 'ARS', 1, '2025-09-23 20:28:18'),
(25, NULL, 1, 'juan', 'melillo', 'juanimelillo@gmail.com', '3571311240', '42695517', 'chubuit 20', 'almafiuerte', 'cordoba', 'Argentina', 1, 120000.00, 'ARS', 1, '2025-09-23 20:29:37'),
(28, NULL, 1, 'juan ignacio', 'melillo', 'juanimelillo@gmail.com', '+543571311240', '42695517', 'alem 972', 'Almafuerte, Córdoba, Argentina', 'Cordoba', 'Argentina', 1, 120000.00, 'ARS', 1, '2025-09-24 13:56:33'),
(29, 37, 1, 'Tomas', 'Raptopulos', 'tomasraptopulos@gmail.com', '571319798', NULL, NULL, NULL, NULL, NULL, 1, 120000.00, 'ARS', 2, '2025-09-23 15:04:26'),
(30, 37, 2, 'prueba', '1', 'pruebaa@mail.com', NULL, NULL, NULL, NULL, NULL, NULL, 1, 120000.00, 'ARS', 2, '2025-09-23 15:04:26'),
(31, 37, 3, '1', '1', 'tomasraptopulos@gmail.com', '571319798', NULL, NULL, NULL, NULL, NULL, 1, 120000.00, 'ARS', 2, '2025-09-23 15:04:26'),
(32, 37, 1, 'torus', 'Raptopulos', 'torus22129@gmail.com', '+543571319798', NULL, NULL, NULL, NULL, NULL, 1, 120000.00, 'ARS', 2, '2025-09-23 15:04:26'),
(33, 37, 2, 'Tomas', 'Raptopulos', 'tomasraptopulos@gmail.com', '571319798', NULL, NULL, NULL, NULL, NULL, 1, 120000.00, 'ARS', 2, '2025-09-23 15:04:26'),
(34, 37, 3, 'prueba', '1', 'pruebaa@mail.com', NULL, NULL, NULL, NULL, NULL, NULL, 1, 120000.00, 'ARS', 2, '2025-09-23 15:04:26'),
(35, 37, 1, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 1, 120000.00, 'ARS', 1, '2025-09-23 15:04:26'),
(36, 37, 2, 'torus', 'Raptopulos', 'torus22129@gmail.com', '+543571319798', NULL, NULL, NULL, NULL, NULL, 1, 120000.00, 'ARS', 2, '2025-09-23 15:04:26'),
(37, 37, 3, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 1, 120000.00, 'ARS', 1, '2025-09-23 15:04:26'),
(38, 37, 1, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 1, 120000.00, 'ARS', 1, '2025-09-23 15:04:26'),
(45, 44, 1, 'juan ignacio', 'melillo', 'juanimelillo@gmail.com', '+543571311240', '42695517', 'alem 972', 'Almafuerte, Córdoba, Argentina', 'Cordoba', 'Argentina', 1, 120000.00, 'ARS', 1, '2025-10-06 23:57:46'),
(46, 44, 1, 'juan ignacio', 'melillo', 'juanimelillo@gmail.com', '+543571311240', '42695517', 'alem 972', 'Almafuerte, Córdoba, Argentina', 'Cordoba', 'Argentina', 1, 120000.00, 'ARS', 1, '2025-10-07 00:02:47');

-- 5) Pagos (incluye los faltantes 17,19,20,21,22)
INSERT INTO checkout_pagos (id_pago, id_certificacion, id_capacitacion, metodo, estado, monto, moneda, comprobante_path, comprobante_nombre, comprobante_mime, comprobante_tamano, observaciones, creado_en, actualizado_en) VALUES
(1, NULL, 45, 'mercado_pago', 'pendiente', 120000.00, 'ARS', NULL, NULL, NULL, NULL, NULL, '2025-10-06 23:57:46', '2025-10-06 23:57:46'),
(2, NULL, 46, 'mercado_pago', 'pagado', 120000.00, 'ARS', NULL, NULL, NULL, NULL, NULL, '2025-10-07 00:02:47', '2025-10-07 00:05:29'),
(17, NULL, 20, 'mercado_pago', 'pendiente', 120000.00, 'ARS', NULL, NULL, NULL, NULL, NULL, '2025-09-23 20:19:27', '2025-09-23 20:19:27'),
(19, NULL, 22, 'mercado_pago', 'pendiente', 120000.00, 'ARS', NULL, NULL, NULL, NULL, NULL, '2025-09-23 20:23:09', '2025-09-23 20:23:09'),
(20, NULL, 23, 'mercado_pago', 'pendiente', 120000.00, 'ARS', NULL, NULL, NULL, NULL, NULL, '2025-09-23 20:27:49', '2025-09-23 20:27:49'),
(21, NULL, 24, 'mercado_pago', 'pendiente', 120000.00, 'ARS', NULL, NULL, NULL, NULL, NULL, '2025-09-23 20:28:18', '2025-09-23 20:28:18'),
(22, NULL, 25, 'mercado_pago', 'pendiente', 120000.00, 'ARS', NULL, NULL, NULL, NULL, NULL, '2025-09-23 20:29:37', '2025-09-23 20:29:37');

-- 6) MercadoPago (dep. de checkout_pagos)
INSERT INTO checkout_mercadopago (id_mp, id_pago, preference_id, init_point, sandbox_init_point, external_reference, status, status_detail, payment_id, payment_type, payer_email, payload, creado_en, actualizado_en) VALUES
(1, 17, '1578491289-3dc2d4a0-c233-4383-8fc2-a275d8bf0629', 'https://www.mercadopago.com.ar/checkout/v1/redirect?pref_id=1578491289-3dc2d4a0-c233-4383-8fc2-a275d8bf0629', 'https://sandbox.mercadopago.com.ar/checkout/v1/redirect?pref_id=1578491289-3dc2d4a0-c233-4383-8fc2-a275d8bf0629', 'insc-20', 'pendiente', NULL, NULL, NULL, NULL, '{"preference":{"id":"1578491289-3dc2d4a0-c233-4383-8fc2-a275d8bf0629"}}', '2025-09-23 20:19:28', '2025-09-23 20:19:28'),
(2, 19, '1578491289-1ec5ce20-4459-4b24-aa11-70df7d0c9aa1', 'https://www.mercadopago.com.ar/checkout/v1/redirect?pref_id=1578491289-1ec5ce20-4459-4b24-aa11-70df7d0c9aa1', 'https://sandbox.mercadopago.com.ar/checkout/v1/redirect?pref_id=1578491289-1ec5ce20-4459-4b24-aa11-70df7d0c9aa1', 'insc-22', 'pendiente', NULL, NULL, NULL, NULL, '{"preference":{"id":"1578491289-1ec5ce20-4459-4b24-aa11-70df7d0c9aa1"}}', '2025-09-23 20:23:10', '2025-09-23 20:23:10'),
(3, 20, '1578491289-69582870-e3d9-4b80-b947-c7b8557b940f', 'https://www.mercadopago.com.ar/checkout/v1/redirect?pref_id=1578491289-69582870-e3d9-4b80-b947-c7b8557b940f', 'https://sandbox.mercadopago.com.ar/checkout/v1/redirect?pref_id=1578491289-69582870-e3d9-4b80-b947-c7b8557b940f', 'insc-23', 'pendiente', NULL, NULL, NULL, NULL, '{"preference":{"id":"1578491289-69582870-e3d9-4b80-b947-c7b8557b940f"}}', '2025-09-23 20:27:50', '2025-09-23 20:27:50'),
(4, 21, '1578491289-fb336608-fb85-45ab-af4d-d0a94b97a14b', 'https://www.mercadopago.com.ar/checkout/v1/redirect?pref_id=1578491289-fb336608-fb85-45ab-af4d-d0a94b97a14b', 'https://sandbox.mercadopago.com.ar/checkout/v1/redirect?pref_id=1578491289-fb336608-fb85-45ab-af4d-d0a94b97a14b', 'insc-24', 'pendiente', NULL, NULL, NULL, NULL, '{"preference":{"id":"1578491289-fb336608-fb85-45ab-af4d-d0a94b97a14b"}}', '2025-09-23 20:28:18', '2025-09-23 20:28:18'),
(5, 22, '1578491289-09ddb966-44d6-4502-b54b-21151b7b5342', 'https://www.mercadopago.com.ar/checkout/v1/redirect?pref_id=1578491289-09ddb966-44d6-4502-b54b-21151b7b5342', 'https://sandbox.mercadopago.com.ar/checkout/v1/redirect?pref_id=1578491289-09ddb966-44d6-4502-b54b-21151b7b5342', 'insc-25', 'pendiente', NULL, NULL, NULL, NULL, '{"preference":{"id":"1578491289-09ddb966-44d6-4502-b54b-21151b7b5342"}}', '2025-09-23 20:29:38', '2025-09-23 20:29:38'),
(6, 1, '1578491289-7a74f440-b3a4-4414-a68d-cebc209bb16c', 'https://www.mercadopago.com.ar/checkout/v1/redirect?pref_id=1578491289-7a74f440-b3a4-4414-a68d-cebc209bb16c', NULL, 'curso-1-59aed990', 'pendiente', NULL, NULL, NULL, NULL, '{"request":{"items":[{"id":"1","title":"Operador de Grúa Móvil","description":"Inscripción al curso Operador de Grúa Móvil","quantity":1,"unit_price":120000,"currency_id":"ARS"}],"external_reference":"curso-1-59aed990","payer":{"email":"juanimelillo@gmail.com","first_name":"juan ignacio","last_name":"melillo"},"back_urls":{"success":"https://b6cd0e268d5e.ngrok-free.app/mp/checkout/gracias.php","failure":"https://b6cd0e268d5e.ngrok-free.app/mp/checkout/gracias.php","pending":"https://b6cd0e268d5e.ngrok-free.app/mp/checkout/gracias.php"},"auto_return":"approved","notification_url":"https://b6cd0e268d5e.ngrok-free.app/mp/checkout/mercadopago_webhook.php","metadata":{"id_pago":1,"id_inscripcion":45,"id_capacitacion":45,"id_certificacion":null,"id_curso":1,"tipo_checkout":"curso","email":"juanimelillo@gmail.com"}},"preference":{"id":"1578491289-7a74f440-b3a4-4414-a68d-cebc209bb16c","init_point":"https://www.mercadopago.com.ar/checkout/v1/redirect?pref_id=1578491289-7a74f440-b3a4-4414-a68d-cebc209bb16c"}}', '2025-10-06 23:57:47', '2025-10-06 23:57:47'),
(7, 2, '1578491289-108127fc-3ba0-4e82-97cd-7df2ee8ce9e7', 'https://www.mercadopago.com.ar/checkout/v1/redirect?pref_id=1578491289-108127fc-3ba0-4e82-97cd-7df2ee8ce9e7', NULL, 'curso-1-984a5f31', 'approved', 'accredited', '128348841431', 'credit_card', 'test_user_449649635@testuser.com', '{"request":{"items":[{"id":"1","title":"Operador de Grúa Móvil","description":"Inscripción al curso Operador de Grúa Móvil","quantity":1,"unit_price":120000,"currency_id":"ARS"}],"external_reference":"curso-1-984a5f31","payer":{"email":"juanimelillo@gmail.com","first_name":"juan ignacio","last_name":"melillo"},"back_urls":{"success":"https://institutodeoperadores.com//checkout/gracias.php","failure":"https://institutodeoperadores.com//checkout/gracias.php","pending":"https://institutodeoperadores.com//checkout/gracias.php"},"auto_return":"approved","notification_url":"https://institutodeoperadores.com//checkout/mercadopago_webhook.php","metadata":{"id_pago":2,"id_inscripcion":46,"id_capacitacion":46,"id_certificacion":null,"id_curso":1,"tipo_checkout":"curso","email":"juanimelillo@gmail.com"}},"preference":{"id":"1578491289-108127fc-3ba0-4e82-97cd-7df2ee8ce9e7","init_point":"https://www.mercadopago.com.ar/checkout/v1/redirect?pref_id=1578491289-108127fc-3ba0-4e82-97cd-7df2ee8ce9e7"}}', '2025-10-07 00:02:48', '2025-10-07 00:05:29');

-- 7) Certificaciones e históricos
INSERT INTO checkout_certificaciones (id_certificacion, creado_por, acepta_tyc, precio_total, moneda, id_curso, pdf_path, pdf_nombre, pdf_mime, observaciones, id_estado, creado_en, nombre, apellido, email, telefono) VALUES
(1, 37, 1, 220000.00, 'ARS', 1, NULL, NULL, NULL, NULL, 2, '2025-09-23 15:04:26', 'prueba', '1', 'pruebaa@mail.com', NULL),
(2, 37, 1, 220000.00, 'ARS', 2, NULL, NULL, NULL, NULL, 1, '2025-09-23 15:04:26', NULL, NULL, NULL, NULL),
(3, 44, 1, 120000.00, 'ARS', 1, 'uploads/certificaciones/cert_20251006205313_674b80cfb3451207.pdf', 'EBOOK_200_PROMPTS_MDEV1.pdf', 'application/pdf', 'Solicitud aprobada el 06/10/2025 20:55', 2, '2025-10-06 20:53:13', 'juan ignacion', 'melillo', 'juanimelillo36@gmail.com', '+543571311240'),
(4, 44, 1, 70000.00, 'ARS', 5, 'uploads/certificaciones/cert_20251007000736_862a0b52b57de445.pdf', 'EBOOK_200_PROMPTS_MDEV1.pdf', 'application/pdf', NULL, 1, '2025-10-07 00:07:36', 'juan ignacion', 'melillo', 'juanimelillo36@gmail.com', '+543571311240'),
(5, 44, 1, 140000.00, 'ARS', 9, 'uploads/certificaciones/cert_20251007000908_f43f1a93d1bec6ba.pdf', 'Instructivo-tablero-CPEC-actualizado.pdf', 'application/pdf', NULL, 1, '2025-10-07 00:09:08', 'juan ignacion', 'melillo', 'juanimelillo36@gmail.com', '+543571311240');

INSERT INTO historico_estado_capacitaciones (id_historico, id_capacitacion, id_estado, cambiado_en) VALUES
(1, 31, 2, '2025-09-29 07:55:03'),
(2, 34, 2, '2025-09-29 07:55:03'),
(3, 29, 2, '2025-09-29 08:21:35'),
(4, 30, 2, '2025-10-02 13:56:35'),
(5, 33, 2, '2025-10-02 13:56:35'),
(6, 36, 2, '2025-10-02 13:56:35'),
(7, 32, 2, '2025-10-02 14:55:07');

INSERT INTO historico_estado_certificaciones (id_historico, id_certificacion, id_estado, cambiado_en) VALUES
(1, 1, 2, '2025-09-29 09:34:04'),
(2, 3, 1, '2025-10-06 20:53:13'),
(3, 3, 2, '2025-10-06 20:55:23'),
(4, 4, 1, '2025-10-07 00:07:36'),
(5, 5, 1, '2025-10-07 00:09:08');

-- 8) Relaciones y otros
INSERT INTO empresa_trabajadores (id, id_empresa, id_trabajador, asignado_en) VALUES
(5, 37, 3, '2025-09-25 10:38:37'),
(6, 37, 39, '2025-09-29 08:21:27'),
(7, 37, 43, '2025-10-02 13:56:25');

INSERT INTO asignaciones_cursos (id_asignacion, id_asignado, id_asignado_por, id_curso, tipo_curso, id_checkout_capacitacion, id_checkout_certificacion, id_estado, observaciones, creado_en, actualizado_en) VALUES
(1, 43, 37, 1, 'capacitacion', 32, NULL, 2, NULL, '2025-10-02 14:55:07', '2025-10-02 14:55:07');

INSERT INTO compra_items (id_item, id_compra, id_curso, id_modalidad, cantidad, precio_unitario, titulo_snapshot, descripcion_snapshot, creado_en) VALUES
(1, 1, 1, 1, 5, 15000.00, 'Operador de Grúa Móvil', 'Compra de prueba para visualización', '2025-09-19 11:53:43'),
(2, 2, 2, 2, 4, 16000.00, 'Operador de Grúa Móvil de Pluma Articulada', 'Compra de prueba 2', '2025-09-19 11:55:12'),
(3, 3, 3, 3, 2, 17000.00, 'Operador de Hidroelevador', 'Compra de prueba 3', '2025-09-19 11:55:13'),
(4, 4, 4, 1, 8, 18000.00, 'Operador de Autoelevador', 'Compra de prueba 4', '2025-09-19 11:55:13'),
(5, 5, 5, 2, 6, 19000.00, 'Operador Rigger', 'Compra de prueba 5', '2025-09-19 11:55:13'),
(6, 6, 6, 3, 3, 20000.00, 'Operador de Motoniveladora', 'Compra de prueba 6', '2025-09-19 11:55:13'),
(7, 7, 7, 1, 7, 21000.00, 'Operador de Cargadora', 'Compra de prueba 7', '2025-09-19 11:57:40'),
(8, 8, 8, 2, 1, 22000.00, 'Operador de Retroexcavadora', 'Compra de prueba 8', '2025-09-19 11:57:40'),
(9, 9, 9, 3, 1, 23000.00, 'Operador de Excavadora', 'Compra de prueba 9', '2025-09-19 11:57:41'),
(10, 10, 10, 1, 1, 24000.00, 'Operador de Topador', 'Compra de prueba 10', '2025-09-19 11:57:41'),
(11, 11, 1, 2, 1, 25000.00, 'Operador de Grúa Móvil', 'Compra de prueba 11', '2025-09-19 11:57:41');

INSERT INTO banner (id_banner, nombre_banner, imagen_banner) VALUES
(5, 'promo 7', 'imagen_673fca722e218.jpg'),
(6, 'promo 2', 'imagen_673fcecd3350d.png');

-- 9) Recuperaciones (corregido: id_usuario = 41 en lugar de 0)
INSERT INTO recuperaciones_contrasena (id_reset, id_usuario, token, expiracion, utilizado, creado_en, usado_en) VALUES
(1, 41, 'cf82c84e1f0a5e449903e13bcfd52f68f1a46578ce1e9f59ce64d2d54beb39a2', '2025-09-30 05:51:58', 1, '2025-09-30 04:51:58', '2025-09-30 04:52:53');

