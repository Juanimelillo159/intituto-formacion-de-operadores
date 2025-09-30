-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Servidor: 127.0.0.1
-- Tiempo de generación: 23-09-2025 a las 19:51:04
-- Versión del servidor: 10.4.32-MariaDB
-- Versión de PHP: 8.2.12

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Base de datos: `formacionoperadores`
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
(5, 'promo 7', '1.png'),
(6, 'promo 2', '2.png');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `checkout_inscripciones`
--

CREATE TABLE `checkout_inscripciones` (
  `id_inscripcion` int(11) NOT NULL,
  `id_curso` int(11) NOT NULL,
  `nombre` varchar(120) NOT NULL,
  `apellido` varchar(120) NOT NULL,
  `email` varchar(150) NOT NULL,
  `telefono` varchar(60) NOT NULL,
  `dni` varchar(40) DEFAULT NULL,
  `direccion` varchar(200) DEFAULT NULL,
  `ciudad` varchar(120) DEFAULT NULL,
  `provincia` varchar(120) DEFAULT NULL,
  `pais` varchar(120) NOT NULL DEFAULT 'Argentina',
  `acepta_tyc` tinyint(1) NOT NULL DEFAULT 0,
  `precio_total` decimal(12,2) NOT NULL DEFAULT 0.00,
  `moneda` varchar(10) NOT NULL DEFAULT 'ARS',
  `creado_en` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

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

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `checkout_pagos`
--

CREATE TABLE `checkout_pagos` (
  `id_pago` int(11) NOT NULL,
  `id_inscripcion` int(11) NOT NULL,
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
  `cronograma` text DEFAULT NULL,
  `publico` text DEFAULT NULL,
  `programa` text DEFAULT NULL,
  `requisitos` text DEFAULT NULL,
  `observaciones` text DEFAULT NULL,
  `complejidad` varchar(200) DEFAULT 'cualquiera'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `cursos`
--

INSERT INTO `cursos` (`id_curso`, `nombre_curso`, `descripcion_curso`, `duracion`, `objetivos`, `cronograma`, `publico`, `programa`, `requisitos`, `observaciones`, `complejidad`) VALUES
(1, 'Operador de Grúa Móvil', 'Curso para la operación segura de grúas móviles.', 40, 'Operar grúas móviles con seguridad; interpretar tablas de carga; seleccionar eslingas y accesorios; ', 'Duración 20 h: 4 módulos teórico-prácticos de 5 h. Día 1: fundamentos y normativa; Día 2: tablas de carga y estabilidad; Día 3: accesorios y rigging; Día 4: prácticas y evaluación.', 'Operadores de grúa móvil, ayudantes de izaje y supervisores de maniobras.', 'M1 Fundamentos y normativa | M2 Estabilidad y tablas de carga | M3 Accesorios de izaje y señales | M4 Prácticas en campo y evaluación.', 'Apto médico vigente; experiencia básica en izajes; EPP completo; comprensión de señales.', 'Traer casco, guantes y calzado de seguridad. Evaluación teórico–práctica al final.', 'cualquiera'),
(2, 'Operador de Grúa Móvil de Pluma Articulada', 'Curso especializado en grúas de pluma articulada.', 40, 'Dominar operación de grúas articuladas (knuckle boom); uso correcto de estabilizadores; lectura de d', '16 h: 2 jornadas de 8 h. Teoría por la mañana y prácticas por la tarde.', 'Operadores de camión con pluma articulada, personal de logística y montaje.', 'Seguridad específica en grúas articuladas | Estabilizadores y configuración | Tablas de carga y radios | Prácticas con distintos accesorios.', 'Apto físico; licencia acorde al vehículo; nociones de amarre y señalización.', 'Se recomienda experiencia previa en camión/plataforma.', 'cualquiera'),
(3, 'Operador de Hidroelevador', 'Curso para la operación de equipos hidro elevadores.', 30, 'Trabajar en altura con plataformas sobre camión (hidroelevador) cumpliendo normas; inspecciones prev', '12 h: 1 día y medio. Teoría, inspecciones y prácticas controladas.', 'Técnicos de mantenimiento, alumbrado público, poda y telecomunicaciones.', 'Normativa y riesgos en altura | Inspección pre-uso | Operación segura del cesto | Sistemas de anclaje y rescate básico.', 'Apto de trabajo en altura; formación básica en EPP; arnés con doble cabo.', 'Obligatorio uso de arnés homologado y líneas de vida.', 'cualquiera'),
(4, 'Operador de Autoelevador', 'Curso de manejo de autoelevadores.', 30, 'Operar autoelevadores de forma segura; gestión de estabilidad y pasillos; manipulación de cargas.', '10 h: teoría (4 h) + prácticas (6 h) en circuito con carga.', 'Operadores de depósito, logística interna y distribución.', 'Estabilidad triángulo y contrapeso | Reglas en pasillos y rampas | Manipulación de pallets | Prácticas con obstáculos.', 'Apto médico laboral; visión adecuada; EPP (chaleco, calzado, casco).', 'Se evalúa destreza en circuito y conocimiento de check-list diario.', 'cualquiera'),
(5, 'Operador Rigger', 'Curso sobre funciones de rigger en maniobras de izaje.', 35, 'Planificar izajes; calcular cargas en eslingas y aparejos; dirigir maniobras con señales.', '20 h: 3 sesiones teóricas + 1 jornada práctica.', 'Riggers, apuntadores, jefes de maniobra y supervisores HSE.', 'Cálculo de tensión en eslingas | Selección de accesorios | Señalización y roles | Simulaciones y prácticas de izaje.', 'Conocimientos básicos de izaje; lectura de tablas; EPP.', 'Se recomienda traer ejemplos reales para análisis de planos de izaje.', 'cualquiera'),
(6, 'Operador de Motoniveladora', 'Curso de operación de motoniveladoras para obras viales.', 50, 'Operar motoniveladora en nivelación, cunetas y pendientes con seguridad y productividad.', '24 h: configuración de cuchilla, controles y prácticas en campo.', 'Maquinistas viales y personal de obras civiles.', 'Componentes y mantenimiento | Técnicas de nivelación | Conformación de cunetas | Prácticas supervisadas.', 'Experiencia básica en maquinaria vial; EPP; apto médico.', 'Se trabaja en circuito de obra; condiciones climáticas pueden modificar el cronograma.', 'cualquiera'),
(7, 'Operador de Cargadora', 'Curso para la operación de cargadoras frontales.', 40, 'Optimizar el ciclo de carga, apilado y carga de camiones; control de estabilidad y visibilidad.', '16 h: teoría (6 h) + prácticas (10 h).', 'Operadores de cantera, áridos y movimientos de suelo.', 'Seguridad y puntos ciegos | Técnicas de carga | Gestión de pilas y taludes | Rutinas de pre-uso y combustible.', 'Experiencia mínima deseable; EPP; apto médico.', 'Se enfatiza trabajo seguro cerca de camiones y tráfico interno.', 'cualquiera'),
(8, 'Operador de Retroexcavadora', 'Curso de uso de retroexcavadoras en obras civiles.', 45, 'Realizar excavaciones, zanjas y rellenos asegurando estabilidad y señalización del frente de obra.', '18 h: teoría (6 h) + prácticas (12 h) con distintos implementos.', 'Operadores en obras civiles, servicios y mantenimiento.', 'Estabilidad y radio de giro | Zanjas, taludes y entibaciones | Carga a camión | Señalización y control de accesos.', 'Conocimientos básicos de obra civil; EPP completo.', 'Se revisan procedimientos de trabajo cerca de redes enterradas.', 'cualquiera'),
(9, 'Operador de Excavadora', 'Curso de manejo de excavadoras hidráulicas.', 45, 'Operar excavadora hidráulica en movimientos de tierra, carguío y zanjeo con eficiencia y control de ', '24 h: teoría (8 h) + prácticas (16 h).', 'Maquinistas de minería, canteras y obras civiles.', 'Sistemas y mantenimientos críticos | Técnicas de excavación | Trabajo en pendientes | Prácticas con diferentes cucharones.', 'Experiencia previa recomendada; EPP; apto médico.', 'Se practican maniobras de emergencia y estacionamiento seguro.', 'cualquiera'),
(10, 'Operador de Topador', 'Curso de operación de topadores para movimiento de suelos.', 50, 'Ejecutar empuje, nivelación y ripado con topador; gestión de taludes y comunicación con apoyo.', '20 h: dos jornadas de 10 h (teoría + campo).', 'Operadores de minería, movimiento de suelos y caminos.', 'Configuración de hoja | Técnicas de empuje y ripado | Trabajo en taludes | Mantenimiento y check-list.', 'Experiencia en maquinaria pesada deseable; EPP; apto médico.', 'Se enfatiza la coordinación con spotters y control de tránsito interno.', 'cualquiera');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `curso_modalidad`
--

CREATE TABLE `curso_modalidad` (
  `id_curso` int(11) NOT NULL,
  `id_modalidad` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `curso_modalidad`
--

INSERT INTO `curso_modalidad` (`id_curso`, `id_modalidad`) VALUES
(1, 1),
(2, 1),
(3, 1),
(4, 1),
(5, 1),
(6, 1),
(7, 1),
(8, 1),
(9, 1),
(10, 1);

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
(2, 'usuario', 'Solo vista ');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `usuarios`
--

CREATE TABLE `usuarios` (
  `id_usuario` int(11) NOT NULL,
  `email` varchar(50) NOT NULL,
  `clave` varchar(60) NOT NULL,
  `id_estado` int(11) NOT NULL,
  `id_permiso` int(11) NOT NULL,
  `token_verificacion` varchar(64) CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL,
  `token_expiracion` datetime DEFAULT NULL,
  `verificado` tinyint(1) UNSIGNED NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `usuarios`
--

INSERT INTO `usuarios` (`id_usuario`, `email`, `clave`, `id_estado`, `id_permiso`, `token_verificacion`, `token_expiracion`, `verificado`) VALUES
(2, 'administracion@institutodeoperadores.com', '$2y$10$BOYvdEyEt4tDuXinuc1b3ek57vlRgcYZXmdn4AJsLZ5KcYJjajSma', 2, 1, NULL, NULL, 0),
(3, 'pruebaa@mail.com', '$2y$10$kCMOMQEBqP7zTcKGjq0iNuZmf5R5h6qPmHqmeUEqssB8XqDPeg05u', 1, 2, NULL, NULL, 0),
(30, 'tomi22129@gmail.com', '$2y$10$YGEU0PawLwm8f9Dba27QsurWQ.5DKFkvftY7Uzst2/0Q3M1L.iv.m', 2, 2, NULL, NULL, 1),
(31, 'juanimelillo@gmail.com', '$2y$10$u.KWRVPpsOayMgXsA60o4OYJwWfauXP3RGN0OTj.kxC.S0Xz3O/qi', 2, 1, NULL, NULL, 1),
(32, 'juanimelillo38@gmail.com', '$2y$10$TN4b6Xv/H8GRwhYGvygn4esnDKDeLQxscN4AECBs/aD9sGcUx6zpG', 1, 2, NULL, NULL, 1),
(33, 'juanimelillo36@gmail.com', '$2y$10$vCaIv8Mg7FMGsHgJ6ii93.o/3KMuNNG43YpD3F5hmqqDViNNQz6s6', 2, 2, NULL, NULL, 1);

--
-- Índices para tablas volcadas
--

--
-- Indices de la tabla `banner`
--
ALTER TABLE `banner`
  ADD PRIMARY KEY (`id_banner`);

--
-- Indices de la tabla `checkout_inscripciones`
--
ALTER TABLE `checkout_inscripciones`
  ADD PRIMARY KEY (`id_inscripcion`),
  ADD KEY `idx_checkout_inscripciones_curso` (`id_curso`);

--
-- Indices de la tabla `checkout_mercadopago`
--
ALTER TABLE `checkout_mercadopago`
  ADD PRIMARY KEY (`id_mp`),
  ADD UNIQUE KEY `ux_checkout_mp_pago` (`id_pago`),
  ADD UNIQUE KEY `ux_checkout_mp_pref` (`preference_id`);

--
-- Indices de la tabla `checkout_pagos`
--
ALTER TABLE `checkout_pagos`
  ADD PRIMARY KEY (`id_pago`),
  ADD KEY `idx_checkout_pagos_inscripcion` (`id_inscripcion`);

--
-- Indices de la tabla `cursos`
--
ALTER TABLE `cursos`
  ADD PRIMARY KEY (`id_curso`);

--
-- Indices de la tabla `curso_modalidad`
--
ALTER TABLE `curso_modalidad`
  ADD PRIMARY KEY (`id_curso`,`id_modalidad`),
  ADD KEY `id_modalidad` (`id_modalidad`);

--
-- Indices de la tabla `curso_precio_hist`
--
ALTER TABLE `curso_precio_hist`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_precio_actual` (`id_curso`,`vigente_desde`,`vigente_hasta`);

--
-- Indices de la tabla `estado`
--
ALTER TABLE `estado`
  ADD PRIMARY KEY (`id_estado`);

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
-- Indices de la tabla `usuarios`
--
ALTER TABLE `usuarios`
  ADD PRIMARY KEY (`id_usuario`),
  ADD UNIQUE KEY `uq_usuarios_token_verificacion` (`token_verificacion`),
  ADD KEY `FK__USUARIO_ESTADO__END` (`id_estado`),
  ADD KEY `FK__USUARIO_PERMISO__END` (`id_permiso`),
  ADD KEY `idx_usuarios_token_expiracion` (`token_expiracion`);

--
-- AUTO_INCREMENT de las tablas volcadas
--

--
-- AUTO_INCREMENT de la tabla `banner`
--
ALTER TABLE `banner`
  MODIFY `id_banner` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=14;

--
-- AUTO_INCREMENT de la tabla `checkout_inscripciones`
--
ALTER TABLE `checkout_inscripciones`
  MODIFY `id_inscripcion` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT de la tabla `checkout_mercadopago`
--
ALTER TABLE `checkout_mercadopago`
  MODIFY `id_mp` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `checkout_pagos`
--
ALTER TABLE `checkout_pagos`
  MODIFY `id_pago` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT de la tabla `cursos`
--
ALTER TABLE `cursos`
  MODIFY `id_curso` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=14;

--
-- AUTO_INCREMENT de la tabla `curso_precio_hist`
--
ALTER TABLE `curso_precio_hist`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=24;

--
-- AUTO_INCREMENT de la tabla `estado`
--
ALTER TABLE `estado`
  MODIFY `id_estado` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT de la tabla `modalidades`
--
ALTER TABLE `modalidades`
  MODIFY `id_modalidad` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT de la tabla `permisos`
--
ALTER TABLE `permisos`
  MODIFY `id_permiso` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT de la tabla `usuarios`
--
ALTER TABLE `usuarios`
  MODIFY `id_usuario` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=34;

--
-- Restricciones para tablas volcadas
--

--
-- Filtros para la tabla `checkout_inscripciones`
--
ALTER TABLE `checkout_inscripciones`
  ADD CONSTRAINT `fk_checkout_inscripciones_curso` FOREIGN KEY (`id_curso`) REFERENCES `cursos` (`id_curso`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Filtros para la tabla `checkout_mercadopago`
--
ALTER TABLE `checkout_mercadopago`
  ADD CONSTRAINT `fk_checkout_mp_pago` FOREIGN KEY (`id_pago`) REFERENCES `checkout_pagos` (`id_pago`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Filtros para la tabla `checkout_pagos`
--
ALTER TABLE `checkout_pagos`
  ADD CONSTRAINT `fk_checkout_pagos_inscripcion` FOREIGN KEY (`id_inscripcion`) REFERENCES `checkout_inscripciones` (`id_inscripcion`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Filtros para la tabla `curso_modalidad`
--
ALTER TABLE `curso_modalidad`
  ADD CONSTRAINT `curso_modalidad_ibfk_1` FOREIGN KEY (`id_curso`) REFERENCES `cursos` (`id_curso`),
  ADD CONSTRAINT `curso_modalidad_ibfk_2` FOREIGN KEY (`id_modalidad`) REFERENCES `modalidades` (`id_modalidad`);

--
-- Filtros para la tabla `curso_precio_hist`
--
ALTER TABLE `curso_precio_hist`
  ADD CONSTRAINT `fk_curso_precio_hist` FOREIGN KEY (`id_curso`) REFERENCES `cursos` (`id_curso`) ON DELETE CASCADE;

--
-- Filtros para la tabla `usuarios`
--
ALTER TABLE `usuarios`
  ADD CONSTRAINT `FK__USUARIO_ESTADO__END` FOREIGN KEY (`id_estado`) REFERENCES `estado` (`id_estado`),
  ADD CONSTRAINT `FK__USUARIO_PERMISO__END` FOREIGN KEY (`id_permiso`) REFERENCES `permisos` (`id_permiso`);
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
