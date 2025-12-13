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

class SwastarkenclCustomersAgency extends ObjectModel
{
    public $state_id; // starken state id
    public $customer_id;
    public $agency_dls;
    public $payment_on_arrival;

    public static $definition = [
        'table' => 'swastarkencl_customers_agency',
        'primary' => 'id_swastarkencl_customers_agency',
        'fields' => [
            'state_id' => ['type' => self::TYPE_INT, 'validate' => 'isUnsignedId', 'required' => true],
            'customer_id' => ['type' => self::TYPE_INT, 'validate' => 'isUnsignedId', 'required' => true],
            'agency_dls' => ['type' => self::TYPE_INT, 'validate' => 'isUnsignedId', 'required' => true],
            'payment_on_arrival' => ['type' => self::TYPE_INT, 'validate' => 'isUnsignedId', 'required' => true],
        ],
    ];

    public static function alreadyExists($stateId, $customerId, $agencyDLS)
    {
        if ((int)$stateId <= 0 || (int) $customerId <= 0 || (int)$agencyDLS <= 0) {
            return false;
        }
        return (bool) Db::getInstance()->getValue('
            SELECT `id_swastarkencl_customers_agency`
            FROM `' . _DB_PREFIX_ . 'swastarkencl_customers_agency`
            WHERE `state_id` = ' . pSQL($stateId) . ' AND `customer_id` = ' . pSQL($customerId)
            . ' AND `agency_dls` = ' . pSQL($agencyDLS) . '
        ');
    }

    public static function deleteByCustomer($customerId)
    {
        if (empty($customerId)) {
            return false;
        }

        return (bool) Db::getInstance()->execute('
            DELETE FROM `' . _DB_PREFIX_ . 'swastarkencl_customers_agency`
            WHERE `customer_id` = ' . pSQL($customerId) . ' 
        ');
    }

    public static function getIdStateCustomerAndAgency($stateId, $customerId, $agencyDLS)
    {
        if (empty($stateId) || empty($customerId) || empty($agencyDLS)) {
            return 0;
        }

        return (int) Db::getInstance()->getValue('
            SELECT `id_swastarkencl_customers_agency`
            FROM `' . _DB_PREFIX_ . 'swastarkencl_customers_agency`
            WHERE `state_id` = ' . pSQL($stateId) . ' AND `customer_id` = ' . pSQL($customerId)
            . ' AND `agency_dls` = ' . pSQL($agencyDLS) . '
        ');
    }

    public static function getAgencyDLS($stateId, $customerId)
    {
        if (empty($stateId) || empty($customerId)) {
            return 0;
        }

        return (int) Db::getInstance()->getValue('
            SELECT `agency_dls`
            FROM `' . _DB_PREFIX_ . 'swastarkencl_customers_agency`
            WHERE `state_id` = ' . pSQL($stateId) . ' AND `customer_id` = ' . pSQL($customerId) . ' 
        ');
    }

    public static function isPaymentArrivalAllowed($starkenStateId, $customerId)
    {
        if (empty($starkenStateId) || empty($customerId)) {
            return false;
        }

        return (bool) Db::getInstance()->getValue('
            SELECT `payment_on_arrival`
            FROM `' . _DB_PREFIX_ . 'swastarkencl_customers_agency` 
            WHERE `state_id` = ' . pSQL((int)$starkenStateId) . ' AND `customer_id` = ' . pSQL((int)$customerId) . ' 
        ');
    }

    public static function getStateIdByCustomer($customerId)
    {
        if ((int) $customerId <= 0) {
            return null;
        }

        return Db::getInstance()->getValue('
            SELECT `state_id` FROM `' . _DB_PREFIX_ . 'swastarkencl_customers_agency`
            WHERE `customer_id` = ' . pSQL($customerId) . ' 
            ORDER BY id_swastarkencl_customers_agency DESC
        ');
    }
}
