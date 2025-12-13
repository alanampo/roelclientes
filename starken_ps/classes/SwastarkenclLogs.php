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

class SwastarkenclLogs extends PrestaShopLogger
{
    public static function getLogs()
    {
        return Db::getInstance()->executeS('
            SELECT DISTINCT message, date_add
            FROM `' . _DB_PREFIX_ . 'log` 
            WHERE error_code IN (70070, 70071, 70072, 70073, 70074, 70075, 70076, 70077) 
            ORDER BY id_log DESC
            LIMIT 30
        ');
    }
}
