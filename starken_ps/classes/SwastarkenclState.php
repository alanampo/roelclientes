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

class SwastarkenclState extends ObjectModel
{
    public $id_starken;
    public $city_dls;
    public $city_starken_id;
    public $city_name;
    public $commune_dls;
    public $commune_name;

    public static $definition = [
        'table' => 'swastarkencl_states',
        'primary' => 'id_swastarkencl_states',
        'fields' => [
            'id_starken' => ['type' => self::TYPE_INT, 'validate' => 'isUnsignedId', 'required' => true],
            'city_dls' => ['type' => self::TYPE_INT, 'validate' => 'isUnsignedId', 'required' => true],
            'city_starken_id' => ['type' => self::TYPE_INT, 'validate' => 'isUnsignedId', 'required' => true],
            'city_name' => ['type' => self::TYPE_STRING, 'required' => true],
            'commune_name' => ['type' => self::TYPE_STRING, 'required' => true],
            'commune_dls' => ['type' => self::TYPE_INT, 'validate' => 'isUnsignedId', 'required' => true],
        ],
    ];

    public static function getStates()
    {
        return Db::getInstance(_PS_USE_SQL_SLAVE_)->executeS('
            SELECT id_swastarkencl_states AS commune_id, commune_name AS name
            FROM `' . _DB_PREFIX_ . 'swastarkencl_states` ORDER BY commune_name ASC
        ');
    }

    public static function getDSLByName($name)
    {
        return Db::getInstance()->getValue('
            SELECT `commune_dls`
            FROM `' . _DB_PREFIX_ . 'swastarkencl_states` 
            WHERE `commune_name` LIKE \'%' . pSQL($name) . '%\'
        ');
    }

    public static function getIdByName($name)
    {
        return Db::getInstance()->getValue('
            SELECT `id_swastarkencl_states`
            FROM `' . _DB_PREFIX_ . 'swastarkencl_states` 
            WHERE `commune_name` LIKE \'%' . pSQL($name) . '%\'
        ');
    }

    public static function getCityDSLByState($stateId)
    {
        return (int) Db::getInstance()->getValue('
            SELECT `city_dls`
            FROM `' . _DB_PREFIX_ . 'swastarkencl_states` 
            WHERE `id_swastarkencl_states` = ' . pSQL((int) $stateId) . '
        ');
    }

    public static function getCityStakenIdByState($stateId)
    {
        return (int) Db::getInstance()->getValue('
            SELECT `city_starken_id`
            FROM `' . _DB_PREFIX_ . 'swastarkencl_states` 
            WHERE `id_swastarkencl_states` = ' . pSQL((int) $stateId) . '
        ');
    }

    public static function getStakenIdById($stateId)
    {
        return Db::getInstance()->getValue('
            SELECT `id_starken`
            FROM `' . _DB_PREFIX_ . 'swastarkencl_states` 
            WHERE `id_swastarkencl_states` = ' . pSQL((int) $stateId) . '
        ');
    }

    public static function clear()
    {
        Db::getInstance()->delete('swastarkencl_states');
    }

    public static function getInstanceById($id)
    {
        $id = Db::getInstance()->getValue('
            SELECT `id_swastarkencl_states`
            FROM `' . _DB_PREFIX_ . 'swastarkencl_states` 
            WHERE `id_swastarkencl_states` = ' . pSQL((int) $id) . '
        ');

        if ($id <= 0) {
            return null;
        }

        return new self($id);
    }
}
