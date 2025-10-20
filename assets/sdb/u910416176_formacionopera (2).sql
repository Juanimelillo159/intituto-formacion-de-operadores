-- 1) Tablas base sin dependencias externas (requeridas por otras)
CREATE TABLE estado (
  id_estado INT(11) NOT NULL AUTO_INCREMENT,
  nombre_estado VARCHAR(20) NOT NULL,
  descripcion_estado VARCHAR(100) NOT NULL,
  PRIMARY KEY (id_estado)
);

CREATE TABLE permisos (
  id_permiso INT(11) NOT NULL AUTO_INCREMENT,
  nombre_permiso VARCHAR(20) NOT NULL,
  descripcion_permiso VARCHAR(100) NOT NULL,
  PRIMARY KEY (id_permiso)
);

CREATE TABLE estados_inscripciones (
  id_estado INT(11) NOT NULL AUTO_INCREMENT,
  nombre_estado VARCHAR(50) NOT NULL,
  PRIMARY KEY (id_estado)
);

CREATE TABLE cursos (
  id_curso INT(11) NOT NULL AUTO_INCREMENT,
  nombre_curso VARCHAR(100) NOT NULL,
  descripcion_curso VARCHAR(255) NOT NULL,
  duracion INT(10) NOT NULL,
  objetivos VARCHAR(100) NOT NULL,
  id_complejidad INT(11) DEFAULT NULL,
  cronograma TEXT DEFAULT NULL,
  publico TEXT DEFAULT NULL,
  programa TEXT DEFAULT NULL,
  requisitos TEXT DEFAULT NULL,
  observaciones TEXT DEFAULT NULL,
  documentacion TEXT DEFAULT NULL,
  PRIMARY KEY (id_curso)
);

CREATE TABLE modalidades (
  id_modalidad INT(11) NOT NULL AUTO_INCREMENT,
  nombre_modalidad VARCHAR(255) NOT NULL,
  descripcion_modalidad TEXT DEFAULT NULL,
  PRIMARY KEY (id_modalidad)
);

-- 2) Dependen de estado y permisos
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
  CONSTRAINT fk_id_estado_usuario FOREIGN KEY (id_estado) REFERENCES estado (id_estado),
  CONSTRAINT fk_permisos FOREIGN KEY (id_permiso) REFERENCES permisos (id_permiso)
);

-- 3) Dependen de usuarios, cursos y/o estados_inscripciones
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
  creado_en DATETIME NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (id_capacitacion),
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
  creado_en DATETIME NOT NULL DEFAULT current_timestamp(),
  nombre VARCHAR(100) DEFAULT NULL,
  apellido VARCHAR(100) DEFAULT NULL,
  email VARCHAR(255) DEFAULT NULL,
  telefono VARCHAR(30) DEFAULT NULL,
  PRIMARY KEY (id_certificacion),
  CONSTRAINT fk_certificaciones_usuarios FOREIGN KEY (creado_por) REFERENCES usuarios (id_usuario),
  CONSTRAINT fk_certificaciones_cursos FOREIGN KEY (id_curso) REFERENCES cursos (id_curso),
  CONSTRAINT fk_certificaciones_estados FOREIGN KEY (id_estado) REFERENCES estados_inscripciones (id_estado)
);

-- 4) Dependen de las de arriba
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
  creado_en DATETIME NOT NULL DEFAULT current_timestamp(),
  actualizado_en DATETIME NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (id_pago),
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
  creado_en DATETIME NOT NULL DEFAULT current_timestamp(),
  actualizado_en DATETIME NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (id_mp),
  CONSTRAINT fk_id_pago_checkout_pagos FOREIGN KEY (id_pago) REFERENCES checkout_pagos (id_pago)
);

CREATE TABLE historico_estado_capacitaciones (
  id_historico INT(11) NOT NULL AUTO_INCREMENT,
  id_capacitacion INT(11) NOT NULL,
  id_estado INT(11) NOT NULL,
  cambiado_en DATETIME NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (id_historico),
  CONSTRAINT fk_id_capacitaciones FOREIGN KEY (id_capacitacion) REFERENCES checkout_capacitaciones (id_capacitacion),
  CONSTRAINT fk_id_estad__end FOREIGN KEY (id_estado) REFERENCES estados_inscripciones (id_estado)
);

CREATE TABLE historico_estado_certificaciones (
  id_historico INT(11) NOT NULL AUTO_INCREMENT,
  id_certificacion INT(11) NOT NULL,
  id_estado INT(11) NOT NULL,
  cambiado_en DATETIME NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (id_historico),
  CONSTRAINT fk_id_certificaciones FOREIGN KEY (id_certificacion) REFERENCES checkout_certificaciones (id_certificacion),
  CONSTRAINT fk_id_estado FOREIGN KEY (id_estado) REFERENCES estados_inscripciones (id_estado)
);

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
  creado_en DATETIME NOT NULL DEFAULT current_timestamp(),
  actualizado_en DATETIME NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (id_asignacion),
  CONSTRAINT fk_asig_usuario FOREIGN KEY (id_asignado) REFERENCES usuarios (id_usuario) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT fk_asig_usuario_creador FOREIGN KEY (id_asignado_por) REFERENCES usuarios (id_usuario) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT fk_asig_curso FOREIGN KEY (id_curso) REFERENCES cursos (id_curso) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT fk_asig_checkout_cap FOREIGN KEY (id_checkout_capacitacion) REFERENCES checkout_capacitaciones (id_capacitacion) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT fk_asig_checkout_cert FOREIGN KEY (id_checkout_certificacion) REFERENCES checkout_certificaciones (id_certificacion) ON DELETE SET NULL ON UPDATE CASCADE
);

-- 5) Tablas con FK hacia cursos (independientes del flujo de checkout)
CREATE TABLE curso_precio_hist (
  id INT(11) NOT NULL AUTO_INCREMENT,
  id_curso INT(11) NOT NULL,
  tipo_curso ENUM('capacitacion','certificacion') NOT NULL DEFAULT 'capacitacion',
  precio DECIMAL(10,2) NOT NULL,
  moneda CHAR(3) NOT NULL DEFAULT 'ARS',
  vigente_desde DATETIME NOT NULL,
  vigente_hasta DATETIME DEFAULT NULL,
  comentario VARCHAR(255) DEFAULT NULL,
  creado_en DATETIME NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (id),
  CONSTRAINT FK_id_curso FOREIGN KEY (id_curso) REFERENCES cursos (id_curso)
);

-- 6) Resto de tablas sin FK definidas en el dump
CREATE TABLE banner (
  id_banner INT(11) NOT NULL AUTO_INCREMENT,
  nombre_banner VARCHAR(100) NOT NULL,
  imagen_banner VARCHAR(100) NOT NULL,
  PRIMARY KEY (id_banner)
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
  creado_en DATETIME NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (id_item)
);

CREATE TABLE curso_modalidad (
  id_curso INT(11) NOT NULL,
  id_modalidad INT(11) NOT NULL
  -- Nota: el dump no define FK aquí
);

CREATE TABLE empresa_trabajadores (
  id INT(11) NOT NULL AUTO_INCREMENT,
  id_empresa INT(11) NOT NULL,
  id_trabajador INT(11) NOT NULL,
  asignado_en DATETIME NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (id)
);

CREATE TABLE recuperaciones_contrasena (
  id_reset INT(11) NOT NULL AUTO_INCREMENT,
  id_usuario INT(11) NOT NULL,
  token VARCHAR(128) NOT NULL,
  expiracion DATETIME NOT NULL,
  utilizado TINYINT(1) NOT NULL DEFAULT 0,
  creado_en DATETIME NOT NULL DEFAULT current_timestamp(),
  usado_en DATETIME DEFAULT NULL,
  PRIMARY KEY (id_reset),
  CONSTRAINT fk_id_usuario_usuarios_end FOREIGN KEY (id_usuario) REFERENCES usuarios (id_usuario)
);

CREATE TABLE v_cursos_rrhh (
  id_usuario INT(11) DEFAULT NULL,
  id_curso INT(11) DEFAULT NULL,
  tipo_curso VARCHAR(13) DEFAULT NULL,
  cantidad BIGINT(21) DEFAULT NULL
);
