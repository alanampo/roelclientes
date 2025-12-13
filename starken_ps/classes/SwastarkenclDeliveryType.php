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

class SwastarkenclDeliveryType extends ObjectModel
{
    public $code_dls;
    public $name;
    public $description;
    
    public static $definition = [
        'table' => 'swastarkencl_delivery_types',
        'primary' => 'id_swastarkencl_delivery_types',
        'fields' => [
            'code_dls' => ['type' => self::TYPE_INT, 'validate' => 'isUnsignedId', 'required' => true],
            'name' => ['type' => self::TYPE_STRING, 'validate' => 'isGenericName', 'required' => true],
            'description' => ['type' => self::TYPE_STRING, 'required' => true],
        ],
    ];

    /**
     * Get Delivery Type Id with its code dls.
     *
     * @param string $code_dls code dls
     *
     * @return int swastarkencl_delivery_types id
     */
    public static function getIdByCodeDls($code_dls)
    {
        if (empty($code_dls)) {
            return false;
        }
        $cacheId = 'SwastarkenclDeliveryType::getIdByCodeDls_' . pSQL($code_dls);
        if (!Cache::isStored($cacheId)) {
            $result = (int) Db::getInstance()->getValue('
                SELECT `id_swastarkencl_delivery_types`
                FROM `' . _DB_PREFIX_ . 'swastarkencl_delivery_types`
                WHERE `code_dls` = ' . pSQL($code_dls) . '
            ');
            Cache::store($cacheId, $result);

            return $result;
        }

        return Cache::retrieve($cacheId);
    }

    public static function all()
    {
        return Db::getInstance()->ExecuteS('
            SELECT `name`
            FROM `' . _DB_PREFIX_ . 'swastarkencl_delivery_types`
        ');
    }

    public static function getDLSByType($type)
    {
        if (Tools::strtolower($type) == 'agencia') {
            $type = "'agencia', 'sucursal', 'agencias', 'sucursales'";
        } else {
            $type = "'domicilio'";
        }
        return Db::getInstance()->getValue("
            SELECT `code_dls`
            FROM `" . _DB_PREFIX_ . "swastarkencl_delivery_types`
            WHERE LOWER(`name`) IN (" . Tools::strtolower($type) . ")
        ");
    }
}
