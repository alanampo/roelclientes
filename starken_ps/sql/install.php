<?php
/**
 *  NOTICE OF LICENSE
 *
 *  This product is licensed for one customer to use on one installation (test stores and multishop included).
 *  Site developer has the right to modify this module to suit their needs, but can not redistribute the module
 *  in whole or in part. Any other use of this module constitues a violation of the user agreement.
 *
 *  DISCLAIMER
 *
 *  NO WARRANTIES OF DATA SAFETY OR MODULE SECURITY ARE EXPRESSED OR IMPLIED. USE THIS MODULE IN ACCORDANCE WITH
 *  YOUR MERCHANT AGREEMENT, KNOWING THAT VIOLATIONS OF PCI COMPLIANCY OR A DATA BREACH CAN COST THOUSANDS OF
 *  DOLLARS IN FINES AND DAMAGE A STORES REPUTATION. USE AT YOUR OWN RISK.
 *
 * @author    Software Agil Ltda
 * @copyright 2022
 * @license   See above
 */

if (!defined('_PS_VERSION_')) {
    exit;
}

$queries = [];

$queries [] = "
    CREATE TABLE IF NOT EXISTS `" . _DB_PREFIX_ . "swastarkencl_states` (
        `id_swastarkencl_states` INT NOT NULL AUTO_INCREMENT,
        `id_starken` INT NOT NULL,
        `commune_dls` INT NOT NULL,
        `city_dls` INT NOT NULL,
        `city_starken_id` INT NOT NULL,
        `commune_name` VARCHAR(50) NOT NULL,
        `city_name` VARCHAR(50) NOT NULL,

        PRIMARY KEY (`id_swastarkencl_states`)
    ) ENGINE = " . _MYSQL_ENGINE_ . " DEFAULT CHARSET = UTF8;
";

$queries [] = "
    CREATE TABLE IF NOT EXISTS `" . _DB_PREFIX_ . "swastarkencl_document_types` (
        `id_swastarkencl_document_types` INT NOT NULL AUTO_INCREMENT,
        `code_dls` INT NOT NULL DEFAULT 0,
        `name` VARCHAR(50),
        `description` VARCHAR(255),

        UNIQUE(`code_dls`, `name`),
        PRIMARY KEY (`id_swastarkencl_document_types`)
    ) ENGINE = " . _MYSQL_ENGINE_ . " DEFAULT CHARSET = UTF8;
";

$queries [] = "
    CREATE TABLE IF NOT EXISTS `" . _DB_PREFIX_ . "swastarkencl_delivery_types` (
        `id_swastarkencl_delivery_types` INT NOT NULL AUTO_INCREMENT,
        `code_dls` INT NOT NULL DEFAULT 0,
        `name` VARCHAR(50),
        `description` VARCHAR(255),

        UNIQUE(`code_dls`, `name`),
        PRIMARY KEY (`id_swastarkencl_delivery_types`)
    ) ENGINE = " . _MYSQL_ENGINE_ . " DEFAULT CHARSET = UTF8;
";

$queries [] = "
    CREATE TABLE IF NOT EXISTS `" . _DB_PREFIX_ . "swastarkencl_service_types` (
        `id_swastarkencl_service_types` INT NOT NULL AUTO_INCREMENT,
        `code_dls` INT NOT NULL DEFAULT 0,
        `name` VARCHAR(50),
        `description` VARCHAR(255),

        UNIQUE(`code_dls`, `name`),
        PRIMARY KEY (`id_swastarkencl_service_types`)
    ) ENGINE = " . _MYSQL_ENGINE_ . " DEFAULT CHARSET = UTF8;
";

$queries [] = "
    CREATE TABLE IF NOT EXISTS `" . _DB_PREFIX_ . "swastarkencl_payment_types` (
        `id_swastarkencl_payment_types` INT NOT NULL AUTO_INCREMENT,
        `code_dls` INT NOT NULL DEFAULT 0,
        `name` VARCHAR(50),
        `description` VARCHAR(255),

        UNIQUE(`code_dls`, `name`),
        PRIMARY KEY (`id_swastarkencl_payment_types`)
    ) ENGINE = " . _MYSQL_ENGINE_ . " DEFAULT CHARSET = UTF8;
";
$queries [] = "
    CREATE TABLE IF NOT EXISTS `" . _DB_PREFIX_ . "swastarkencl_carriers` (
        `id_swastarkencl_carriers` INT NOT NULL AUTO_INCREMENT,
        `id_carrier` INT NOT NULL,
        `delivery` VARCHAR(50),
        `service` VARCHAR(50),
        `payment_type` SMALLINT DEFAULT NULL,

        PRIMARY KEY (`id_swastarkencl_carriers`)
    ) ENGINE = " . _MYSQL_ENGINE_ . " DEFAULT CHARSET = UTF8;
";
$queries [] = "
    CREATE TABLE IF NOT EXISTS `" . _DB_PREFIX_ . "swastarkencl_emisiones` (
        `id_swastarkencl_emisiones` INT NOT NULL AUTO_INCREMENT,
        `id_order` INT NOT NULL,
        `id_emision` INT NOT NULL,

        -- From /emision/tipo-entrega. It's can contain only the DLS value
        `tipo_entrega` TEXT, -- json format

        -- From /emision/tipo-pago. It's can contain only the DLS value
        `tipo_pago` TEXT, -- json format

        -- From /emision/tipo-servicio. It's can contain only the DLS value
        `tipo_servicio` TEXT, -- json format

        `cuenta_corriente` VARCHAR(50),
        `centro_costo` VARCHAR(50),
        `valor` FLOAT,

        -- DLS value from /agency/agency
        `codigo_agencia_origen` INT NOT NULL,
        `codigo_agencia_destino` INT,

        `destinatario_rut` VARCHAR(50) NOT NULL,
        `destinatario_nombres` VARCHAR(50),
        `destinatario_paterno` VARCHAR(50),
        `destinatario_materno` VARCHAR(50),
        `destinatario_razon_social` VARCHAR(50),
        `destinatario_direccion` VARCHAR(50),
        `destinatario_numeracion` VARCHAR(50),
        `destinatario_departamento` VARCHAR(50),
        `destinatario_codigo_comuna` INT,
        `destinatario_telefono` VARCHAR(50) NOT NULL,
        `destinatario_email` VARCHAR(50) NOT NULL,
        `destinatario_contacto` VARCHAR(50) NOT NULL,
        
        `contenido` VARCHAR(50),
        `kilos_total` FLOAT DEFAULT 0.0,
        `valor_declarado` FLOAT DEFAULT 0.0,
        `orden_flete` INT,
        `estado` VARCHAR(50),
        `impresiones` INT,
        `encargos` TEXT, -- json format
        `user` TEXT, -- json format
        `master` TEXT, -- json format
        `master_id` INT,
        `user_id` INT,
        `etiqueta` TEXT,

        `status` VARCHAR(50), 
        `created_at` VARCHAR(50), 
        `direccion_normalizada` TEXT,
        `latitud` VARCHAR(50),
        `longitud` VARCHAR(50),
        `retiro_asociado` VARCHAR(50),
        `queue_id` VARCHAR(50),
        `observacion` VARCHAR(50),
        `retry` VARCHAR(50),
        `updated_at` VARCHAR(50),

        PRIMARY KEY (`id_swastarkencl_emisiones`)
    ) ENGINE = " . _MYSQL_ENGINE_ . " DEFAULT CHARSET = UTF8;
";

$queries [] = "
    CREATE TABLE IF NOT EXISTS `" . _DB_PREFIX_ . "swastarkencl_customers_agency` (
        `id_swastarkencl_customers_agency` INT NOT NULL AUTO_INCREMENT,
        `customer_id` INT NOT NULL,
        `state_id` INT NOT NULL,
        `agency_dls` INT NOT NULL,
        `payment_on_arrival` SMALLINT DEFAULT 1,

        PRIMARY KEY (`id_swastarkencl_customers_agency`)
    ) ENGINE = " . _MYSQL_ENGINE_ . " DEFAULT CHARSET = UTF8;
";

if (count($queries) > 0) {
    foreach ($queries as $query) {
        try {
            Db::getInstance()->execute($query);
        } catch (PrestaShopDatabaseException $e) {
            if (Configuration::get('SWASTARKENCL_ENABLE_LOGS')) {
                PrestaShopLogger::addLog(
                    $e->getMessage(),
                    3,
                    null,
                    'Swastarkencl',
                    null,
                    true,
                    null
                );
            }
        }
    }
}
