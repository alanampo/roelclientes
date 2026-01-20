-- phpMyAdmin SQL Dump
-- version 5.2.2
-- https://www.phpmyadmin.net/
--
-- Servidor: localhost:3306
-- Tiempo de generación: 07-01-2026 a las 16:49:47
-- Versión del servidor: 8.0.44
-- Versión de PHP: 8.4.16

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

CREATE DATABASE IF NOT EXISTS `roeluser1_carrito_mayorista` CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci;
USE `roeluser1_carrito_mayorista`;



--
-- Base de datos: `roeluser1_carrito_mayorista`
--

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `backoffice_admins`
--

CREATE TABLE `backoffice_admins` (
  `id` int NOT NULL,
  `email` varchar(190) NOT NULL,
  `pass_hash` varchar(255) NOT NULL,
  `name` varchar(120) NOT NULL DEFAULT 'Administrador',
  `role` varchar(50) NOT NULL DEFAULT 'admin',
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `last_login_at` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Volcado de datos para la tabla `backoffice_admins`
--

INSERT INTO `backoffice_admins` (`id`, `email`, `pass_hash`, `name`, `role`, `is_active`, `last_login_at`, `created_at`) VALUES
(1, 'admin@roelplant.cl', '$2y$10$CsfCagxSTJWupvEGFmM5vOQJ95DKgxjwWVXS3pchicSuKS/sW36Qu', 'Admin Roelplant', 'admin', 1, '2026-01-07 14:36:21', '2026-01-06 11:44:45');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `backoffice_audit`
--

CREATE TABLE `backoffice_audit` (
  `id` bigint NOT NULL,
  `admin_id` int DEFAULT NULL,
  `action` varchar(60) NOT NULL,
  `meta` json DEFAULT NULL,
  `ip` varchar(64) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Volcado de datos para la tabla `backoffice_audit`
--


-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `bo_admin_users`
--

CREATE TABLE `bo_admin_users` (
  `id` int NOT NULL,
  `username` varchar(64) NOT NULL,
  `password_hash` varchar(255) NOT NULL,
  `name` varchar(120) DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `last_login_at` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Volcado de datos para la tabla `bo_admin_users`
--

INSERT INTO `bo_admin_users` (`id`, `username`, `password_hash`, `name`, `is_active`, `last_login_at`, `created_at`) VALUES
(1, 'admin', '$2y$10$vn/J3odqdlKz4DaF3fmuz.7XgvLLBWxmjlAX7/0hzScafSgnfzl4W', 'Administrador', 1, NULL, '2026-01-06 11:10:02');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `bo_audit`
--

CREATE TABLE `bo_audit` (
  `id` bigint NOT NULL,
  `admin_id` int DEFAULT NULL,
  `action` varchar(64) NOT NULL,
  `entity` varchar(64) DEFAULT NULL,
  `entity_id` varchar(64) DEFAULT NULL,
  `meta` text,
  `ip` varchar(64) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `carts_may`
--

CREATE TABLE `carts_may` (
  `id` bigint UNSIGNED NOT NULL,
  `customer_id` bigint UNSIGNED NOT NULL,
  `status` enum('open','converted','abandoned') NOT NULL DEFAULT 'open',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Volcado de datos para la tabla `carts_may`
--


-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `cart_items_may`
--

CREATE TABLE `cart_items_may` (
  `id` bigint UNSIGNED NOT NULL,
  `cart_id` bigint UNSIGNED NOT NULL,
  `id_variedad` int NOT NULL,
  `referencia` varchar(32) NOT NULL,
  `nombre` varchar(200) NOT NULL,
  `imagen_url` text,
  `unit_price_clp` int UNSIGNED NOT NULL,
  `qty` int UNSIGNED NOT NULL DEFAULT '1',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Volcado de datos para la tabla `cart_items_may`
--


-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `customers`
--

CREATE TABLE `customers` (
  `id` bigint UNSIGNED NOT NULL,
  `rut` varchar(12) NOT NULL,
  `rut_clean` varchar(10) NOT NULL,
  `nombre` varchar(120) NOT NULL,
  `telefono` varchar(30) NOT NULL,
  `region` varchar(80) NOT NULL,
  `comuna` varchar(80) NOT NULL,
  `email` varchar(190) DEFAULT NULL,
  `password_hash` varchar(255) NOT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `last_login_at` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Volcado de datos para la tabla `customers`
--


-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `orders_may`
--

CREATE TABLE `orders_may` (
  `id` bigint UNSIGNED NOT NULL,
  `order_code` varchar(32) COLLATE utf8mb4_unicode_ci NOT NULL,
  `customer_id` bigint UNSIGNED NOT NULL,
  `customer_rut` varchar(16) COLLATE utf8mb4_unicode_ci NOT NULL,
  `customer_nombre` varchar(120) COLLATE utf8mb4_unicode_ci NOT NULL,
  `customer_telefono` varchar(32) COLLATE utf8mb4_unicode_ci NOT NULL,
  `customer_region` varchar(64) COLLATE utf8mb4_unicode_ci NOT NULL,
  `customer_comuna` varchar(64) COLLATE utf8mb4_unicode_ci NOT NULL,
  `customer_email` varchar(190) COLLATE utf8mb4_unicode_ci NOT NULL,
  `currency` char(3) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'CLP',
  `subtotal_clp` int UNSIGNED NOT NULL DEFAULT '0',
  `shipping_code` varchar(32) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'retiro',
  `shipping_label` varchar(80) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'Retiro en vivero',
  `shipping_cost_clp` int UNSIGNED NOT NULL DEFAULT '0',
  `total_clp` int UNSIGNED NOT NULL DEFAULT '0',
  `notes` text COLLATE utf8mb4_unicode_ci,
  `status` varchar(24) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'new',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Volcado de datos para la tabla `orders_may`
--


-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `orders_legacy`
--

CREATE TABLE `orders_legacy` (
  `id` int UNSIGNED NOT NULL,
  `user_id` int UNSIGNED NOT NULL,
  `status` varchar(30) NOT NULL DEFAULT 'created',
  `shipping_method` varchar(30) NOT NULL DEFAULT 'manual',
  `shipping_label` varchar(120) NOT NULL DEFAULT '',
  `shipping_amount` int NOT NULL DEFAULT '0',
  `subtotal_amount` int NOT NULL DEFAULT '0',
  `total_amount` int NOT NULL DEFAULT '0',
  `notes` text,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `order_items_may`
--

CREATE TABLE `order_items_may` (
  `id` bigint UNSIGNED NOT NULL,
  `order_id` bigint UNSIGNED NOT NULL,
  `id_variedad` bigint UNSIGNED DEFAULT NULL,
  `referencia` varchar(64) COLLATE utf8mb4_unicode_ci NOT NULL,
  `nombre` varchar(190) COLLATE utf8mb4_unicode_ci NOT NULL,
  `imagen_url` varchar(512) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `unit_price_clp` int UNSIGNED NOT NULL,
  `qty` int UNSIGNED NOT NULL,
  `line_total_clp` int UNSIGNED NOT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Volcado de datos para la tabla `order_items_may`
--


-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `order_items_legacy`
--

CREATE TABLE `order_items_legacy` (
  `id` int UNSIGNED NOT NULL,
  `order_id` int UNSIGNED NOT NULL,
  `product_ref` varchar(60) NOT NULL,
  `product_name` varchar(255) NOT NULL,
  `unit_price` int NOT NULL DEFAULT '0',
  `qty` int NOT NULL DEFAULT '1',
  `line_total` int NOT NULL DEFAULT '0',
  `image_url` varchar(500) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `production_requests`
--

CREATE TABLE `production_requests` (
  `id` int NOT NULL,
  `request_code` varchar(32) NOT NULL,
  `customer_id` int NOT NULL,
  `status` varchar(20) NOT NULL DEFAULT 'new',
  `total_units` int NOT NULL DEFAULT '0',
  `total_amount_clp` int NOT NULL DEFAULT '0',
  `notes` text,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Volcado de datos para la tabla `production_requests`
--


-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `production_request_items`
--

CREATE TABLE `production_request_items` (
  `id` int NOT NULL,
  `request_id` int NOT NULL,
  `product_id` varchar(32) NOT NULL,
  `product_name` varchar(255) NOT NULL,
  `qty` int NOT NULL,
  `unit_price_clp` int NOT NULL DEFAULT '0',
  `line_total_clp` int NOT NULL DEFAULT '0',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Volcado de datos para la tabla `production_request_items`
--


--
-- Índices para tablas volcadas
--

--
-- Indices de la tabla `backoffice_admins`
--
ALTER TABLE `backoffice_admins`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `email` (`email`);

--
-- Indices de la tabla `backoffice_audit`
--
ALTER TABLE `backoffice_audit`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_admin` (`admin_id`),
  ADD KEY `idx_action` (`action`);

--
-- Indices de la tabla `bo_admin_users`
--
ALTER TABLE `bo_admin_users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `username` (`username`);

--
-- Indices de la tabla `bo_audit`
--
ALTER TABLE `bo_audit`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_admin` (`admin_id`),
  ADD KEY `idx_action` (`action`),
  ADD KEY `idx_entity` (`entity`);

--
-- Indices de la tabla `carts_may`
--
ALTER TABLE `carts_may`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uk_carts_customer_open` (`customer_id`,`status`),
  ADD KEY `idx_carts_customer_id` (`customer_id`);

--
-- Indices de la tabla `cart_items_may`
--
ALTER TABLE `cart_items_may`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uk_cart_items_cart_variedad` (`cart_id`,`id_variedad`),
  ADD KEY `idx_cart_items_cart_id` (`cart_id`);

--
-- Indices de la tabla `customers`
--
ALTER TABLE `customers`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uk_customers_rut_clean` (`rut_clean`),
  ADD UNIQUE KEY `uk_customers_email` (`email`),
  ADD KEY `idx_customers_created_at` (`created_at`);

--
-- Indices de la tabla `orders_may`
--
ALTER TABLE `orders_may`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_orders_order_code` (`order_code`),
  ADD KEY `idx_orders_customer` (`customer_id`),
  ADD KEY `idx_orders_created` (`created_at`);

--
-- Indices de la tabla `orders_legacy`
--
ALTER TABLE `orders_legacy`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_orders_user` (`user_id`),
  ADD KEY `idx_orders_created` (`created_at`);

--
-- Indices de la tabla `order_items_may`
--
ALTER TABLE `order_items_may`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_order_items_order` (`order_id`);

--
-- Indices de la tabla `order_items_legacy`
--
ALTER TABLE `order_items_legacy`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_items_order` (`order_id`);

--
-- Indices de la tabla `production_requests`
--
ALTER TABLE `production_requests`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `request_code` (`request_code`),
  ADD KEY `idx_customer` (`customer_id`),
  ADD KEY `idx_status` (`status`),
  ADD KEY `idx_created` (`created_at`);

--
-- Indices de la tabla `production_request_items`
--
ALTER TABLE `production_request_items`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_req` (`request_id`),
  ADD KEY `idx_prod` (`product_id`);

--
-- AUTO_INCREMENT de las tablas volcadas
--

--
-- AUTO_INCREMENT de la tabla `backoffice_admins`
--
ALTER TABLE `backoffice_admins`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT de la tabla `backoffice_audit`
--
ALTER TABLE `backoffice_audit`
  MODIFY `id` bigint NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=19;

--
-- AUTO_INCREMENT de la tabla `bo_admin_users`
--
ALTER TABLE `bo_admin_users`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT de la tabla `bo_audit`
--
ALTER TABLE `bo_audit`
  MODIFY `id` bigint NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `carts_may`
--
ALTER TABLE `carts_may`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=40;

--
-- AUTO_INCREMENT de la tabla `cart_items_may`
--
ALTER TABLE `cart_items_may`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=111;

--
-- AUTO_INCREMENT de la tabla `customers`
--
ALTER TABLE `customers`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=22;

--
-- AUTO_INCREMENT de la tabla `orders_may`
--
ALTER TABLE `orders_may`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=20;

--
-- AUTO_INCREMENT de la tabla `orders_legacy`
--
ALTER TABLE `orders_legacy`
  MODIFY `id` int UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `order_items_may`
--
ALTER TABLE `order_items_may`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=44;

--
-- AUTO_INCREMENT de la tabla `order_items_legacy`
--
ALTER TABLE `order_items_legacy`
  MODIFY `id` int UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `production_requests`
--
ALTER TABLE `production_requests`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=17;

--
-- AUTO_INCREMENT de la tabla `production_request_items`
--
ALTER TABLE `production_request_items`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=59;

--
-- Restricciones para tablas volcadas
--

--
-- Filtros para la tabla `backoffice_audit`
--
ALTER TABLE `backoffice_audit`
  ADD CONSTRAINT `fk_audit_admin` FOREIGN KEY (`admin_id`) REFERENCES `backoffice_admins` (`id`) ON DELETE SET NULL;

--
-- Filtros para la tabla `carts_may`
--
ALTER TABLE `carts_may`
  ADD CONSTRAINT `fk_carts_customer` FOREIGN KEY (`customer_id`) REFERENCES `customers` (`id`) ON DELETE CASCADE;

--
-- Filtros para la tabla `cart_items_may`
--
ALTER TABLE `cart_items_may`
  ADD CONSTRAINT `fk_cart_items_cart` FOREIGN KEY (`cart_id`) REFERENCES `carts_may` (`id`) ON DELETE CASCADE;

--
-- Filtros para la tabla `order_items_may`
--
ALTER TABLE `order_items_may`
  ADD CONSTRAINT `fk_order_items_order` FOREIGN KEY (`order_id`) REFERENCES `orders_may` (`id`) ON DELETE CASCADE;

--
-- Filtros para la tabla `production_request_items`
--
ALTER TABLE `production_request_items`
  ADD CONSTRAINT `fk_pri_req` FOREIGN KEY (`request_id`) REFERENCES `production_requests` (`id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;