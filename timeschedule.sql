-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Servidor: 127.0.0.1
-- Tiempo de generación: 05-11-2026 a las 18:07:40
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
-- Base de datos: `timeschedule`
--

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `chatbot_logs`
--

CREATE TABLE `chatbot_logs` (
  `id` int(11) NOT NULL,
  `pregunta` text NOT NULL,
  `respuesta` text NOT NULL,
  `fecha` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Volcado de datos para la tabla `chatbot_logs`
--

INSERT INTO `chatbot_logs` (`id`, `pregunta`, `respuesta`, `fecha`) VALUES
(1, 'en que me puedes ayudar/orientar?', 'Lo siento, no entendí tu pregunta. Intenta usar palabras clave como \'horario\', \'docente\', \'recreo\' o \'estado\'.', '2026-10-22 17:05:41'),
(2, 'estado', 'Las grillas \"Borrador\" solo las ven los administradores. Cuando la pasas a \"Confirmado\", se publica para que todos los alumnos la vean.', '2026-10-22 17:05:54'),
(3, 'en que me puedes ayudar o orientar?', 'Lo siento, no entendí tu pregunta. Intenta usar palabras clave como \'horario\', \'docente\', \'recreo\' o \'estado\'.', '2026-10-22 17:08:50'),
(6, 'ayuda', 'Lo siento, no he podido comprender del todo tu consulta. Intenta redactarla usando palabras clave como **\'horario\'**, **\'docente\'**, **\'cupo\'** o escribe **\'manual\'** para ver la guía rápida del sistema.', '2026-11-05 11:50:50'),
(7, 'manual', '📖 **Manual Rápido de TimeSchedule:**\n1. **Acceso:** Ingresa al login con tu cédula sin puntos ni guiones (ej: 11111111).\n2. **Grillas:** Ve a \'Grilla Admin\', selecciona el grupo y turno. Asigna materias y docentes módulo a módulo.\n3. **Cupos:** Usa el control superior para limitar las horas máximas por materia.\n4. **Estados:** Guarda como \'Borrador\' (uso interno) o \'Confirmado\' (visible para alumnos en \'Ver Grillas\').', '2026-11-05 11:51:04'),
(8, 'ayuda', '**Manual Rápido de TimeSchedule:**\n1. **Acceso:** Ingresa al login con tu cédula sin puntos ni guiones (ej: 11111111).\n2. **Grillas:** Ve a \'Grilla Admin\', selecciona el grupo y turno. Asigna materias y docentes módulo a módulo.\n3. **Cupos:** Usa el control superior para limitar las horas máximas por materia.\n4. **Estados:** Guarda como \'Borrador\' (uso interno) o \'Confirmado\' (visible para alumnos en \'Ver Grillas\').', '2026-11-05 11:52:23');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `docentes`
--

CREATE TABLE `docentes` (
  `id` int(11) NOT NULL,
  `ci` varchar(12) NOT NULL,
  `nombre` varchar(50) NOT NULL,
  `apellido` varchar(50) NOT NULL,
  `correo` varchar(100) DEFAULT NULL,
  `telefono` varchar(50) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Volcado de datos para la tabla `docentes`
--

INSERT INTO `docentes` (`id`, `ci`, `nombre`, `apellido`, `correo`, `telefono`) VALUES
(21, '48151164', 'Bruno', 'Amorin', 'ggg@gmail.com', '099 124 531'),
(23, '57845118', 'Tiago', 'dos Santos', 'tiagocamilodossantos27@gmail.com', '099 115 260'),
(24, '99999999', 'Leandro', 'Pereira', 'ggg1@gmail.com', '099 124 531');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `docentes_horarios_externos`
--

CREATE TABLE `docentes_horarios_externos` (
  `id` int(11) NOT NULL,
  `docente_id` int(11) NOT NULL,
  `modulo_id` int(11) NOT NULL,
  `dia_semana` varchar(20) NOT NULL,
  `nombre_institucion` varchar(150) DEFAULT 'Institución Externa'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `docente_materias`
--

CREATE TABLE `docente_materias` (
  `id` int(11) NOT NULL,
  `docente_id` int(11) NOT NULL,
  `materia_id` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Volcado de datos para la tabla `docente_materias`
--

INSERT INTO `docente_materias` (`id`, `docente_id`, `materia_id`) VALUES
(3, 21, 4),
(1, 23, 3),
(2, 24, 5);

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `grillas`
--

CREATE TABLE `grillas` (
  `id` int(11) NOT NULL,
  `grupo_id` int(11) NOT NULL,
  `anio_lectivo` int(11) NOT NULL DEFAULT 2026,
  `estado` enum('Borrador','Confirmado') NOT NULL DEFAULT 'Borrador',
  `creado_por` int(11) NOT NULL,
  `confirmado_por` int(11) DEFAULT NULL,
  `fecha_confirmacion` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Volcado de datos para la tabla `grillas`
--

INSERT INTO `grillas` (`id`, `grupo_id`, `anio_lectivo`, `estado`, `creado_por`, `confirmado_por`, `fecha_confirmacion`) VALUES
(20, 20, 2026, 'Confirmado', 1, 1, '2026-10-24 13:09:22'),
(22, 21, 2026, 'Confirmado', 1, 1, '2026-11-05 13:53:01'),
(23, 23, 2026, 'Borrador', 1, 1, '2026-10-23 23:16:02');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `grupos`
--

CREATE TABLE `grupos` (
  `id` int(11) NOT NULL,
  `nombre_grupo` varchar(50) NOT NULL,
  `turno` varchar(50) NOT NULL,
  `salon_id` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Volcado de datos para la tabla `grupos`
--

INSERT INTO `grupos` (`id`, `nombre_grupo`, `turno`, `salon_id`) VALUES
(20, '3ro MF BT', 'Turno 1', NULL),
(21, '2do MF AD', 'Turno 3', NULL),
(23, '1ro MA', 'Turno 1', NULL);

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `horarios_clase`
--

CREATE TABLE `horarios_clase` (
  `id` int(11) NOT NULL,
  `grilla_id` int(11) NOT NULL,
  `modulo_id` int(11) NOT NULL,
  `dia_semana` enum('Lunes','Martes','Miércoles','Jueves','Viernes') NOT NULL,
  `materia_id` int(11) NOT NULL,
  `docente_id` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Volcado de datos para la tabla `horarios_clase`
--

INSERT INTO `horarios_clase` (`id`, `grilla_id`, `modulo_id`, `dia_semana`, `materia_id`, `docente_id`) VALUES
(5, 20, 1, 'Lunes', 4, 21),
(22, 22, 9, 'Lunes', 5, 24),
(23, 22, 9, 'Martes', 4, 21),
(24, 22, 11, 'Lunes', 5, 24),
(25, 22, 11, 'Martes', 4, 21);

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `materias`
--

CREATE TABLE `materias` (
  `id` int(11) NOT NULL,
  `nombre` varchar(100) NOT NULL,
  `tipo` varchar(100) DEFAULT 'Equivalente / Común'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Volcado de datos para la tabla `materias`
--

INSERT INTO `materias` (`id`, `nombre`, `tipo`) VALUES
(3, 'Programación', 'Equivalente / Común'),
(4, 'Mecanica', 'Equivalente / Común'),
(5, 'UTU Lab', 'Técnico Profesional / Específica');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `modulos_horarios`
--

CREATE TABLE `modulos_horarios` (
  `id` int(11) NOT NULL,
  `turno` varchar(50) NOT NULL,
  `numero_modulo` int(11) NOT NULL,
  `hora_inicio` time NOT NULL,
  `hora_fin` time NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Volcado de datos para la tabla `modulos_horarios`
--

INSERT INTO `modulos_horarios` (`id`, `turno`, `numero_modulo`, `hora_inicio`, `hora_fin`) VALUES
(1, 'Turno 1', 1, '07:30:00', '08:15:00'),
(2, 'Turno 1', 2, '08:15:00', '09:00:00'),
(3, 'Turno 1', 3, '09:05:00', '09:50:00'),
(4, 'Turno 1', 4, '09:50:00', '10:35:00'),
(5, 'Turno 1', 5, '10:40:00', '11:25:00'),
(6, 'Turno 1', 6, '11:25:00', '12:10:00'),
(7, 'Turno 1', 7, '12:15:00', '13:00:00'),
(8, 'Turno 1', 8, '13:05:00', '13:50:00'),
(9, 'Turno 3', 1, '13:05:00', '13:50:00'),
(10, 'Turno 3', 2, '13:50:00', '14:35:00'),
(11, 'Turno 3', 3, '14:40:00', '15:25:00'),
(12, 'Turno 3', 4, '15:25:00', '16:10:00'),
(13, 'Turno 3', 5, '16:15:00', '17:00:00'),
(14, 'Turno 3', 6, '17:00:00', '17:45:00'),
(15, 'Turno 3', 7, '17:50:00', '18:35:00'),
(16, 'Turno 3', 8, '18:35:00', '19:20:00'),
(17, 'Turno 5', 1, '17:50:00', '18:35:00'),
(18, 'Turno 5', 2, '18:35:00', '19:20:00'),
(19, 'Turno 5', 3, '19:20:00', '20:00:00'),
(20, 'Turno 5', 4, '20:05:00', '20:45:00'),
(21, 'Turno 5', 5, '20:45:00', '21:25:00'),
(22, 'Turno 5', 6, '21:30:00', '22:10:00'),
(23, 'Turno 5', 7, '22:15:00', '22:55:00'),
(24, 'Turno 5', 8, '22:55:00', '23:35:00');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `usuarios`
--

CREATE TABLE `usuarios` (
  `id` int(11) NOT NULL,
  `ci` varchar(12) NOT NULL,
  `nombre` varchar(50) NOT NULL,
  `apellido` varchar(50) NOT NULL,
  `email` varchar(100) DEFAULT NULL,
  `password_hash` varchar(255) NOT NULL,
  `rol` enum('administrativo','direccion','adscripcion') NOT NULL DEFAULT 'adscripcion',
  `creado_en` timestamp NOT NULL DEFAULT current_timestamp(),
  `session_id` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Volcado de datos para la tabla `usuarios`
--

INSERT INTO `usuarios` (`id`, `ci`, `nombre`, `apellido`, `email`, `password_hash`, `rol`, `creado_en`, `session_id`) VALUES
(1, '11111111', 'Maria ', 'Galarza', 'admin@utu.edu.uy', '$2y$10$/R2r1zSYN/CFArP8Km6ulOzZgcDFu6K3EjBWtrJoFghEczQhomJ5y', 'administrativo', '2026-08-28 17:29:05', 'r6n03p1ujshac90ruqs2slh7he'),
(2, '22222222', 'Administrativo', '2', 'admin2@utu.edu.uy', '$2y$10$U04y9OhDyAqrlwRBHO/KKuHNWOXMtjUzNTWdJ6A/SO0b/EX7vs7k2', 'administrativo', '2026-10-28 01:05:19', 'r6n03p1ujshac90ruqs2slh7he');

--
-- Índices para tablas volcadas
--

--
-- Indices de la tabla `chatbot_logs`
--
ALTER TABLE `chatbot_logs`
  ADD PRIMARY KEY (`id`);

--
-- Indices de la tabla `docentes`
--
ALTER TABLE `docentes`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `ci` (`ci`);

--
-- Indices de la tabla `docentes_horarios_externos`
--
ALTER TABLE `docentes_horarios_externos`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unq_doc_mod_dia` (`docente_id`,`modulo_id`,`dia_semana`),
  ADD KEY `modulo_id` (`modulo_id`);

--
-- Indices de la tabla `docente_materias`
--
ALTER TABLE `docente_materias`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `docente_materia_unique` (`docente_id`,`materia_id`);

--
-- Indices de la tabla `grillas`
--
ALTER TABLE `grillas`
  ADD PRIMARY KEY (`id`),
  ADD KEY `grupo_id` (`grupo_id`),
  ADD KEY `creado_por` (`creado_por`),
  ADD KEY `confirmado_por` (`confirmado_por`);

--
-- Indices de la tabla `grupos`
--
ALTER TABLE `grupos`
  ADD PRIMARY KEY (`id`),
  ADD KEY `salon_id` (`salon_id`);

--
-- Indices de la tabla `horarios_clase`
--
ALTER TABLE `horarios_clase`
  ADD PRIMARY KEY (`id`),
  ADD KEY `grilla_id` (`grilla_id`),
  ADD KEY `modulo_id` (`modulo_id`),
  ADD KEY `materia_id` (`materia_id`),
  ADD KEY `docente_id` (`docente_id`);

--
-- Indices de la tabla `materias`
--
ALTER TABLE `materias`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `nombre` (`nombre`);

--
-- Indices de la tabla `modulos_horarios`
--
ALTER TABLE `modulos_horarios`
  ADD PRIMARY KEY (`id`);

--
-- Indices de la tabla `usuarios`
--
ALTER TABLE `usuarios`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `ci` (`ci`),
  ADD UNIQUE KEY `email` (`email`);

--
-- AUTO_INCREMENT de las tablas volcadas
--

--
-- AUTO_INCREMENT de la tabla `chatbot_logs`
--
ALTER TABLE `chatbot_logs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT de la tabla `docentes`
--
ALTER TABLE `docentes`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=25;

--
-- AUTO_INCREMENT de la tabla `docentes_horarios_externos`
--
ALTER TABLE `docentes_horarios_externos`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT de la tabla `docente_materias`
--
ALTER TABLE `docente_materias`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT de la tabla `grillas`
--
ALTER TABLE `grillas`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=24;

--
-- AUTO_INCREMENT de la tabla `grupos`
--
ALTER TABLE `grupos`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=24;

--
-- AUTO_INCREMENT de la tabla `horarios_clase`
--
ALTER TABLE `horarios_clase`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=26;

--
-- AUTO_INCREMENT de la tabla `materias`
--
ALTER TABLE `materias`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT de la tabla `modulos_horarios`
--
ALTER TABLE `modulos_horarios`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=25;

--
-- AUTO_INCREMENT de la tabla `usuarios`
--
ALTER TABLE `usuarios`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- Restricciones para tablas volcadas
--

--
-- Filtros para la tabla `docentes_horarios_externos`
--
ALTER TABLE `docentes_horarios_externos`
  ADD CONSTRAINT `docentes_horarios_externos_ibfk_1` FOREIGN KEY (`docente_id`) REFERENCES `docentes` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `docentes_horarios_externos_ibfk_2` FOREIGN KEY (`modulo_id`) REFERENCES `modulos_horarios` (`id`) ON DELETE CASCADE;

--
-- Filtros para la tabla `grillas`
--
ALTER TABLE `grillas`
  ADD CONSTRAINT `grillas_ibfk_1` FOREIGN KEY (`grupo_id`) REFERENCES `grupos` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `grillas_ibfk_2` FOREIGN KEY (`creado_por`) REFERENCES `usuarios` (`id`),
  ADD CONSTRAINT `grillas_ibfk_3` FOREIGN KEY (`confirmado_por`) REFERENCES `usuarios` (`id`);

--
-- Filtros para la tabla `grupos`
--
ALTER TABLE `grupos`
  ADD CONSTRAINT `grupos_ibfk_1` FOREIGN KEY (`salon_id`) REFERENCES `salones` (`id`) ON DELETE SET NULL;

--
-- Filtros para la tabla `horarios_clase`
--
ALTER TABLE `horarios_clase`
  ADD CONSTRAINT `horarios_clase_ibfk_1` FOREIGN KEY (`grilla_id`) REFERENCES `grillas` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `horarios_clase_ibfk_2` FOREIGN KEY (`modulo_id`) REFERENCES `modulos_horarios` (`id`),
  ADD CONSTRAINT `horarios_clase_ibfk_3` FOREIGN KEY (`materia_id`) REFERENCES `materias` (`id`),
  ADD CONSTRAINT `horarios_clase_ibfk_4` FOREIGN KEY (`docente_id`) REFERENCES `docentes` (`id`);
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
