-- Estructura para el nuevo flujo de checkout (inscripciones y pagos)

CREATE TABLE IF NOT EXISTS `checkout_inscripciones` (
  `id_inscripcion` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `id_curso` INT UNSIGNED NOT NULL,
  `nombre` VARCHAR(120) NOT NULL,
  `apellido` VARCHAR(120) NOT NULL,
  `email` VARCHAR(150) NOT NULL,
  `telefono` VARCHAR(60) NOT NULL,
  `dni` VARCHAR(40) DEFAULT NULL,
  `direccion` VARCHAR(200) DEFAULT NULL,
  `ciudad` VARCHAR(120) DEFAULT NULL,
  `provincia` VARCHAR(120) DEFAULT NULL,
  `pais` VARCHAR(120) NOT NULL DEFAULT 'Argentina',
  `acepta_tyc` TINYINT(1) NOT NULL DEFAULT 0,
  `precio_total` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  `moneda` VARCHAR(10) NOT NULL DEFAULT 'ARS',
  `creado_en` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `actualizado_en` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id_inscripcion`),
  KEY `idx_checkout_inscripciones_curso` (`id_curso`),
  CONSTRAINT `fk_checkout_inscripciones_curso` FOREIGN KEY (`id_curso`) REFERENCES `cursos` (`id_curso`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `checkout_pagos` (
  `id_pago` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `id_inscripcion` INT UNSIGNED NOT NULL,
  `metodo` VARCHAR(40) NOT NULL,
  `estado` VARCHAR(30) NOT NULL DEFAULT 'pendiente',
  `monto` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  `moneda` VARCHAR(10) NOT NULL DEFAULT 'ARS',
  `comprobante_path` VARCHAR(255) DEFAULT NULL,
  `comprobante_nombre` VARCHAR(255) DEFAULT NULL,
  `comprobante_mime` VARCHAR(120) DEFAULT NULL,
  `comprobante_tamano` INT UNSIGNED DEFAULT NULL,
  `observaciones` VARCHAR(255) DEFAULT NULL,
  `mp_preference_id` VARCHAR(120) DEFAULT NULL,
  `mp_payment_id` VARCHAR(60) DEFAULT NULL,
  `mp_payment_status` VARCHAR(30) DEFAULT NULL,
  `mp_payment_status_detail` VARCHAR(80) DEFAULT NULL,
  `mp_payer_email` VARCHAR(150) DEFAULT NULL,
  `mp_metadata` TEXT DEFAULT NULL,
  `creado_en` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `actualizado_en` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id_pago`),
  KEY `idx_checkout_pagos_inscripcion` (`id_inscripcion`),
  CONSTRAINT `fk_checkout_pagos_inscripcion` FOREIGN KEY (`id_inscripcion`) REFERENCES `checkout_inscripciones` (`id_inscripcion`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
