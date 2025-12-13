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
$queries[] = 'DROP TABLE `'._DB_PREFIX_.'swastarkencl_states`;';
$queries[] = 'DROP TABLE `'._DB_PREFIX_.'swastarkencl_document_types`;';
$queries[] = 'DROP TABLE `'._DB_PREFIX_.'swastarkencl_delivery_types`;';
$queries[] = 'DROP TABLE `'._DB_PREFIX_.'swastarkencl_service_types`;';
$queries[] = 'DROP TABLE `'._DB_PREFIX_.'swastarkencl_payment_types`;';
$queries[] = 'DROP TABLE `'._DB_PREFIX_.'swastarkencl_carriers`;';
$queries[] = 'DROP TABLE `'._DB_PREFIX_.'swastarkencl_emisiones`;';
$queries[] = 'DROP TABLE `'._DB_PREFIX_.'swastarkencl_customers_agency`;';

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
