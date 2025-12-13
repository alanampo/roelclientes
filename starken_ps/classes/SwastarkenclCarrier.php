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

if (!class_exists('SwastarkenclCarrier')) {
    class SwastarkenclCarrier extends ObjectModel
    {
        public $id_carrier;
        public $delivery;
        public $service;
        public $payment_type;
        
        public static $definition = [
            'table' => 'swastarkencl_carriers',
            'primary' => 'id_swastarkencl_carriers',
            'fields' => [
                'id_carrier' => ['type' => self::TYPE_INT, 'validate' => 'isUnsignedId', 'required' => true],
                'delivery' => ['type' => self::TYPE_STRING, 'required' => true],
                'service' => ['type' => self::TYPE_STRING, 'required' => true],
                'payment_type' => ['type' => self::TYPE_INT, 'required' => false],
            ],
        ];

        public static function updateCarrierByPreviousOne($previousCarrierId, $newCarrierId)
        {
            return Db::getInstance()->execute('
                UPDATE `' . _DB_PREFIX_ . 'swastarkencl_carriers` 
                SET id_carrier = '.pSQL($newCarrierId).' 
                WHERE id_carrier = '.pSQL($previousCarrierId).' 
            ');
        }

        public static function getIdByCarrier($carrierId)
        {
            if (empty($carrierId)) {
                return 0;
            }
            $cacheId = 'SwastarkenclPaymentType::getIdByCarrier' . pSQL($carrierId);
            if (!Cache::isStored($cacheId)) {
                $result = (int) Db::getInstance()->getValue('
                    SELECT `id_swastarkencl_carriers`
                    FROM `' . _DB_PREFIX_ . 'swastarkencl_carriers`
                    WHERE `id_carrier` = ' . pSQL($carrierId) . '
                ');
                Cache::store($cacheId, $result);

                return $result;
            }

            return Cache::retrieve($cacheId);
        }

        public static function all()
        {
            return Db::getInstance()->executeS('
                SELECT *
                FROM `' . _DB_PREFIX_ . 'swastarkencl_carriers` 
                ORDER BY id_swastarkencl_carriers DESC
                LIMIT 4
            ');
        }

        public static function getPaymentTypeByCarrierId($id)
        {
            return Db::getInstance()->getRow('
                SELECT payment_type, delivery
                FROM `' . _DB_PREFIX_ . 'swastarkencl_carriers` 
                WHERE `id_carrier` = ' . pSQL((int)$id) . '
            ');
        }

        public static function hasZone($carrierId, $zoneId)
        {
            Db::getInstance()->query('
                SELECT *
                FROM `' . _DB_PREFIX_ . 'carrier_zone` 
                WHERE `id_carrier` = ' . pSQL((int)$carrierId) . ' AND `id_zone` = ' . pSQL((int)$zoneId) . '
            ');

            return (Db::getInstance()->numRows() > 0 ? true : false);
        }
    }
}
