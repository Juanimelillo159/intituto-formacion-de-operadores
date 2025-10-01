-- phpMyAdmin SQL Dump
-- version 5.2.2
-- https://www.phpmyadmin.net/
--
-- Servidor: 127.0.0.1:3306
-- Tiempo de generación: 01-10-2025 a las 01:57:44
-- Versión del servidor: 11.8.3-MariaDB-log
-- Versión de PHP: 7.2.34

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Base de datos: `u910416176_formacionopera`
--

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `banner`
--

CREATE TABLE `banner` (
  `id_banner` int(11) NOT NULL,
  `nombre_banner` varchar(100) NOT NULL,
  `imagen_banner` varchar(100) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `banner`
--

INSERT INTO `banner` (`id_banner`, `nombre_banner`, `imagen_banner`) VALUES
(5, 'promo 7', 'imagen_673fca722e218.jpg'),
(6, 'promo 2', 'imagen_673fcecd3350d.png');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `checkout_capacitaciones`
--

CREATE TABLE `checkout_capacitaciones` (
  `id_capacitacion` int(11) NOT NULL,
  `creado_por` int(11) DEFAULT NULL,
  `id_curso` int(11) NOT NULL,
  `nombre` varchar(100) DEFAULT NULL,
  `apellido` varchar(100) DEFAULT NULL,
  `email` varchar(150) DEFAULT NULL,
  `telefono` varchar(50) DEFAULT NULL,
  `dni` varchar(40) DEFAULT NULL,
  `direccion` varchar(200) DEFAULT NULL,
  `ciudad` varchar(120) DEFAULT NULL,
  `provincia` varchar(120) DEFAULT NULL,
  `pais` varchar(100) DEFAULT NULL,
  `acepta_tyc` tinyint(1) NOT NULL DEFAULT 0,
  `precio_total` decimal(12,2) NOT NULL DEFAULT 0.00,
  `moneda` varchar(10) NOT NULL DEFAULT 'ARS',
  `id_estado` int(11) NOT NULL DEFAULT 1,
  `creado_en` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `checkout_capacitaciones`
--

INSERT INTO `checkout_capacitaciones` (`id_capacitacion`, `creado_por`, `id_curso`, `nombre`, `apellido`, `email`, `telefono`, `dni`, `direccion`, `ciudad`, `provincia`, `pais`, `acepta_tyc`, `precio_total`, `moneda`, `id_estado`, `creado_en`) VALUES
(6, 37, 1, 'juan ignacio', 'melillo', 'juanimelillo@gmail.com', '+543571311240', '42695517', 'alem 972', 'Almafuerte, Córdoba, Argentina', 'Cordoba', 'Argentina', 1, 120000.00, 'ARS', 1, '2025-09-23 15:04:26'),
(7, 37, 1, 'juan ignacio', 'melillo', 'juanimelillo@gmail.com', '+543571311240', '42695517', 'alem 972', 'Almafuerte, Córdoba, Argentina', 'Cordoba', 'Argentina', 1, 120000.00, 'ARS', 1, '2025-09-23 15:05:50'),
(20, NULL, 1, 'juan ignacio', 'melillo', 'juanimelillo@gmail.com', '+543571311240', '42695517', 'alem 972', 'Almafuerte, Córdoba, Argentina', 'Cordoba', 'Argentina', 1, 120000.00, 'ARS', 1, '2025-09-23 20:19:27'),
(22, NULL, 1, 'juan ignacio', 'melillo', 'juanimelillo@gmail.com', '+543571311240', '42695517', 'alem 972', 'Almafuerte, Córdoba, Argentina', 'Cordoba', 'Argentina', 1, 120000.00, 'ARS', 1, '2025-09-23 20:23:09'),
(23, NULL, 1, 'juan ignacio', 'melillo', 'juanimelillo@gmail.com', '+543571311240', NULL, 'alem 972', 'Almafuerte, Córdoba, Argentina', 'Cordoba', 'Argentina', 1, 120000.00, 'ARS', 1, '2025-09-23 20:27:49'),
(24, NULL, 1, 'juan ignacio', 'melillo', 'juanimelillo@gmail.com', '+543571311240', '123123123', 'alem 972', 'Almafuerte, Córdoba, Argentina', 'Cordoba', 'Argentina', 1, 120000.00, 'ARS', 1, '2025-09-23 20:28:18'),
(25, NULL, 1, 'juan', 'melillo', 'juanimelillo@gmail.com', '3571311240', '42695517', 'chubuit 20', 'almafiuerte', 'cordoba', 'Argentina', 1, 120000.00, 'ARS', 1, '2025-09-23 20:29:37'),
(28, NULL, 1, 'juan ignacio', 'melillo', 'juanimelillo@gmail.com', '+543571311240', '42695517', 'alem 972', 'Almafuerte, Córdoba, Argentina', 'Cordoba', 'Argentina', 1, 120000.00, 'ARS', 1, '2025-09-24 13:56:33'),
(29, 37, 1, 'Tomas', 'Raptopulos', 'tomasraptopulos@gmail.com', '571319798', NULL, NULL, NULL, NULL, NULL, 1, 120000.00, 'ARS', 2, '2025-09-23 15:04:26'),
(30, 37, 2, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 1, 120000.00, 'ARS', 1, '2025-09-23 15:04:26'),
(31, 37, 3, '1', '1', 'tomasraptopulos@gmail.com', '571319798', NULL, NULL, NULL, NULL, NULL, 1, 120000.00, 'ARS', 2, '2025-09-23 15:04:26'),
(32, 37, 1, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 1, 120000.00, 'ARS', 1, '2025-09-23 15:04:26'),
(33, 37, 2, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 1, 120000.00, 'ARS', 1, '2025-09-23 15:04:26'),
(34, 37, 3, 'prueba', '1', 'pruebaa@mail.com', NULL, NULL, NULL, NULL, NULL, NULL, 1, 120000.00, 'ARS', 2, '2025-09-23 15:04:26'),
(35, 37, 1, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 1, 120000.00, 'ARS', 1, '2025-09-23 15:04:26'),
(36, 37, 2, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 1, 120000.00, 'ARS', 1, '2025-09-23 15:04:26'),
(37, 37, 3, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 1, 120000.00, 'ARS', 1, '2025-09-23 15:04:26'),
(38, 37, 1, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 1, 120000.00, 'ARS', 1, '2025-09-23 15:04:26');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `checkout_certificaciones`
--

CREATE TABLE `checkout_certificaciones` (
  `id_certificacion` int(11) NOT NULL,
  `creado_por` int(11) DEFAULT NULL,
  `acepta_tyc` tinyint(1) NOT NULL DEFAULT 0,
  `precio_total` decimal(12,2) NOT NULL DEFAULT 0.00,
  `moneda` varchar(10) NOT NULL DEFAULT 'ARS',
  `id_curso` int(11) NOT NULL,
  `pdf_path` varchar(255) DEFAULT NULL,
  `pdf_nombre` varchar(255) DEFAULT NULL,
  `pdf_mime` varchar(120) DEFAULT 'application/pdf',
  `observaciones` varchar(255) DEFAULT NULL,
  `id_estado` int(11) NOT NULL DEFAULT 1,
  `creado_en` datetime NOT NULL DEFAULT current_timestamp(),
  `nombre` varchar(100) DEFAULT NULL,
  `apellido` varchar(100) DEFAULT NULL,
  `email` varchar(255) DEFAULT NULL,
  `telefono` varchar(30) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `checkout_certificaciones`
--

INSERT INTO `checkout_certificaciones` (`id_certificacion`, `creado_por`, `acepta_tyc`, `precio_total`, `moneda`, `id_curso`, `pdf_path`, `pdf_nombre`, `pdf_mime`, `observaciones`, `id_estado`, `creado_en`, `nombre`, `apellido`, `email`, `telefono`) VALUES
(1, 37, 1, 220000.00, 'ARS', 1, NULL, NULL, NULL, NULL, 2, '2025-09-23 15:04:26', 'prueba', '1', 'pruebaa@mail.com', NULL),
(2, 37, 1, 220000.00, 'ARS', 2, NULL, NULL, NULL, NULL, 1, '2025-09-23 15:04:26', NULL, NULL, NULL, NULL);

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `checkout_mercadopago`
--

CREATE TABLE `checkout_mercadopago` (
  `id_mp` int(11) NOT NULL,
  `id_pago` int(11) NOT NULL,
  `preference_id` varchar(80) NOT NULL,
  `init_point` varchar(255) NOT NULL,
  `sandbox_init_point` varchar(255) DEFAULT NULL,
  `external_reference` varchar(120) DEFAULT NULL,
  `status` varchar(60) NOT NULL DEFAULT 'pendiente',
  `status_detail` varchar(120) DEFAULT NULL,
  `payment_id` varchar(60) DEFAULT NULL,
  `payment_type` varchar(80) DEFAULT NULL,
  `payer_email` varchar(150) DEFAULT NULL,
  `payload` longtext DEFAULT NULL,
  `creado_en` datetime NOT NULL DEFAULT current_timestamp(),
  `actualizado_en` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `checkout_mercadopago`
--

INSERT INTO `checkout_mercadopago` (`id_mp`, `id_pago`, `preference_id`, `init_point`, `sandbox_init_point`, `external_reference`, `status`, `status_detail`, `payment_id`, `payment_type`, `payer_email`, `payload`, `creado_en`, `actualizado_en`) VALUES
(1, 17, '1578491289-3dc2d4a0-c233-4383-8fc2-a275d8bf0629', 'https://www.mercadopago.com.ar/checkout/v1/redirect?pref_id=1578491289-3dc2d4a0-c233-4383-8fc2-a275d8bf0629', 'https://sandbox.mercadopago.com.ar/checkout/v1/redirect?pref_id=1578491289-3dc2d4a0-c233-4383-8fc2-a275d8bf0629', 'insc-20', 'pendiente', NULL, NULL, NULL, NULL, '{\"preference\":{\"id\":\"1578491289-3dc2d4a0-c233-4383-8fc2-a275d8bf0629\"}}', '2025-09-23 20:19:28', '2025-09-23 20:19:28'),
(2, 19, '1578491289-1ec5ce20-4459-4b24-aa11-70df7d0c9aa1', 'https://www.mercadopago.com.ar/checkout/v1/redirect?pref_id=1578491289-1ec5ce20-4459-4b24-aa11-70df7d0c9aa1', 'https://sandbox.mercadopago.com.ar/checkout/v1/redirect?pref_id=1578491289-1ec5ce20-4459-4b24-aa11-70df7d0c9aa1', 'insc-22', 'pendiente', NULL, NULL, NULL, NULL, '{\"preference\":{\"id\":\"1578491289-1ec5ce20-4459-4b24-aa11-70df7d0c9aa1\"}}', '2025-09-23 20:23:10', '2025-09-23 20:23:10'),
(3, 20, '1578491289-69582870-e3d9-4b80-b947-c7b8557b940f', 'https://www.mercadopago.com.ar/checkout/v1/redirect?pref_id=1578491289-69582870-e3d9-4b80-b947-c7b8557b940f', 'https://sandbox.mercadopago.com.ar/checkout/v1/redirect?pref_id=1578491289-69582870-e3d9-4b80-b947-c7b8557b940f', 'insc-23', 'pendiente', NULL, NULL, NULL, NULL, '{\"preference\":{\"id\":\"1578491289-69582870-e3d9-4b80-b947-c7b8557b940f\"}}', '2025-09-23 20:27:50', '2025-09-23 20:27:50'),
(4, 21, '1578491289-fb336608-fb85-45ab-af4d-d0a94b97a14b', 'https://www.mercadopago.com.ar/checkout/v1/redirect?pref_id=1578491289-fb336608-fb85-45ab-af4d-d0a94b97a14b', 'https://sandbox.mercadopago.com.ar/checkout/v1/redirect?pref_id=1578491289-fb336608-fb85-45ab-af4d-d0a94b97a14b', 'insc-24', 'pendiente', NULL, NULL, NULL, NULL, '{\"preference\":{\"id\":\"1578491289-fb336608-fb85-45ab-af4d-d0a94b97a14b\"}}', '2025-09-23 20:28:18', '2025-09-23 20:28:18'),
(5, 22, '1578491289-09ddb966-44d6-4502-b54b-21151b7b5342', 'https://www.mercadopago.com.ar/checkout/v1/redirect?pref_id=1578491289-09ddb966-44d6-4502-b54b-21151b7b5342', 'https://sandbox.mercadopago.com.ar/checkout/v1/redirect?pref_id=1578491289-09ddb966-44d6-4502-b54b-21151b7b5342', 'insc-25', 'pendiente', NULL, NULL, NULL, NULL, '{\"preference\":{\"id\":\"1578491289-09ddb966-44d6-4502-b54b-21151b7b5342\"}}', '2025-09-23 20:29:38', '2025-09-23 20:29:38');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `checkout_pagos`
--

CREATE TABLE `checkout_pagos` (
  `id_pago` int(11) NOT NULL,
  `id_certificacion` int(11) DEFAULT NULL,
  `id_capacitacion` int(11) DEFAULT NULL,
  `metodo` varchar(40) NOT NULL,
  `estado` varchar(30) NOT NULL DEFAULT 'pendiente',
  `monto` decimal(12,2) NOT NULL DEFAULT 0.00,
  `moneda` varchar(10) NOT NULL DEFAULT 'ARS',
  `comprobante_path` varchar(255) DEFAULT NULL,
  `comprobante_nombre` varchar(255) DEFAULT NULL,
  `comprobante_mime` varchar(120) DEFAULT NULL,
  `comprobante_tamano` int(10) UNSIGNED DEFAULT NULL,
  `observaciones` varchar(255) DEFAULT NULL,
  `creado_en` datetime NOT NULL DEFAULT current_timestamp(),
  `actualizado_en` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `checkout_pagos`
--

INSERT INTO `checkout_pagos` (`id_pago`, `id_certificacion`, `id_capacitacion`, `metodo`, `estado`, `monto`, `moneda`, `comprobante_path`, `comprobante_nombre`, `comprobante_mime`, `comprobante_tamano`, `observaciones`, `creado_en`, `actualizado_en`) VALUES
(3, 6, 0, 'mercado_pago', 'pendiente', 120000.00, 'ARS', NULL, NULL, NULL, NULL, NULL, '2025-09-23 15:04:26', '2025-09-23 15:04:26'),
(4, 7, 0, 'transferencia', 'pendiente', 120000.00, 'ARS', 'uploads/comprobantes/comp_20250923200550_ca34f49565cb480f.pdf', 'EBOOK_200_PROMPTS_MDEV1.pdf', 'application/pdf', 2881165, NULL, '2025-09-23 15:05:50', '2025-09-23 15:05:50'),
(17, 20, 0, 'mercado_pago', 'pendiente', 120000.00, 'ARS', NULL, NULL, NULL, NULL, NULL, '2025-09-23 20:19:27', '2025-09-23 20:19:27'),
(19, 22, 0, 'mercado_pago', 'pendiente', 120000.00, 'ARS', NULL, NULL, NULL, NULL, NULL, '2025-09-23 20:23:09', '2025-09-23 20:23:09'),
(20, 23, 0, 'mercado_pago', 'pendiente', 120000.00, 'ARS', NULL, NULL, NULL, NULL, NULL, '2025-09-23 20:27:49', '2025-09-23 20:27:49'),
(21, 24, 0, 'mercado_pago', 'pendiente', 120000.00, 'ARS', NULL, NULL, NULL, NULL, NULL, '2025-09-23 20:28:18', '2025-09-23 20:28:18'),
(22, 25, 0, 'mercado_pago', 'pendiente', 120000.00, 'ARS', NULL, NULL, NULL, NULL, NULL, '2025-09-23 20:29:37', '2025-09-23 20:29:37'),
(25, 28, 0, 'transferencia', 'pendiente', 120000.00, 'ARS', 'uploads/comprobantes/comp_20250924185633_5bd0174a6d752794.pdf', 'Instructivo-tablero-CPEC-actualizado.pdf', 'application/pdf', 1677309, 'comprobante', '2025-09-24 13:56:33', '2025-09-24 13:56:33');

--
-- Disparadores `checkout_pagos`
--
DELIMITER $$
CREATE TRIGGER `trg_checkout_pagos_bi` BEFORE INSERT ON `checkout_pagos` FOR EACH ROW BEGIN
  IF ( (NEW.id_certificacion IS NULL AND NEW.id_capacitacion IS NULL)
       OR (NEW.id_certificacion IS NOT NULL AND NEW.id_capacitacion IS NOT NULL) ) THEN
    SIGNAL SQLSTATE '45000'
      SET MESSAGE_TEXT = 'Debe existir exactamente una: id_certificacion O id_capacitacion';
  END IF;
END
$$
DELIMITER ;
DELIMITER $$
CREATE TRIGGER `trg_checkout_pagos_bu` BEFORE UPDATE ON `checkout_pagos` FOR EACH ROW BEGIN
  IF ( (NEW.id_certificacion IS NULL AND NEW.id_capacitacion IS NULL)
       OR (NEW.id_certificacion IS NOT NULL AND NEW.id_capacitacion IS NOT NULL) ) THEN
    SIGNAL SQLSTATE '45000'
      SET MESSAGE_TEXT = 'Debe existir exactamente una: id_certificacion O id_capacitacion';
  END IF;
END
$$
DELIMITER ;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `compra_items`
--

CREATE TABLE `compra_items` (
  `id_item` int(11) NOT NULL,
  `id_compra` int(11) NOT NULL,
  `id_curso` int(11) NOT NULL,
  `id_modalidad` int(11) DEFAULT NULL,
  `cantidad` int(11) NOT NULL DEFAULT 1,
  `precio_unitario` decimal(10,2) NOT NULL,
  `titulo_snapshot` varchar(150) DEFAULT NULL,
  `descripcion_snapshot` text DEFAULT NULL,
  `creado_en` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `compra_items`
--

INSERT INTO `compra_items` (`id_item`, `id_compra`, `id_curso`, `id_modalidad`, `cantidad`, `precio_unitario`, `titulo_snapshot`, `descripcion_snapshot`, `creado_en`) VALUES
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

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `cursos`
--

CREATE TABLE `cursos` (
  `id_curso` int(11) NOT NULL,
  `nombre_curso` varchar(100) NOT NULL,
  `descripcion_curso` varchar(255) NOT NULL,
  `duracion` int(10) NOT NULL,
  `objetivos` varchar(100) NOT NULL,
  `id_complejidad` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `cursos`
--

INSERT INTO `cursos` (`id_curso`, `nombre_curso`, `descripcion_curso`, `duracion`, `objetivos`, `id_complejidad`) VALUES
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

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `curso_modalidad`
--

CREATE TABLE `curso_modalidad` (
  `id_curso` int(11) NOT NULL,
  `id_modalidad` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `curso_precio_hist`
--

CREATE TABLE `curso_precio_hist` (
  `id` int(11) NOT NULL,
  `id_curso` int(11) NOT NULL,
  `precio` decimal(10,2) NOT NULL,
  `moneda` char(3) NOT NULL DEFAULT 'ARS',
  `vigente_desde` datetime NOT NULL,
  `vigente_hasta` datetime DEFAULT NULL,
  `comentario` varchar(255) DEFAULT NULL,
  `creado_en` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `curso_precio_hist`
--

INSERT INTO `curso_precio_hist` (`id`, `id_curso`, `precio`, `moneda`, `vigente_desde`, `vigente_hasta`, `comentario`, `creado_en`) VALUES
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
(18, 10, 90000.00, 'ARS', '2025-02-01 00:00:00', '2025-09-26 00:02:59', 'Tarifa 2025', '2025-09-16 19:40:17'),
(20, 6, 1200000.00, 'ARS', '2025-09-27 21:58:00', NULL, 'comentario', '2025-09-16 21:59:03'),
(21, 6, 1300000.00, 'ARS', '2025-09-15 22:07:00', '2025-09-26 22:06:59', 'comentario', '2025-09-16 22:07:34'),
(22, 6, 180000.00, 'ARS', '2025-09-26 22:07:00', '2025-09-27 21:57:59', 'mas', '2025-09-16 22:07:55'),
(23, 10, 999999.00, 'ARS', '2025-09-26 00:03:00', NULL, 'inflacion loquita', '2025-09-18 00:03:38');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `empresa_trabajadores`
--

CREATE TABLE `empresa_trabajadores` (
  `id` int(11) NOT NULL,
  `id_empresa` int(11) NOT NULL,
  `id_trabajador` int(11) NOT NULL,
  `asignado_en` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `empresa_trabajadores`
--

INSERT INTO `empresa_trabajadores` (`id`, `id_empresa`, `id_trabajador`, `asignado_en`) VALUES
(5, 37, 3, '2025-09-25 10:38:37'),
(6, 37, 39, '2025-09-29 08:21:27');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `estado`
--

CREATE TABLE `estado` (
  `id_estado` int(11) NOT NULL,
  `nombre_estado` varchar(20) NOT NULL,
  `descripcion_estado` varchar(100) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `estado`
--

INSERT INTO `estado` (`id_estado`, `nombre_estado`, `descripcion_estado`) VALUES
(1, 'registrado', 'El usuario se registro pero no inicio sesion nunca en la pagina'),
(2, 'logueado', 'El usuario inicio la sesion en la pagina');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `estados_inscripciones`
--

CREATE TABLE `estados_inscripciones` (
  `id_estado` int(11) NOT NULL,
  `nombre_estado` varchar(50) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `estados_inscripciones`
--

INSERT INTO `estados_inscripciones` (`id_estado`, `nombre_estado`) VALUES
(1, 'pendiente'),
(2, 'aprobado'),
(3, 'pagado'),
(4, 'rechazado');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `historico_estado_capacitaciones`
--

CREATE TABLE `historico_estado_capacitaciones` (
  `id_historico` int(11) NOT NULL,
  `id_capacitacion` int(11) NOT NULL,
  `id_estado` int(11) NOT NULL,
  `cambiado_en` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `historico_estado_capacitaciones`
--

INSERT INTO `historico_estado_capacitaciones` (`id_historico`, `id_capacitacion`, `id_estado`, `cambiado_en`) VALUES
(1, 31, 2, '2025-09-29 07:55:03'),
(2, 34, 2, '2025-09-29 07:55:03'),
(3, 29, 2, '2025-09-29 08:21:35');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `historico_estado_certificaciones`
--

CREATE TABLE `historico_estado_certificaciones` (
  `id_historico` int(11) NOT NULL,
  `id_certificacion` int(11) NOT NULL,
  `id_estado` int(11) NOT NULL,
  `cambiado_en` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `historico_estado_certificaciones`
--

INSERT INTO `historico_estado_certificaciones` (`id_historico`, `id_certificacion`, `id_estado`, `cambiado_en`) VALUES
(1, 1, 2, '2025-09-29 09:34:04');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `modalidades`
--

CREATE TABLE `modalidades` (
  `id_modalidad` int(11) NOT NULL,
  `nombre_modalidad` varchar(255) NOT NULL,
  `descripcion_modalidad` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `modalidades`
--

INSERT INTO `modalidades` (`id_modalidad`, `nombre_modalidad`, `descripcion_modalidad`) VALUES
(1, 'Presencial', 'Clases que se realizan en un lugar físico, con la presencia del instructor y los estudiantes.'),
(2, 'Online', 'Clases que se realizan completamente en línea, sin necesidad de presencia física.'),
(3, 'Híbrido', 'Combinación de clases presenciales y en línea.');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `permisos`
--

CREATE TABLE `permisos` (
  `id_permiso` int(11) NOT NULL,
  `nombre_permiso` varchar(20) NOT NULL,
  `descripcion_permiso` varchar(100) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `permisos`
--

INSERT INTO `permisos` (`id_permiso`, `nombre_permiso`, `descripcion_permiso`) VALUES
(1, 'admin', 'Permiso de edicion'),
(2, 'usuario', 'Solo vista '),
(3, 'rrhh', 'Permisos para asignar empleados y cursos a esos empleados'),
(4, 'Trabajador_asignado', 'Perfil asignado a los trabajadores de cada empresa');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `recuperaciones_contrasena`
--

CREATE TABLE `recuperaciones_contrasena` (
  `id_reset` int(11) NOT NULL,
  `id_usuario` int(11) NOT NULL,
  `token` varchar(128) NOT NULL,
  `expiracion` datetime NOT NULL,
  `utilizado` tinyint(1) NOT NULL DEFAULT 0,
  `creado_en` datetime NOT NULL DEFAULT current_timestamp(),
  `usado_en` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Volcado de datos para la tabla `recuperaciones_contrasena`
--

INSERT INTO `recuperaciones_contrasena` (`id_reset`, `id_usuario`, `token`, `expiracion`, `utilizado`, `creado_en`, `usado_en`) VALUES
(1, 0, 'cf82c84e1f0a5e449903e13bcfd52f68f1a46578ce1e9f59ce64d2d54beb39a2', '2025-09-30 05:51:58', 1, '2025-09-30 04:51:58', '2025-09-30 04:52:53');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `usuarios`
--

CREATE TABLE `usuarios` (
  `id_usuario` int(11) NOT NULL,
  `email` varchar(50) NOT NULL,
  `nombre` varchar(255) DEFAULT NULL,
  `apellido` varchar(255) DEFAULT NULL,
  `telefono` varchar(50) DEFAULT NULL,
  `clave` varchar(60) NOT NULL,
  `id_estado` int(11) NOT NULL,
  `id_permiso` int(11) NOT NULL,
  `token_verificacion` varchar(64) CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL,
  `token_expiracion` datetime DEFAULT NULL,
  `verificado` tinyint(1) UNSIGNED NOT NULL DEFAULT 0,
  `google_sub` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `usuarios`
--

INSERT INTO `usuarios` (`id_usuario`, `email`, `nombre`, `apellido`, `telefono`, `clave`, `id_estado`, `id_permiso`, `token_verificacion`, `token_expiracion`, `verificado`, `google_sub`) VALUES
(1, 'pruebas@institutodeoperadores.com', 'Pruebas', 'Melillo', NULL, '$2y$10$G5oCy7VKVCfM0lcW9q/Ig.tETnIh3Gy9X5UmiPLiWBC8cm/E6Ffwa', 2, 2, NULL, NULL, 1, '104201308450167003224'),
(2, 'administracion@institutodeoperadores.com', '', '', NULL, '$2y$10$BOYvdEyEt4tDuXinuc1b3ek57vlRgcYZXmdn4AJsLZ5KcYJjajSma', 2, 1, NULL, NULL, 1, NULL),
(3, 'pruebaa@mail.com', 'prueba', '1', NULL, '$2y$10$kCMOMQEBqP7zTcKGjq0iNuZmf5R5h6qPmHqmeUEqssB8XqDPeg05u', 1, 4, NULL, NULL, 0, NULL),
(31, 'torus22129@gmail.com', 'aa', 'aaa', '03571319798', '$2y$10$CIOfT8hLcmHRckAql6fRIOYFtRcYPa11eo5Per2Y1tiq5YbyJAkTi', 1, 3, NULL, NULL, 1, NULL),
(37, 'tomi22129@gmail.com', 'Tom', 'Rap', '+5222222', '$2y$10$2/wrIoDerHg3pwZ.YzI3hOW20tUP4pAcAsAU375Fpk.jbLHu3H0v6', 1, 3, NULL, NULL, 1, '116105777166954342116'),
(39, 'tomasraptopulos@gmail.com', 'Tomas', 'Raptopulos', '571319798', '$2y$10$/oK3tHteDx3z/5VfxBsw6uO0mXQLupeijAu3Gy3k7wCvW4MfGahgK', 1, 4, NULL, NULL, 1, NULL),
(41, 'juanimelillo@gmail.com', 'Juani', 'Melillo', NULL, '$2y$10$KfEzcYTP.yarL7uOLfRZJO1agR1gyKG8K6hNFPIxsq8AxQuvoBvDa', 1, 2, NULL, NULL, 1, '118284451710276177062'),
(42, 'juanimellilo@gmail.com', NULL, NULL, NULL, '$2y$10$egI9cKpzeIFFxHj1x2dfTOqw9DVkP5ndiaqL8bHZNTvRQOYFzOEDm', 1, 2, NULL, NULL, 0, NULL);

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `v_cursos_rrhh`
--

CREATE TABLE `v_cursos_rrhh` (
  `id_usuario` int(11) DEFAULT NULL,
  `id_curso` int(11) DEFAULT NULL,
  `tipo_curso` varchar(13) DEFAULT NULL,
  `cantidad` bigint(21) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Índices para tablas volcadas
--

--
-- Indices de la tabla `banner`
--
ALTER TABLE `banner`
  ADD PRIMARY KEY (`id_banner`);

--
-- Indices de la tabla `checkout_capacitaciones`
--
ALTER TABLE `checkout_capacitaciones`
  ADD PRIMARY KEY (`id_capacitacion`),
  ADD KEY `fk_checkout_creado_por` (`creado_por`),
  ADD KEY `fk_checkout_id_curso` (`id_curso`),
  ADD KEY `fk_checkout_id_estado` (`id_estado`);

--
-- Indices de la tabla `checkout_certificaciones`
--
ALTER TABLE `checkout_certificaciones`
  ADD PRIMARY KEY (`id_certificacion`),
  ADD KEY `fk_certificaciones_usuarios` (`creado_por`),
  ADD KEY `fk_certificaciones_cursos` (`id_curso`),
  ADD KEY `fk_certificaciones_estados` (`id_estado`);

--
-- Indices de la tabla `checkout_mercadopago`
--
ALTER TABLE `checkout_mercadopago`
  ADD PRIMARY KEY (`id_mp`),
  ADD KEY `fk_id_pago_checkout_pagos` (`id_pago`);

--
-- Indices de la tabla `checkout_pagos`
--
ALTER TABLE `checkout_pagos`
  ADD PRIMARY KEY (`id_pago`),
  ADD KEY `fk_pagos_certificacion` (`id_certificacion`),
  ADD KEY `fk_pagos_capacitacion` (`id_capacitacion`);

--
-- Indices de la tabla `cursos`
--
ALTER TABLE `cursos`
  ADD PRIMARY KEY (`id_curso`);

--
-- Indices de la tabla `curso_precio_hist`
--
ALTER TABLE `curso_precio_hist`
  ADD KEY `FK_id_curso` (`id_curso`);

--
-- Indices de la tabla `empresa_trabajadores`
--
ALTER TABLE `empresa_trabajadores`
  ADD PRIMARY KEY (`id`);

--
-- Indices de la tabla `estado`
--
ALTER TABLE `estado`
  ADD PRIMARY KEY (`id_estado`);

--
-- Indices de la tabla `estados_inscripciones`
--
ALTER TABLE `estados_inscripciones`
  ADD PRIMARY KEY (`id_estado`);

--
-- Indices de la tabla `historico_estado_capacitaciones`
--
ALTER TABLE `historico_estado_capacitaciones`
  ADD PRIMARY KEY (`id_historico`),
  ADD KEY `fk_id_capacitaciones` (`id_capacitacion`),
  ADD KEY `fk_id_estad__end` (`id_estado`);

--
-- Indices de la tabla `historico_estado_certificaciones`
--
ALTER TABLE `historico_estado_certificaciones`
  ADD PRIMARY KEY (`id_historico`),
  ADD KEY `fk_id_estado` (`id_estado`),
  ADD KEY `fk_id_certificaciones` (`id_certificacion`);

--
-- Indices de la tabla `modalidades`
--
ALTER TABLE `modalidades`
  ADD PRIMARY KEY (`id_modalidad`);

--
-- Indices de la tabla `permisos`
--
ALTER TABLE `permisos`
  ADD PRIMARY KEY (`id_permiso`);

--
-- Indices de la tabla `recuperaciones_contrasena`
--
ALTER TABLE `recuperaciones_contrasena`
  ADD PRIMARY KEY (`id_reset`),
  ADD KEY `fk_id_usuario_usuarios_end` (`id_usuario`);

--
-- Indices de la tabla `usuarios`
--
ALTER TABLE `usuarios`
  ADD PRIMARY KEY (`id_usuario`),
  ADD UNIQUE KEY `uq_usuarios_email` (`email`),
  ADD UNIQUE KEY `google_sub` (`google_sub`),
  ADD KEY `fk_id_estado_usuario` (`id_estado`),
  ADD KEY `fk_permisos` (`id_permiso`);

--
-- AUTO_INCREMENT de las tablas volcadas
--

--
-- AUTO_INCREMENT de la tabla `banner`
--
ALTER TABLE `banner`
  MODIFY `id_banner` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT de la tabla `checkout_capacitaciones`
--
ALTER TABLE `checkout_capacitaciones`
  MODIFY `id_capacitacion` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=39;

--
-- AUTO_INCREMENT de la tabla `checkout_certificaciones`
--
ALTER TABLE `checkout_certificaciones`
  MODIFY `id_certificacion` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT de la tabla `checkout_mercadopago`
--
ALTER TABLE `checkout_mercadopago`
  MODIFY `id_mp` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT de la tabla `checkout_pagos`
--
ALTER TABLE `checkout_pagos`
  MODIFY `id_pago` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=26;

--
-- AUTO_INCREMENT de la tabla `cursos`
--
ALTER TABLE `cursos`
  MODIFY `id_curso` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;

--
-- AUTO_INCREMENT de la tabla `empresa_trabajadores`
--
ALTER TABLE `empresa_trabajadores`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT de la tabla `estado`
--
ALTER TABLE `estado`
  MODIFY `id_estado` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT de la tabla `estados_inscripciones`
--
ALTER TABLE `estados_inscripciones`
  MODIFY `id_estado` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT de la tabla `historico_estado_capacitaciones`
--
ALTER TABLE `historico_estado_capacitaciones`
  MODIFY `id_historico` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT de la tabla `historico_estado_certificaciones`
--
ALTER TABLE `historico_estado_certificaciones`
  MODIFY `id_historico` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT de la tabla `modalidades`
--
ALTER TABLE `modalidades`
  MODIFY `id_modalidad` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT de la tabla `permisos`
--
ALTER TABLE `permisos`
  MODIFY `id_permiso` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT de la tabla `recuperaciones_contrasena`
--
ALTER TABLE `recuperaciones_contrasena`
  MODIFY `id_reset` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT de la tabla `usuarios`
--
ALTER TABLE `usuarios`
  MODIFY `id_usuario` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=43;

--
-- Restricciones para tablas volcadas
--

--
-- Filtros para la tabla `checkout_capacitaciones`
--
ALTER TABLE `checkout_capacitaciones`
  ADD CONSTRAINT `fk_checkout_creado_por` FOREIGN KEY (`creado_por`) REFERENCES `usuarios` (`id_usuario`),
  ADD CONSTRAINT `fk_checkout_id_curso` FOREIGN KEY (`id_curso`) REFERENCES `cursos` (`id_curso`),
  ADD CONSTRAINT `fk_checkout_id_estado` FOREIGN KEY (`id_estado`) REFERENCES `estados_inscripciones` (`id_estado`);

--
-- Filtros para la tabla `checkout_certificaciones`
--
ALTER TABLE `checkout_certificaciones`
  ADD CONSTRAINT `fk_certificaciones_cursos` FOREIGN KEY (`id_curso`) REFERENCES `cursos` (`id_curso`),
  ADD CONSTRAINT `fk_certificaciones_estados` FOREIGN KEY (`id_estado`) REFERENCES `estados_inscripciones` (`id_estado`),
  ADD CONSTRAINT `fk_certificaciones_usuarios` FOREIGN KEY (`creado_por`) REFERENCES `usuarios` (`id_usuario`);

--
-- Filtros para la tabla `checkout_mercadopago`
--
ALTER TABLE `checkout_mercadopago`
  ADD CONSTRAINT `fk_id_pago_checkout_pagos` FOREIGN KEY (`id_pago`) REFERENCES `checkout_pagos` (`id_pago`);

--
-- Filtros para la tabla `checkout_pagos`
--
ALTER TABLE `checkout_pagos`
  ADD CONSTRAINT `fk_pagos_capacitacion` FOREIGN KEY (`id_capacitacion`) REFERENCES `checkout_capacitacion` (`id_capacitacion`) ON DELETE NO ACTION ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_pagos_certificacion` FOREIGN KEY (`id_certificacion`) REFERENCES `checkout_certificacion` (`id_certificacion`) ON DELETE NO ACTION ON UPDATE CASCADE;

--
-- Filtros para la tabla `curso_precio_hist`
--
ALTER TABLE `curso_precio_hist`
  ADD CONSTRAINT `FK_id_curso` FOREIGN KEY (`id_curso`) REFERENCES `cursos` (`id_curso`);

--
-- Filtros para la tabla `historico_estado_capacitaciones`
--
ALTER TABLE `historico_estado_capacitaciones`
  ADD CONSTRAINT `fk_id_capacitaciones` FOREIGN KEY (`id_capacitacion`) REFERENCES `checkout_capacitaciones` (`id_capacitacion`),
  ADD CONSTRAINT `fk_id_estad__end` FOREIGN KEY (`id_estado`) REFERENCES `estados_inscripciones` (`id_estado`);

--
-- Filtros para la tabla `historico_estado_certificaciones`
--
ALTER TABLE `historico_estado_certificaciones`
  ADD CONSTRAINT `fk_id_certificaciones` FOREIGN KEY (`id_certificacion`) REFERENCES `checkout_certificaciones` (`id_certificacion`),
  ADD CONSTRAINT `fk_id_estado` FOREIGN KEY (`id_estado`) REFERENCES `estados_inscripciones` (`id_estado`);

--
-- Filtros para la tabla `recuperaciones_contrasena`
--
ALTER TABLE `recuperaciones_contrasena`
  ADD CONSTRAINT `fk_id_usuario_usuarios_end` FOREIGN KEY (`id_usuario`) REFERENCES `usuarios` (`id_usuario`);

--
-- Filtros para la tabla `usuarios`
--
ALTER TABLE `usuarios`
  ADD CONSTRAINT `fk_id_estado_usuario` FOREIGN KEY (`id_estado`) REFERENCES `estado` (`id_estado`),
  ADD CONSTRAINT `fk_permisos` FOREIGN KEY (`id_permiso`) REFERENCES `permisos` (`id_permiso`);
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
