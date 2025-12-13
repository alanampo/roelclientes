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

function upgrade_module_3_0_0()
{
    # State modification - start
    $queries = null;
    $queries[] = "ALTER TABLE `" . _DB_PREFIX_ . "swastarkencl_states` DROP IF EXISTS `id_state`;";

    $queries[] = "ALTER TABLE `" . _DB_PREFIX_ . "swastarkencl_states` ADD COLUMN `commune_dls` INT NOT NULL;";
    $queries[] = "ALTER TABLE `" . _DB_PREFIX_ . "swastarkencl_states` ADD COLUMN `commune_name` VARCHAR(50) NOT NULL;";

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
    # State modification - end

    return true;
}
