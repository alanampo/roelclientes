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

require_once(dirname(__FILE__) . '/vendor/autoload.php');

class Swastarkencl extends CarrierModule
{
    private $configTemplate;
    private $starkenCarriers;
    private $listOfCarrierToGetOrderShippingCost = [];
    private $listOfCarrierWithCostsToGetOrderShippingCost = [];

    const ENTITY_WITH_INVALID_ID = 70070;
    const DISABLED_FUNCTIONALITY = 70071;
    const INVALID_PRODUCT_DIMENSIONS = 70072;
    const MODULE_IS_INACTIVE = 70073;
    const STARKEN_ENDPOINT_PROBLEM = 70074;
    const STARKEN_ENDPOINT_REQUEST_INFO = 70075;
    const ORDER_ALREADY_USED = 70076;
    const ENTITY_REGISTRATION_FAIL = 70077;
    const PAY_ON_ARRIVAL = 3;
    const PAY_WITH_CHECKING_ACCOUNT = 2;
    const STARKEN_DEFAULT_API_TOKEN = '41543348-b239-41d5-8d61-17f290f02960';

    public function __construct()
    {
        $this->name = 'swastarkencl';
        $this->tab = 'shipping_logistics';
        $this->version = '3.6.1';
        $this->author = 'Softwareagil';
        $this->need_instance = 0;
        $this->bootstrap = true;
        $this->module_key = 'def67c5e3ad0527b7a876fb3231d009b';
        parent::__construct();
        $this->displayName = $this->l('Starken transportist');
        $this->description = $this->l('Your shipments anywhere in Chile');
        $this->confirmUninstall = $this->l('Are you sure you want to uninstall this module?');
        $this->ps_versions_compliancy = ['min' => '1.6', 'max' => _PS_VERSION_];

        if (!_PS_MODE_DEV_ && Configuration::get('SWASTARKENCL_USER_TOKEN') == self::STARKEN_DEFAULT_API_TOKEN) {
            Configuration::updateValue('SWASTARKENCL_USER_TOKEN', '');
        }
    }

    public function install()
    {
        if (!$this->validateDependencies()) {
            return false;
        }

        // By default, ENABLE overrides
        Configuration::updateValue('PS_DISABLE_OVERRIDES', false);
        Configuration::updateValue('SWASTARKENCL_USER_TOKEN', '');

        return (parent::install()
            && $this->registerHook('displayTop')
            && $this->registerHook('header')
            && $this->registerHook('displayHeader')
            && $this->registerHook('backOfficeHeader')
            && $this->registerHook('displayBackOfficeHeader')
            && $this->registerHook('displayOrderDetail')
            && $this->registerHook('displayAdminOrder')
            && $this->registerHook('actionOrderStatusUpdate')
            && $this->registerHook('updateCarrier')
            && $this->registerHook('actionCarrierUpdate')
            && $this->registerHook('displayBeforeCarrier')
            && $this->registerHook('displayCarrierExtraContent')
            && $this->registerHook('actionCartSave')
            && $this->registerHook('actionBeforeCartUpdateQty')
            && $this->registerHook('actionCartUpdateQuantityBefore')
        );
    }

    private function validateDependencies()
    {
        $areAllDepenenciesInstalled = true;
        if (!extension_loaded('curl')) {
            $this->_errors[] = $this->l('You have to enable the cURL extension on your server to install this module');
            $areAllDepenenciesInstalled = false;
        }
        if (!extension_loaded('soap')) {
            $this->_errors[] = $this->l('You have to enable the SOAP extension on your server to install this module');
            $areAllDepenenciesInstalled = false;
        }
        if (!extension_loaded('gd')) {
            $this->_errors[] = $this->l('You have to enable the GD extension on your server to install this module');
            $areAllDepenenciesInstalled = false;
        }
        if (!extension_loaded('intl')) {
            $this->_errors[] = $this->l('You have to enable the Intl extension on your server to install this module');
            $areAllDepenenciesInstalled = false;
        }
        if (!extension_loaded('mbstring')) {
            $this->_errors[] = $this->l(
                'You have to enable the mbstring extension on your server to install this module'
            );
            $areAllDepenenciesInstalled = false;
        }

        return $areAllDepenenciesInstalled;
    }

    private function createIssuanceTypes()
    {
        $allTypesWereCreate = true;
        if (!$this->addTypes('/emision/tipo-documento')) {
            $this->_errors[] = $this->l('There was a problem while adding Document types');
            $allTypesWereCreate = false;
        }

        if (!$this->addTypes('/emision/tipo-entrega')) {
            $this->_errors[] = $this->l('There was a problem while adding Delivery types');
            $allTypesWereCreate = false;
        }

        if (!$this->addTypes('/emision/tipo-servicio')) {
            $this->_errors[] = $this->l('There was a problem while adding Service types');
            $allTypesWereCreate = false;
        }

        if (!$this->addTypes('/emision/tipo-pago')) {
            $this->_errors[] = $this->l('There was a problem while adding Payment types');
            $allTypesWereCreate = false;
        }

        return $allTypesWereCreate;
    }

    private function registerCarriers()
    {
        $carriersRegistered = true;
        if (!$this->addCarriers()) {
            $this->_errors[] = $this->l("There was a problem while adding the carrier");
            $carriersRegistered = false;
        }

        if (!$this->addRanges($this->starkenCarriers)) {
            $this->_errors[] = $this->l("There was a problem while adding price and weight ranges to the carrier");
            $carriersRegistered = false;
        }

        if (!$this->setCarrierGroups($this->starkenCarriers)) {
            $this->_errors[] = $this->l("There was a problem while adding groups to the carrier");
            $carriersRegistered = false;
        }

        $this->setCarrierZones($this->starkenCarriers);

        return $carriersRegistered;
    }

    public function uninstall()
    {
        include(dirname(__FILE__) . '/sql/uninstall.php');
        Configuration::deleteByName('SWASTARKENCL_TOKEN');
        Configuration::deleteByName('SWASTARKENCL_ENABLE_LOGS');
        Configuration::deleteByName('SWASTARKENCL_API_URL');
        Configuration::deleteByName('SWASTARKENCL_USER_TOKEN');
        Configuration::deleteByName('SWASTARKENCL_ORDER_STATE');
        Configuration::deleteByName('SWASTARKENCL_API_URL');
        Configuration::deleteByName('SWASTARKENCL_CLIENT_RUT');
        Configuration::deleteByName('SWASTARKENCL_ORIGIN_AGENCY');
        Configuration::deleteByName('SWASTARKENCL_CHECKING_ACCOUNT_SELECTED');
        Configuration::deleteByName('SWASTARKENCL_CENTER_COST_SELECTED');
        Configuration::deleteByName('SWASTARKENCL_HIDE_SHIPPING_WITH_COST_0');
        Configuration::deleteByName('SWASTARKENCL_ENABLE_CHECKING_ACCOUNT');
        Configuration::deleteByName('SWASTARKENCL_SYNCHRONIZE_CARRIERS_AND_ZONES');
        $this->disableCarrier();

        return parent::uninstall();
    }

    public function addRutVerificationDigit($rut)
    {
        $r = (int)$rut;
        $s = 1;
        for ($m = 0; $r != 0; $r /= 10) {
            $s = ($s + $r % 10 * (9 - $m++ % 6)) % 11;
        };

        return $rut . '-' . chr($s ? $s + 47 : 75);
    }

    public function getContent()
    {
        if (
            Tools::isSubmit('SWASTARKENCL_CREATE_SETUP')
            && Tools::isSubmit('SWASTARKENCL_USER_TOKEN')
        ) {
            $this->createSetup();
        }

        if (Tools::isSubmit('SWASTARKENCL_GENERAL_SETTINGS')) {
            $this->postProcess();
        }

        $userAPIToken = Configuration::get('SWASTARKENCL_USER_TOKEN');
        $adminToken = Tools::getAdminTokenLite('AdminModules');
        $formAction = AdminController::$currentIndex
            . '&configure=' . $this->name
            . '&tab_module=' . $this->tab
            . '&module_name=' . $this->name
            . '&token=' . $adminToken;

        if (
            Tools::isEmpty($userAPIToken)
            || !$userAPIToken
        ) {
            $this->context->smarty->assign([
                'swastarkencl_form_action' => $formAction,
                'swastarkencl_form_back_action' => AdminController::$currentIndex . '&token=' . $adminToken,
                'swastarkencl_logo' => Tools::getShopDomainSsl(true) . $this->_path . 'logo.png',
            ]);

            $this->configTemplate .= $this->context->smarty->fetch(
                $this->local_path . 'views/templates/admin/create-setup.tpl'
            );

            return $this->configTemplate;
        }

        $this->context->smarty->registerPlugin('modifier', 'starken_add_dv', [$this, 'addRutVerificationDigit']);

        $curl = new Curl\Curl();
        $curl->setHeader('Content-Type', 'application/json');
        $curl->setHeader('Authorization', 'Bearer ' . $userAPIToken);
        $curl->get(Configuration::get('SWASTARKENCL_API_URL') . '/emision/credito-cliente/ctacte');

        if ($curl->error || (isset($curl->response->status) && in_array($curl->response->status, [500, 400]))) {
            $this->addLog(
                json_encode([
                    'message' => $this->l(
                        'There was an error trying to get starken->emision->credito_cliente->ctacte endpoint at config'
                    ),
                    'request' => [
                        'code' => $curl->errorCode,
                        'message' => $curl->errorMessage,
                    ],
                    'response' => [
                        'status' => isset($curl->response->status) ? $curl->response->status : '',
                        'message' => isset($curl->response->error) ? $curl->response->error : '',
                    ],
                ]),
                3,
                self::STARKEN_ENDPOINT_PROBLEM,
                'Swastarkencl',
                $this->id
            );
        }

        $swastarkenclCheckingAccounts = is_array($curl->response) ? $curl->response : [];
        if (count($swastarkenclCheckingAccounts) > 0) {
            Configuration::updateValue('SWASTARKENCL_CLIENT_RUT', $swastarkenclCheckingAccounts[0]->rut);
        }

        $this->context->smarty->assign([
            'swastarkencl_errors' => $this->_errors,
            'swastarkencl_form_action' => $formAction,
            'swastarkencl_form_back_action' => AdminController::$currentIndex . '&token=' . $adminToken,
            'swastarkencl_logo' => Tools::getShopDomainSsl(true) . $this->_path . 'logo.png',
            'swastarkencl_carrier_ids' => implode(
                ', ',
                json_decode(Configuration::get('SWASTARKENCL_CARRIER_IDS'), true)
            ),
            'swastarkencl_form_back_action' => AdminController::$currentIndex . '&token=' . $adminToken,
            'swastarkencl_order_states' => OrderState::getOrderStates(Configuration::get('PS_LANG_DEFAULT')),
            'swastarkencl_enable_logs' => Configuration::get('SWASTARKENCL_ENABLE_LOGS'),
            'swastarkencl_api_url' => Configuration::get('SWASTARKENCL_API_URL'),
            'swastarkencl_user_token' => $userAPIToken,
            'swastarkencl_order_state' => Configuration::get('SWASTARKENCL_ORDER_STATE'),
            'swastarkencl_client_rut' => Configuration::get('SWASTARKENCL_CLIENT_RUT'),
            'swastarkencl_communes' => SwastarkenclState::getStates(),
            'swastarkencl_commune_id' => Configuration::get('PS_SHOP_STATE_ID'),

            'swastarkencl_starkenendpoints_link' => $this->context->link->getModuleLink(
                $this->name,
                'StarkenEndpoints',
                ['token' => Configuration::get('SWASTARKENCL_TOKEN')]
            ),
            'swastarkencl_origin_agency' => Configuration::get('SWASTARKENCL_ORIGIN_AGENCY'),
            'swastarkencl_checking_accounts' => $swastarkenclCheckingAccounts,
            'swastarkencl_checking_account_selected' => Configuration::get('SWASTARKENCL_CHECKING_ACCOUNT_SELECTED'),
            'swastarkencl_center_cost_selected' => Configuration::get('SWASTARKENCL_CENTER_COST_SELECTED'),
            'swastarkencl_logs' => SwastarkenclLogs::getLogs(),
            'swastarkencl_hide_shipping_with_cost_0' => Configuration::get('SWASTARKENCL_HIDE_SHIPPING_WITH_COST_0'),
            'swastarkencl_enable_checking_account' => Configuration::get('SWASTARKENCL_ENABLE_CHECKING_ACCOUNT'),
            'swastarkencl_synchronize_carriers_and_zones' => Configuration::get(
                'SWASTARKENCL_SYNCHRONIZE_CARRIERS_AND_ZONES'
            ),
        ]);

        $this->configTemplate .= $this->context->smarty->fetch(
            $this->local_path . 'views/templates/admin/configure.tpl'
        );

        return $this->configTemplate;
    }

    public function createSetup()
    {
        $debugToken = self::STARKEN_DEFAULT_API_TOKEN == trim(Tools::getValue('SWASTARKENCL_USER_TOKEN'));
        if (
            Tools::isEmpty(trim(Tools::getValue('SWASTARKENCL_USER_TOKEN')))
            || ($debugToken && !_PS_MODE_DEV_)
        ) {
            return;
        }

        Configuration::updateValue(
            'SWASTARKENCL_TOKEN',
            md5(date('Y-m-d-H:i:s') . '-' . $this->name . '-' . rand(1, 9999))
        );
        Configuration::updateValue('SWASTARKENCL_API_URL', 'https://gateway.starken.cl/externo/integracion');
        Configuration::updateValue('SWASTARKENCL_ENABLE_LOGS', 1);
        Configuration::updateValue('SWASTARKENCL_USER_TOKEN', trim(Tools::getValue('SWASTARKENCL_USER_TOKEN')));
        Configuration::updateValue('SWASTARKENCL_ORDER_STATE', 3);
        Configuration::updateValue('SWASTARKENCL_CLIENT_RUT', '');
        Configuration::updateValue('SWASTARKENCL_CARRIER_IDS', json_encode([]));
        Configuration::updateValue('SWASTARKENCL_ORIGIN_AGENCY', '');
        Configuration::updateValue('SWASTARKENCL_CHECKING_ACCOUNT_SELECTED', '');
        Configuration::updateValue('SWASTARKENCL_CENTER_COST_SELECTED', '');
        Configuration::updateValue('SWASTARKENCL_HIDE_SHIPPING_WITH_COST_0', '');
        Configuration::updateValue('SWASTARKENCL_ENABLE_CHECKING_ACCOUNT', '');
        Configuration::updateValue('SWASTARKENCL_SYNCHRONIZE_CARRIERS_AND_ZONES', true);

        include(dirname(__FILE__) . '/sql/install.php');

        if (!$this->createIssuanceTypes()) {
            return false;
        }

        if (!$this->addStates()) {
            $this->_errors[] = $this->l('There was a problem while adding the states');
            return false;
        }

        if (!$this->registerCarriers()) {
            return false;
        }

        $this->configureCountry();
    }

    private function postProcess()
    {
        $formFields = $this->getConfigFormValues();
        $this->validateFields();
        if (count($this->_errors) > 0) {
            return false;
        }
        foreach (array_keys($formFields) as $key) {
            if (!Configuration::updateValue($key, Tools::getValue($key))) {
                $this->_errors[] = $this->l('Could not save values for') . $key;
                return false;
            }
        }

        if (Tools::isSubmit('SWASTARKENCL_SYNCHRONIZE_CARRIERS_AND_ZONES')) {
            $this->synchronizeCarriersAndZones();
        }

        $this->configTemplate .= $this->displayConfirmation($this->l('Settings updated'));

        return true;
    }

    private function synchronizeCarriersAndZones()
    {
        $carrierIds = json_decode(Configuration::get('SWASTARKENCL_CARRIER_IDS'), true);

        foreach ($carrierIds as $carrierId) {
            $this->starkenCarriers[] = new Carrier($carrierId);
        }

        $this->setCarrierZones($this->starkenCarriers);
        unset($this->starkenCarriers);
    }

    private function getConfigFormValues()
    {
        return [
            'SWASTARKENCL_ENABLE_LOGS' => Configuration::get('SWASTARKENCL_ENABLE_LOGS'),
            'SWASTARKENCL_API_URL' => Configuration::get('SWASTARKENCL_API_URL'),
            'SWASTARKENCL_USER_TOKEN' => Configuration::get('SWASTARKENCL_USER_TOKEN'),
            'SWASTARKENCL_ORDER_STATE' => Configuration::get('SWASTARKENCL_ORDER_STATE'),
            'SWASTARKENCL_CLIENT_RUT' => Configuration::get('SWASTARKENCL_CLIENT_RUT'),
            'PS_SHOP_STATE_ID' => Configuration::get('PS_SHOP_STATE_ID'),
            'SWASTARKENCL_ORIGIN_AGENCY' => Configuration::get('SWASTARKENCL_ORIGIN_AGENCY'),
            'SWASTARKENCL_CHECKING_ACCOUNT_SELECTED' => Configuration::get('SWASTARKENCL_CHECKING_ACCOUNT_SELECTED'),
            'SWASTARKENCL_CENTER_COST_SELECTED' => Configuration::get('SWASTARKENCL_CENTER_COST_SELECTED'),
            'SWASTARKENCL_HIDE_SHIPPING_WITH_COST_0' => Configuration::get('SWASTARKENCL_HIDE_SHIPPING_WITH_COST_0'),
            'SWASTARKENCL_ENABLE_CHECKING_ACCOUNT' => Configuration::get('SWASTARKENCL_ENABLE_CHECKING_ACCOUNT'),
            'SWASTARKENCL_SYNCHRONIZE_CARRIERS_AND_ZONES' => Configuration::get(
                'SWASTARKENCL_SYNCHRONIZE_CARRIERS_AND_ZONES'
            ),
        ];
    }

    private function validateFields()
    {
        if (!Validate::isUrl(Tools::getValue('SWASTARKENCL_API_URL'))) {
            $this->_errors[] = $this->l('API\'s URL is not valid');
        }
        if (!Validate::isString(Tools::getValue('SWASTARKENCL_USER_TOKEN'))) {
            $this->_errors[] = $this->l('User token is not valid');
        }
        if (!Validate::isInt(Tools::getValue('SWASTARKENCL_ORDER_STATE'))) {
            $this->_errors[] = $this->l('Orden state is not valid');
        }
        if (
            !empty(Tools::getValue('SWASTARKENCL_CLIENT_RUT'))
            && !preg_match("/^\d{8}\-{1}[\w|\d]{1}$/", Tools::getValue('SWASTARKENCL_CLIENT_RUT'))
        ) {
            $this->_errors[] = $this->l('The client rut is not valid');
        }
    }

    private function addTypes($endpoint)
    {
        $curl = new Curl\Curl();
        $curl->setHeader('Content-Type', 'application/json');
        $curl->setHeader('Authorization', 'Bearer ' . Configuration::get('SWASTARKENCL_USER_TOKEN'));
        $curl->get(Configuration::get('SWASTARKENCL_API_URL') . $endpoint);

        if ($curl->error) {
            if (Configuration::get('SWASTARKENCL_ENABLE_LOGS')) {
                PrestaShopLogger::addLog(
                    'Error: ' . $curl->errorCode . ': ' . $curl->errorMessage,
                    3,
                    null,
                    'Swastarkencl',
                    null,
                    true,
                    $this->context->employee->id
                );
            }
            return false;
        } else {
            foreach ($curl->response as $responseType) {
                $newType = null;
                switch ($endpoint) {
                    case '/emision/tipo-documento':
                        if (SwastarkenclDocumentType::getIdByCodeDls($responseType->codigo_dls) == 0) {
                            $newType = new SwastarkenclDocumentType();
                        }
                        break;

                    case '/emision/tipo-entrega':
                        if (SwastarkenclDeliveryType::getIdByCodeDls($responseType->codigo_dls) == 0) {
                            $newType = new SwastarkenclDeliveryType();
                        }
                        break;

                    case '/emision/tipo-servicio':
                        if (SwastarkenclServiceType::getIdByCodeDls($responseType->codigo_dls) == 0) {
                            $newType = new SwastarkenclServiceType();
                        }
                        break;

                    case '/emision/tipo-pago':
                        if (SwastarkenclPaymentType::getIdByCodeDls($responseType->codigo_dls) == 0) {
                            $newType = new SwastarkenclPaymentType();
                        }
                        break;
                }
                if ($newType != null) {
                    try {
                        $newType->code_dls = (int) $responseType->codigo_dls;
                        $newType->name = $responseType->nombre;
                        $newType->description = $responseType->descripcion;
                        $newType->add();
                    } catch (PrestaShopDatabaseException $e) {
                        $this->addLog(
                            json_encode([
                                'message' => $e->getMessage()
                            ]),
                            2,
                            self::ENTITY_REGISTRATION_FAIL,
                            get_class($newType),
                            null
                        );
                    }
                }
            }
        }
        return true;
    }

    private function addStates()
    {
        $curl = new Curl\Curl();
        $curl->setHeader('Content-Type', 'application/json');
        $curl->setHeader('Authorization', 'Bearer ' . Configuration::get('SWASTARKENCL_USER_TOKEN'));
        $curl->setOpt(CURLOPT_TIMEOUT, 500);
        $curl->get(Configuration::get('SWASTARKENCL_API_URL') . '/agency/comuna');

        if ($curl->error || (isset($curl->response) && is_array($curl->response) && count($curl->response) == 0)) {
            $this->addLog(json_encode([
                'message' => $this->l('There was a problem connecting to starken communes endpoint'),
                'errorCode' => $curl->errorCode,
                'errorMessage' => $curl->errorMessage
            ]));
            return false;
        } else {
            SwastarkenclState::clear();
            foreach ($curl->response as $commune) {
                try {
                    $swastarkenclState = new SwastarkenclState();
                    $swastarkenclState->id_starken = $commune->id;
                    $swastarkenclState->commune_dls = $commune->code_dls;
                    $swastarkenclState->city_dls = $commune->city->code_dls;
                    $swastarkenclState->city_starken_id = $commune->city->id;
                    $swastarkenclState->city_name = $commune->city->name;
                    $swastarkenclState->commune_name = $commune->name;
                    $swastarkenclState->save();
                } catch (PrestaShopException $e) {
                    $this->addLog($e->errorMessage());
                }
            }
        }
        return true;
    }

    private function addCarriers()
    {
        $carriersInfo = [
            [
                'name' => $this->l('Normal to agency service'),
                'service' => 'NORMAL',
                'delivery' => 'AGENCIA',
                'payment_type' => 2,
            ],
            [
                'name' => $this->l('Normal to residence service'),
                'service' => 'NORMAL',
                'delivery' => 'DOMICILIO',
                'payment_type' => 2,
            ],
            [
                'name' => $this->l('Express to agency service'),
                'service' => 'EXPRESS',
                'delivery' => 'AGENCIA',
                'payment_type' => 2,
            ],
            [
                'name' => $this->l('Express to residence service'),
                'service' => 'EXPRESS',
                'delivery' => 'DOMICILIO',
                'payment_type' => 2,
            ],
            [
                'name' => $this->l('Normal to agency service - Pay on arrival'),
                'service' => 'NORMAL',
                'delivery' => 'AGENCIA',
                'payment_type' => 3,
            ],
            [
                'name' => $this->l('Normal to residence service - Pay on arrival'),
                'service' => 'NORMAL',
                'delivery' => 'DOMICILIO',
                'payment_type' => 3,
            ],
            [
                'name' => $this->l('Express to agency service - Pay on arrival'),
                'service' => 'EXPRESS',
                'delivery' => 'AGENCIA',
                'payment_type' => 3,
            ],
            [
                'name' => $this->l('Express to residence service - Pay on arrival'),
                'service' => 'EXPRESS',
                'delivery' => 'DOMICILIO',
                'payment_type' => 3,
            ],
        ];
        $ids = [];

        foreach ($carriersInfo as $carrierInfo) {
            $carrier = new Carrier();
            $carrier->name = $carrierInfo['name'];
            $carrier->is_module = true;
            $carrier->active = 1;
            $carrier->range_behavior = 1;
            $carrier->need_range = 1;
            $carrier->shipping_external = true;
            $carrier->range_behavior = 0;
            $carrier->external_module_name = $this->name;
            $carrier->shipping_method = 2;
            // $carrier->url = '';
            $langs = Language::getLanguages();
            foreach ($langs as $lang) {
                $carrier->delay[$lang['id_lang']] = $this->description;
            }

            if ($carrier->add()) {
                $ids[] = (int)$carrier->id;
                $swastarkenclCarrier = new SwastarkenclCarrier();
                $swastarkenclCarrier->id_carrier = (int)$carrier->id;
                $swastarkenclCarrier->delivery = $carrierInfo['delivery'];
                $swastarkenclCarrier->service = $carrierInfo['service'];
                if (isset($carrierInfo['payment_type']) && $carrierInfo['payment_type'] != null) {
                    $swastarkenclCarrier->payment_type = $carrierInfo['payment_type'];
                }
                $swastarkenclCarrier->save();

                if (!copy(
                    dirname(__FILE__) . '/views/img/carrier_32x32.jpg',
                    _PS_SHIP_IMG_DIR_ . '/' . $carrier->id . '.jpg'
                )) {
                    $this->_errors[] = $this->l('Copying carrier image did not succeed');
                }
                $this->starkenCarriers[] = $carrier;
            }
        }
        Configuration::updateValue('SWASTARKENCL_CARRIER_IDS', json_encode($ids));

        if (count($this->starkenCarriers) > 1) {
            return true;
        }
    }

    private function addRanges($carriers)
    {
        foreach ($carriers as $carrier) {
            $rangePrice = new RangePrice();
            $rangePrice->id_carrier = $carrier->id;
            $rangePrice->delimiter1 = 0.0;
            $rangePrice->delimiter2 = 100000000.0;
            $rangePrice->add();

            $rangeWeight = new RangeWeight();
            $rangeWeight->id_carrier = $carrier->id;
            $rangeWeight->delimiter1 = 0.0;
            $rangeWeight->delimiter2 = 10000.0;
            $rangeWeight->add();
        }
        return true;
    }

    private function setCarrierGroups($carriers)
    {
        $groupsIds = [];
        $groups = Group::getGroups($this->context->language->id);
        foreach ($groups as $group) {
            $groupsIds[] = $group['id_group'];
        }

        foreach ($carriers as $carrier) {
            $carrier->setGroups($groupsIds);
        }
        return true;
    }

    private function setCarrierZones($carriers)
    {
        $zones = Zone::getZones();
        foreach ($carriers as $carrier) {
            foreach ($zones as $zone) {
                if (!SwastarkenclCarrier::hasZone($carrier->id, $zone['id_zone'])) {
                    $carrier->addZone($zone['id_zone']);
                }
            }
        }
    }

    private function disableCarrier()
    {
        if (!empty(Configuration::get('SWASTARKENCL_CARRIER_IDS'))) {
            $ids = json_decode(Configuration::get('SWASTARKENCL_CARRIER_IDS'), true);
            foreach ($ids as $id) {
                $carrier = Carrier::getCarrierByReference($id);
                if ($carrier) {
                    $carrier->active = 0;
                    $carrier->update();
                }
            }
        }
    }

    private function configureCountry()
    {
        $format = Db::getInstance()->getValue(
            "SELECT `format` FROM `" . _DB_PREFIX_ . "address_format` WHERE id_country = 68"
        );
        $newFormat = '';

        if (strpos($format, 'firstname') === false) {
            $newFormat .= 'firstname\n ';
        }
        if (strpos($format, 'lastname') === false) {
            $newFormat .= 'lastname\n ';
        }
        if (strpos($format, 'dni') === false) {
            $newFormat .= 'dni\n ';
        }
        if (strpos($format, 'address1') === false) {
            $newFormat .= 'address1\n ';
        }
        if (strpos($format, 'other') === false) {
            $newFormat .= 'other\n ';
        }
        if (strpos($format, 'address2') === false) {
            $newFormat .= 'address2\n ';
        }
        if (strpos($format, 'Country:name') === false) {
            $newFormat .= 'Country:name\n ';
        }
        if (strpos($format, 'city') === false) {
            $newFormat .= 'city\n ';
        }
        if (strpos($format, 'phone') === false) {
            $newFormat .= 'phone\n ';
        }
        $newFormat .= $format;

        Db::getInstance()->update(
            'address_format',
            [
                'format' => $newFormat,
            ],
            'id_country = 68'
        );
    }

    public function hookBackOfficeHeader($params)
    {
        if (
            in_array($this->context->controller->controller_name, ['AdminModules'])
            && Tools::getValue('configure') == $this->name
            && !Tools::isEmpty(Configuration::get('SWASTARKENCL_USER_TOKEN'))
        ) {
            $this->context->controller->addCSS($this->getPathUri() . '/views/css/swastarkencl-backoffice.css', 'all');
            $this->context->controller->addJS($this->getPathUri() . '/views/js/swastarkencl-backoffice.js');
        }
    }

    public function hookDisplayBackOfficeHeader($params)
    {
        $this->hookBackOfficeHeader($params);
    }

    public function hookHeader()
    {
        if (method_exists($this->context->controller, 'registerStylesheet')) {
            $this->context->controller->registerStylesheet(
                'modules-swastarkencl-front-css',
                'modules/' . $this->name . '/views/css/swastarkencl-front.css',
                ['server' => 'local', 'media' => 'all', 'priority' => 1001]
            );
            $this->context->controller->registerJavascript(
                'modules-swastarkencl-front-js',
                'modules/' . $this->name . '/views/js/swastarkencl-front.js',
                ['server' => 'local', 'position' => 'bottom', 'priority' => 1002]
            );
        } else {
            $this->context->controller->addCSS($this->getPathUri() . '/views/css/swastarkencl-front.css', 'all');
            $this->context->controller->addJS($this->getPathUri() . '/views/js/swastarkencl-front.js');
        }
    }

    public function hookDisplayHeader()
    {
        $this->hookHeader();
    }

    public function hookDisplayCarrierExtraContent($carrier)
    {
        $starkenCarrier = SwastarkenclCarrier::all();
    }

    public function hookDisplayBeforeCarrier($params)
    {
        if (!$this->active) {
            return '';
        }

        $starkenStateId = 0;
        $userStarkenStateId = (int) SwastarkenclCustomersAgency::getStateIdByCustomer(
            $this->context->customer->id
        );

        if (isset($params['cart']) && isset($params['cart']->id_customer)) {
            $address = new Address($params['cart']->id_address_delivery);
            $state = new State($address->id_state);
            $starkenStateId = SwastarkenclState::getIdByName($state->name);
        }

        $destinationAgencyDLSCode = (int) SwastarkenclCustomersAgency::getAgencyDLS(
            ($userStarkenStateId > 0 ? $userStarkenStateId : $starkenStateId),
            $this->context->customer->id
        );

        $this->context->smarty->assign([
            'swastarkencl_logged_user' => $this->context->customer->id,
            'swastarkencl_user_commune' => $userStarkenStateId > 0 ? $userStarkenStateId : $starkenStateId,
            'swastarkencl_communes' => SwastarkenclState::getStates(),
            'swastarkencl_starkenendpoints_link' => $this->context->link->getModuleLink(
                $this->name,
                'StarkenEndpoints',
                ['token' => Configuration::get('SWASTARKENCL_TOKEN')]
            ),
            'swastarkencl_ps16' => version_compare(_PS_VERSION_, '1.7.0', '<='),
            'swastarkencl_carrier_ids' => implode(
                ', ',
                json_decode(Configuration::get('SWASTARKENCL_CARRIER_IDS'), true)
            ),
            'swastarkencl_destination_agency_dls_code' => $destinationAgencyDLSCode,
        ]);

        return $this->display(__FILE__, 'views/templates/hook/before-carriers.tpl');
    }

    public function hookDisplayTop()
    {
        return $this->display(__FILE__, 'views/templates/hook/top.tpl');
    }

    public function hookDisplayOrderDetail($params)
    {
        $order = new Order($params['order']->id);
        $address = new Address($order->id_address_delivery);
        $issue = new SwastarkenclEmision(SwastarkenclEmision::getIdByOrder($order->id));
        $customer = new Customer($order->id_customer);
        $tracking = null;

        if ($order->id <= 0 || $address->id <= 0 || $issue->id <= 0 || $customer->id <= 0) {
            return '';
        }

        if ($issue->orden_flete > 0) {
            $curl = new Curl\Curl();
            $curl->setHeader('Content-Type', 'application/json');
            $curl->setHeader('Cache-Control', 'no-cache');
            $curl->setHeader('Authorization', 'Bearer ' . Configuration::get('SWASTARKENCL_USER_TOKEN'));
            $curl->get(Configuration::get('SWASTARKENCL_API_URL') . '/tracking/orden-flete/of/' . $issue->orden_flete);

            if ($curl->error || (isset($curl->response->status) && $curl->response->status == 500)) {
                $this->addLog(json_encode([
                    'message' => $this->l('There was an error trying to get tracking info.'),
                    'freightOrder' => $issue->orden_flete,
                    'errorCode' => $curl->errorCode,
                    'errorMessage' => $curl->errorMessage,
                ]));
            } else {
                $tracking = $curl->response;
            }
        }

        $issue->tipo_entrega = json_decode($issue->tipo_entrega, true);
        $issue->tipo_pago = json_decode($issue->tipo_pago, true);
        $issue->tipo_servicio = json_decode($issue->tipo_servicio, true);

        $issue->coordinate = '';
        if (!empty($issue->latitud) && !empty($issue->longitud)) {
            $issue->coordinate = $issue->latitud . ', ' . $issue->longitud;
        }

        $this->context->smarty->assign([
            'swastarkencl_emision' => $issue,
            'swastarkencl_tracking' => $tracking,
        ]);

        return $this->display(__FILE__, 'views/templates/hook/order-details.tpl');
    }

    public function hookDisplayAdminOrder($params)
    {
        $order = new Order(Tools::getValue('id_order'));
        $address = new Address($order->id_address_delivery);
        $issue = new SwastarkenclEmision(SwastarkenclEmision::getIdByOrder($order->id));
        $customer = new Customer($order->id_customer);
        $tracking = null;


        if ($order->id <= 0 || $address->id <= 0 || $customer->id <= 0) {
            return '';
        }

        $id_order_carrier = $order->getIdOrderCarrier();
        if ($id_order_carrier) {
            $orderCarrier = new OrderCarrier($id_order_carrier);
            $orderCarrier->tracking_number = $issue->orden_flete;
            $orderCarrier->update();
        }


        if ((int)$issue->id <= 0) {
            $this->context->smarty->assign([
                'swastarkencl_issue_problem' => Tools::getValue('starken-issue-problem'),
                'swastarkencl_module_config_link' => $this->context->link->getAdminLink(
                    'AdminModules',
                    true,
                    [],
                    ['configure' => $this->name]
                ),
            ]);
            return $this->display(__FILE__, 'views/templates/hook/admin-order.tpl');
        }

        if ($issue->orden_flete <= 0) {
            $curl = new Curl\Curl();
            $curl->setHeader('Content-Type', 'application/json');
            $curl->setHeader('Cache-Control', 'no-cache');
            $curl->setHeader('Authorization', 'Bearer ' . Configuration::get('SWASTARKENCL_USER_TOKEN'));
            $curl->get(Configuration::get('SWASTARKENCL_API_URL') . '/emision/consulta/' . $issue->id_emision);

            if ($curl->error || (isset($curl->response->status) && $curl->response->status == 500)) {
                $this->addLog(json_encode([
                    'message' => $this->l('There was an error trying to consult the issue'),
                    'issue' => $issue->id_emision,
                    'errorCode' => $curl->errorCode,
                    'errorMessage' => $curl->errorMessage,
                ]));
            } else {
                $issue->centro_costo = $curl->response->centro_costo;
                $issue->valor = $curl->response->valor;
                $issue->direccion_normalizada = $curl->response->direccion_normalizada;
                $issue->latitud = $curl->response->latitud;
                $issue->longitud = $curl->response->longitud;
                $issue->orden_flete = $curl->response->orden_flete;
                $issue->retiro_asociado = $curl->response->retiro_asociado;
                $issue->impresiones = $curl->response->impresiones;
                $issue->master_id = $curl->response->master_id;
                $issue->status = $curl->response->status;
                $issue->retry = $curl->response->retry;
                $issue->queue_id = $curl->response->queue_id;
                $issue->estado = $curl->response->estado;
                $issue->etiqueta = $curl->response->etiqueta;
                $issue->observacion = $curl->response->observacion;
                $issue->created_at = $curl->response->created_at;
                $issue->updated_at = $curl->response->updated_at;
                $issue->encargos = json_encode($curl->response->encargos);
                $issue->user = json_encode(isset($curl->response->user) ? $curl->response->user : []);
                $issue->update();
            }
        } else {
            $curl = new Curl\Curl();
            $curl->setHeader('Content-Type', 'application/json');
            $curl->setHeader('Cache-Control', 'no-cache');
            $curl->setHeader('Authorization', 'Bearer ' . Configuration::get('SWASTARKENCL_USER_TOKEN'));
            $curl->get(Configuration::get('SWASTARKENCL_API_URL') . '/tracking/orden-flete/of/' . $issue->orden_flete);

            if ($curl->error || (isset($curl->response->status) && $curl->response->status == 500)) {
                $this->addLog(json_encode([
                    'message' => $this->l('There was an error trying to get tracking info.'),
                    'freightOrder' => $issue->orden_flete,
                    'errorCode' => $curl->errorCode,
                    'errorMessage' => $curl->errorMessage,
                ]));
            } else {
                $tracking = $curl->response;
            }
        }

        $issue->tipo_entrega = json_decode($issue->tipo_entrega, true);
        $issue->tipo_pago = json_decode($issue->tipo_pago, true);
        $issue->tipo_servicio = json_decode($issue->tipo_servicio, true);

        $issue->coordinate = '';
        if (!empty($issue->latitud) && !empty($issue->longitud)) {
            $issue->coordinate = $issue->latitud . ', ' . $issue->longitud;
        }

        $this->changeAgencyDLSCodeByItsName($issue);

        if (isset($tracking->issuer_rut)) {
            $tracking->issuer_rut = $this->formatRutNumber($tracking->issuer_rut);
            $tracking->receiver_rut = $this->formatRutNumber($tracking->receiver_rut);
        }

        if (!isset($issue->direccion_agencia_origen)) {
            $issue->direccion_agencia_origen = null;
        }

        if (!isset($issue->direccion_agencia_origen)) {
            $issue->direccion_agencia_destino = null;
        }

        $this->context->smarty->assign([
            'swastarkencl_email_send' => $this->context->link->getModuleLink(
                $this->name,
                'StarkenEndpoints',
                [
                    'send_mail' => true,
                    'orden_flete' => $issue->orden_flete,
                    'order_id' => $order->id,
                    'id_customer' => $customer->id,
                    'token' => Configuration::get('SWASTARKENCL_TOKEN')
                ]
            ),
            'swastarkencl_total_shipping' => (float)$order->total_shipping,
            'swastarkencl_emision' => $issue,
            'swastarkencl_tracking' => $tracking,
            'swastarkencl_sender' => Configuration::get('PS_SHOP_NAME'),
            'swastarkencl_sender_phone' => Configuration::get('PS_SHOP_PHONE'),
            'swastarkencl_ps_logo' => (Tools::getShopDomainSsl(true) . __PS_BASE_URI__ . '/img/' . Configuration::get('PS_LOGO')
            ),
            'swastarkencl_recipient_name' => $customer->firstname . ' ' . $customer->lastname,
            'swastarkencl_recipient_phone' => (!empty($address->phone) ? $address->phone : $address->phone_mobile),
        ]);

        return $this->display(__FILE__, 'views/templates/hook/admin-order.tpl');
    }

    private function formatRutNumber($rut)
    {
        $formattedRut = '--';
        if (isset($rut)) {
            $formattedRut = str_split(str_replace('-', '', $rut));
            $formattedRutLastItem = array_slice($formattedRut, -1, 1);
            $formattedRut[key(array_slice($formattedRut, -1, 1, true))] = '-';
            $formattedRut[] = $formattedRutLastItem[0];
            $formattedRut = implode('', $formattedRut);
        }
        return $formattedRut;
    }

    private function changeAgencyDLSCodeByItsName(&$issue)
    {
        $curl = new Curl\Curl();
        $curl->setHeader('Content-Type', 'application/json');
        $curl->setHeader('Cache-Control', 'no-cache');
        $curl->setHeader('Authorization', 'Bearer ' . Configuration::get('SWASTARKENCL_USER_TOKEN'));
        $curl->get(Configuration::get('SWASTARKENCL_API_URL') . '/agency/agency');
        if ($curl->error || (isset($curl->response->status) && $curl->response->status == 500)) {
            $this->addLog(json_encode([
                'message' => $this->l('There was an error trying to get agency details'),
                'agency' => $issue->codigo_agencia_origen,
                'errorCode' => $curl->errorCode,
                'errorMessage' => $curl->errorMessage,
            ]));
        } else {
            foreach ($curl->response as $agency) {
                if ($issue->codigo_agencia_origen == $agency->code_dls) {
                    $issue->codigo_agencia_origen = $agency->name;
                    $issue->direccion_agencia_origen = $agency->address;
                    break;
                }
            }

            if ($issue->codigo_agencia_destino != 0) {
                foreach ($curl->response as $agency) {
                    if ($issue->codigo_agencia_destino == $agency->code_dls) {
                        $issue->codigo_agencia_destino = $agency->name;
                        $issue->direccion_agencia_destino = $agency->address;
                        break;
                    }
                }
            }
        }
    }

    /**
     * Update carrier id if carrier is edited in Backoffice
     *
     * @see http://doc.prestashop.com/display/PS16/Creating+a+carrier+module
     * @param  Array $params carrier data
     * @return void
     */
    public function hookUpdateCarrier($params)
    {
        $carrierIds = json_decode(Configuration::get('SWASTARKENCL_CARRIER_IDS'), true);
        $newCarrierIds = [];
        $newCarrierIds[] = (int) $params['carrier']->id;
        if (in_array($params['id_carrier'], $carrierIds)) {
            foreach ($carrierIds as $carrierId) {
                if ($params['id_carrier'] != $carrierId) {
                    $newCarrierIds[] = (int) $carrierId;
                }
                if ($params['id_carrier'] == $carrierId) {
                    SwastarkenclCarrier::updateCarrierByPreviousOne((int) $carrierId, (int) $params['carrier']->id);
                }
            }
            Configuration::updateValue('SWASTARKENCL_CARRIER_IDS', json_encode($newCarrierIds));
        }
    }

    public function hookActionCarrierUpdate($params)
    {
        $this->hookUpdateCarrier($params);
    }

    public function sendMailInTransit($order_id, $idFlete) //($idCustomer, $orderName, $idFlete)
    {
        $idShop = Context::getContext()->shop->id;
        $idLang = Context::getContext()->language->id;
        $shop_url = Context::getContext()->link->getPageLink(
            'order-detail',
            true,
            $idLang,
            [
                "id_order" => $order_id
            ],
            false,
            $idShop
        );
        $order = new Order($order_id);
        $customer = new Customer($order->id_customer);
        $firstName = $customer->firstname;
        $lastName = $customer->lastname;
        $carrier = new Carrier($order->id_carrier);
        $email = $customer->email;

        $shop_url = str_replace('//', '/', $shop_url);
        $shop_url = str_replace('https:/', 'https://', $shop_url);

        if ($idFlete == 0) {
            $followup_link = $shop_url;
        } elseif (strlen($carrier->url) > 0) {
            $followup_link = str_replace("@", $idFlete, $carrier->url);
        } else {
            $followup_link = "https://www.starken.cl/seguimiento?codigo=$idFlete";
        }

        $mail = Mail::Send(
            $this->context->language->id, // defaut language id
            'in_transit_custom', // email template file to be use
            $this->l('In transit'), // email subject
            [
                '{shop_url}' => $shop_url,
                '{firstname}' => $firstName,
                '{lastname}' => $lastName,
                '{order_name}' => $order->reference,
                '{followup}' => ($idFlete == 0) ? "(ver en tienda)" : $idFlete, // email content
                '{followup_link}' => $followup_link
            ],
            $email, ///Configuration::get('PS_SHOP_EMAIL'), // receiver email address
            NULL, //receiver name
            NULL, //from email address
            NULL,  //from name
            NULL, //file attachment
            NULL, //mode smtp
            _PS_MODULE_DIR_ . 'swastarkencl/mails'
        );
    }

    public function hookActionOrderStatusUpdate($params)
    {
        if (!_PS_MODE_DEV_ && Configuration::get('SWASTARKENCL_USER_TOKEN') == self::STARKEN_DEFAULT_API_TOKEN) {
            return;
        }

        if (!$this->active) {
            return;
        }

        if (Configuration::get('SWASTARKENCL_ORDER_STATE') != $params['newOrderStatus']->id) {
            return;
        }

        $issueId = SwastarkenclEmision::getIdByOrder($params['id_order']);
        $order = new Order($params['id_order']);

        if ($issueId > 0) {
            $issue = new SwastarkenclEmision($issueId);
            // $this->sendMailInTransit($order->id_customer, $order->reference, $issue->orden_flete);

            $this->addLog(
                json_encode([
                    'message' => $this->l('This order is in use in another Starken Issue')
                ]),
                1,
                self::ORDER_ALREADY_USED,
                'SwastarkenclEmision',
                $issueId
            );
            Tools::redirectAdmin(
                $this->context->link->getAdminLink(
                    'AdminOrders',
                    true,
                    [],
                    [
                        'vieworder' => true,
                        'starken-issue-problem' => true,
                        'id_order' => $params['id_order']
                    ]
                )
            );
        }

        if (!in_array($order->id_carrier, json_decode(Configuration::get('SWASTARKENCL_CARRIER_IDS'), true))) {
            return;
        }

        $address = new Address($order->id_address_delivery);
        $customer = new Customer($order->id_customer);
        $destinationState = SwastarkenclState::getInstanceById(
            SwastarkenclCustomersAgency::getStateIdByCustomer($customer->id)
        );
        $originState = SwastarkenclState::getInstanceById(Configuration::get('PS_SHOP_STATE_ID'));
        $swastarkenclCarrier = new SwastarkenclCarrier(SwastarkenclCarrier::getIdByCarrier($order->id_carrier));

        if (!isset($swastarkenclCarrier->id) || $swastarkenclCarrier->id <= 0) {
            $this->addLog(
                json_encode([
                    'message' => $this->l('Starken Carrier is not valid')
                ]),
                2,
                self::ENTITY_WITH_INVALID_ID,
                'SwastarkenclCarrier',
                is_object($swastarkenclCarrier) ? $swastarkenclCarrier->id : null
            );
            Tools::redirectAdmin(
                $this->context->link->getAdminLink(
                    'AdminOrders',
                    true,
                    [],
                    [
                        'vieworder' => true,
                        'starken-issue-problem' => true,
                        'id_order' => Tools::getValue('id_order')
                    ]
                )
            );
        }

        if (!isset($originState->id) || $originState->id <= 0) {
            $this->addLog(
                json_encode([
                    'message' => $this->l('The Origin Agency Code is not valid')
                ]),
                2,
                self::ENTITY_WITH_INVALID_ID,
                'State',
                $originState->id
            );
            Tools::redirectAdmin(
                $this->context->link->getAdminLink(
                    'AdminOrders',
                    true,
                    [],
                    [
                        'vieworder' => true,
                        'starken-issue-problem' => true,
                        'id_order' => Tools::getValue('id_order')
                    ]
                )
            );
        }

        if (!isset($destinationState->id) || $destinationState->id <= 0) {
            $this->addLog(
                json_encode([
                    'message' => $this->l('The Destination Agency Code is not valid')
                ]),
                2,
                self::ENTITY_WITH_INVALID_ID,
                'State',
                $destinationState->id
            );
            Tools::redirectAdmin(
                $this->context->link->getAdminLink(
                    'AdminOrders',
                    true,
                    [],
                    [
                        'vieworder' => true,
                        'starken-issue-problem' => true,
                        'id_order' => Tools::getValue('id_order')
                    ]
                )
            );
        }

        $cartProducts = null;
        $cart = null;
        if (empty($params['cart'])) {
            $cart = Cart::getCartByOrderId($params['id_order']);
            $cartProducts = $cart->getProducts();
        } else {
            $cart = $params['cart'];
            $cartProducts = $cart->getProducts();
        }

        $weight = 0.0;
        $height = 0.0;
        $width = 0.0;
        $depth = 0.0;
        $volume = 0.0;
        $dimensions = [];
        $declaredProductsPrice = 0.0;
        $productNamesAsDescription = [];
        $onlyOneProduct = true;

        foreach ($cartProducts as $product) {
            if ($product['cart_quantity'] > 1) {
                $onlyOneProduct = false;
            }
            $productNamesAsDescription[] = $product['name'] . ' (' . $product['cart_quantity'] . ')';
            $declaredProductsPrice += $product['price_without_reduction'] * $product['cart_quantity'];
            $dimensions[] = $product['width'];
            $dimensions[] = $product['height'];
            $dimensions[] = $product['depth'];
            $volume += ($product['width'] * $product['height'] * $product['depth']) * $product['cart_quantity'];
            $weight += $product['weight'] * $product['cart_quantity'];
        }

        if (count($cartProducts) > 1) {
            $onlyOneProduct = false;
        }

        $width = max($dimensions);

        if ($width <= 0) {
            return;
        }

        $height = sqrt(($volume / $width) * 2 / 3);

        if ($height <= 0) {
            return;
        }

        if (!$onlyOneProduct) {
            $width = max($dimensions);
            $height = sqrt(($volume / $width) * 2 / 3);
            $depth = $volume / $width / $height;
        } else {
            $width = $dimensions[0];
            $height = $dimensions[1];
            $depth = $dimensions[2];
        }

        if ($width <= 0 || $height <= 0 || $depth <= 0 || $weight <= 0) {
            $this->addLog(
                json_encode([
                    'message' => $this->l(
                        'The Product / Some products in the cart, need to update its dimensions or weight'
                    ),
                    'request' => [
                        'dimensions' => ['width' => $width, 'height' => $height, 'depth' => $depth, 'weight' => $weight]
                    ]
                ]),
                2,
                self::INVALID_PRODUCT_DIMENSIONS,
                'Product'
            );
            Tools::redirectAdmin(
                $this->context->link->getAdminLink(
                    'AdminOrders',
                    true,
                    [],
                    [
                        'vieworder' => true,
                        'starken-issue-problem' => true,
                        'id_order' => Tools::getValue('id_order')
                    ]
                )
            );
        }

        $codigoAgenciaOrigen = Configuration::get('SWASTARKENCL_ORIGIN_AGENCY');
        $codigoAgenciaDestino = SwastarkenclCustomersAgency::getAgencyDLS(
            $destinationState->id,
            $customer->id
        );

        if ($codigoAgenciaOrigen <= 0 && $codigoAgenciaDestino <= 0) {
            $curl = new Curl\Curl();
            $curl->setHeader('Content-Type', 'application/json');
            $curl->setHeader('Authorization', 'Bearer ' . Configuration::get('SWASTARKENCL_USER_TOKEN'));
            $originCityId = SwastarkenclState::getCityStakenIdByState($originState->id);
            $curl->get(Configuration::get('SWASTARKENCL_API_URL') . '/agency/city/' . $originCityId);

            if ($curl->error || (isset($curl->response->status) && $curl->response->status == 500)) {
                $this->addLog(json_encode([
                    'message' => $this->l('There was a problem connecting to starken city endpoint'),
                    'request' => Configuration::get('SWASTARKENCL_API_URL') . '/agency/city/' . $originCityId,
                    'response' => $curl->response,
                ]));
                return;
            }

            $weightAndVolumeRestrictions = [
                'MT' => ['peso' => 35.0, 'volume' => 125000.0],
                'BT' => ['peso' => 100.0, 'volume' => 100000000.0],
                'GT' => ['peso' => 100.001, 'volume' => 100000000.001],
            ];
            $weightRestrictions = [];

            foreach ($weightAndVolumeRestrictions as $key => $value) {
                if ($value['peso'] >= $weight &&  $value['volume'] >= $volume) {
                    $weightRestrictions[] = $key;
                }
            }

            $weightRestrictions = implode('-', $weightRestrictions);
            foreach ($curl->response->comunas as $comuna) {
                if ($comuna->code_dls == $originState->commune_dls) {
                    foreach ($comuna->agencies as $agency) {
                        if (!empty($agency->code_dls) && $agency->weight_restriction == $weightRestrictions) {
                            $codigoAgenciaOrigen = $agency->code_dls;
                        }
                    }
                }
                if ($comuna->code_dls == $destinationState->commune_dls) {
                    foreach ($comuna->agencies as $agency) {
                        if (!empty($agency->code_dls) && $agency->weight_restriction == $weightRestrictions) {
                            $codigoAgenciaDestino = $agency->code_dls;
                        }
                    }
                }
            }
            $curl->close();
        }

        $serviceTypeDLSCode = SwastarkenclServiceType::getDLSByType($swastarkenclCarrier->service);

        if ($serviceTypeDLSCode < 0 || $serviceTypeDLSCode == '' || $serviceTypeDLSCode == null) {
            $this->addLog(
                json_encode([
                    'message' => $this->l('The carrier service type is not registered')
                ]),
                2,
                self::ENTITY_WITH_INVALID_ID,
                'SwastarkenclCarrier',
                $swastarkenclCarrier->id
            );
            Tools::redirectAdmin(
                $this->context->link->getAdminLink(
                    'AdminOrders',
                    true,
                    [],
                    [
                        'vieworder' => true,
                        'starken-issue-problem' => true,
                        'id_order' => Tools::getValue('id_order')
                    ]
                )
            );
        }

        $orderDescription = implode(', ', $productNamesAsDescription);
        $documentDescription = (Tools::strlen($orderDescription) > 200
            ? Tools::substr($orderDescription, 0, 197) . '...'
            : $orderDescription
        );
        $data = [];
        $data = [
            "codigo_agencia_origen" => $codigoAgenciaOrigen,
            "codigo_agencia_destino" => $codigoAgenciaDestino,
            "destinatario_rut" => $address->dni,
            "destinatario_nombres" => $address->firstname,
            "destinatario_paterno" => $address->lastname,
            "destinatario_telefono" => (!empty($address->phone) ? $address->phone : $address->phone_mobile),
            "destinatario_email" => $customer->email,
            "destinatario_contacto" => $address->firstname . ' ' . $address->lastname,
            "destinatario_direccion" => $address->address1,
            "destinatario_numeracion" => $address->address2,
            "destinatario_departamento" => $address->other,
            "destinatario_codigo_comuna" => $destinationState->commune_dls,
            "contenido" => $order->reference,
            "valor_declarado" => (int) round($cart->getOrderTotal()),
            "tipo_entrega" => [
                "codigo_dls" => (int) SwastarkenclDeliveryType::getDLSByType($swastarkenclCarrier->delivery),
            ],
            "tipo_pago" => [
                "codigo_dls" => (int) ($swastarkenclCarrier->payment_type != self::PAY_ON_ARRIVAL
                    ? self::PAY_WITH_CHECKING_ACCOUNT
                    : self::PAY_ON_ARRIVAL
                ),
            ],
            "tipo_servicio" => [
                "codigo_dls" => (int) $serviceTypeDLSCode,
            ],
            "encargos" => [
                [
                    "descripcion" => $documentDescription,
                    "tipo_encargo" => ($volume <= 2250 && $weight <= 0.3 ? 'SOBRE' : 'BULTO'
                    ),
                    "kilos" => (float) number_format($weight, 2),
                    "alto" => (float) number_format($height, 2),
                    "ancho" => (float) number_format($width, 2),
                    "largo" => (float) number_format($depth, 2)
                ]
            ]
        ];

        // Starken Restriction
        $declaredValueFlag = 50000;

        if ((int) $data['valor_declarado'] >= $declaredValueFlag) {
            $data['encargos'][] = [
                'tipo_documento' => [
                    'id' => 6, // Fijo
                    'codigo_dls' => "1686", // Fijo
                    'nombre' => 'Orden de compra',
                    'descripcion' => 'Orden de compra'
                ],
                'tipo_encargo' => 'DOCUMENTO', // Fijo
                'numero_documento' => $order->id,
                'descripcion' => $documentDescription
            ];
        }

        if (
            Configuration::get('SWASTARKENCL_CHECKING_ACCOUNT_SELECTED')
            && $swastarkenclCarrier->payment_type != self::PAY_ON_ARRIVAL
        ) {
            $ctacte = explode('-', Configuration::get('SWASTARKENCL_CHECKING_ACCOUNT_SELECTED'));
            $data['cuenta_corriente'] = $ctacte[0];
        }

        if (
            Configuration::get('SWASTARKENCL_CENTER_COST_SELECTED')
            && $swastarkenclCarrier->payment_type != self::PAY_ON_ARRIVAL
        ) {
            $data['centro_costo'] = Configuration::get('SWASTARKENCL_CENTER_COST_SELECTED');
        }

        $curl = new Curl\Curl();
        $curl->setHeader('Content-Type', 'application/json');
        $curl->setHeader('Cache-Control', 'no-cache');
        $curl->setHeader('Authorization', 'Bearer ' . Configuration::get('SWASTARKENCL_USER_TOKEN'));

        if ($swastarkenclCarrier->delivery == 'DOMICILIO') {
            unset($data['codigo_agencia_destino']);
        }

        $curl->post(Configuration::get('SWASTARKENCL_API_URL') . '/emision/emision', $data);

        if ($curl->error || (isset($curl->response->status) && $curl->response->status == 500)) {
            $this->addLog(json_encode([
                'message' => $this->l('The issue could not be created'),
                'request' => $data,
                'response' => $curl->response,
            ]));
            return;
        }

        try {
            $issue = new SwastarkenclEmision();
            $issue->id_order = $order->id;
            $issue->id_emision = $curl->response->id;
            $issue->tipo_entrega = json_encode($curl->response->tipo_entrega);
            $issue->tipo_pago = json_encode($curl->response->tipo_pago);
            $issue->tipo_servicio = json_encode($curl->response->tipo_servicio);
            $issue->cuenta_corriente = $curl->response->cuenta_corriente;
            $issue->centro_costo = (string)$curl->response->centro_costo;
            $issue->valor = $curl->response->valor;
            $issue->codigo_agencia_origen = $curl->response->codigo_agencia_origen;
            $issue->codigo_agencia_destino = $curl->response->codigo_agencia_destino;
            $issue->destinatario_rut = $curl->response->destinatario_rut;
            $issue->destinatario_nombres = $curl->response->destinatario_nombres;
            $issue->destinatario_paterno = $curl->response->destinatario_paterno;
            $issue->destinatario_materno = $curl->response->destinatario_materno;
            $issue->destinatario_razon_social = $curl->response->destinatario_razon_social;
            $issue->destinatario_direccion = $curl->response->destinatario_direccion;
            $issue->destinatario_numeracion = $curl->response->destinatario_numeracion;
            $issue->destinatario_departamento = $curl->response->destinatario_departamento;
            $issue->destinatario_codigo_comuna = $curl->response->destinatario_codigo_comuna;
            $issue->destinatario_telefono = $curl->response->destinatario_telefono;
            $issue->destinatario_email = $curl->response->destinatario_email;
            $issue->destinatario_contacto = $curl->response->destinatario_contacto;
            $issue->contenido = $curl->response->contenido;
            $issue->kilos_total = $curl->response->kilos_total;
            $issue->valor_declarado = $curl->response->valor_declarado;
            $issue->orden_flete = $curl->response->orden_flete;
            $issue->estado = $curl->response->estado;
            $issue->impresiones = $curl->response->impresiones;
            $issue->encargos = isset($curl->response->encargos) ? json_encode($curl->response->encargos) : null;
            $issue->user = isset($curl->response->user) ? json_encode($curl->response->user) : null;
            $issue->master = isset($curl->response->master) ? $curl->response->master : '';
            $issue->master_id = isset($curl->response->master_id) ? $curl->response->master_id : null;
            $issue->user_id = isset($curl->response->user_id) ? $curl->response->user_id : null;
            $issue->etiqueta = $curl->response->etiqueta;
            $issue->status = $curl->response->status;
            $issue->created_at = $curl->response->created_at;
            $issue->direccion_normalizada = $curl->response->direccion_normalizada;
            $issue->latitud = $curl->response->latitud;
            $issue->longitud = $curl->response->longitud;
            $issue->retiro_asociado = $curl->response->retiro_asociado;
            $issue->queue_id = $curl->response->queue_id;
            $issue->observacion = $curl->response->observacion;
            $issue->retry = $curl->response->retry;
            $issue->updated_at = $curl->response->updated_at;
            $issue->save();

            $this->sendMailInTransit($params['id_order'], $issue->orden_flete);

            //$order->shipping_number = $issue->orden_flete;
            //$order->update();

            $this->addLog(
                json_encode([
                    'message' => $this->l(
                        'Request to generate an Starken Issue'
                    ),
                    'request' => $data,
                    'response' => $curl->response,
                ]),
                1,
                self::STARKEN_ENDPOINT_REQUEST_INFO,
                'SwastarkenclEmision',
                null
            );
        } catch (PrestaShopException $e) {
            $this->addLog(
                json_encode([
                    'message' => $e->getMessage(),
                    'request' => $data,
                    'response' => $curl->response,
                ]),
                3,
                self::STARKEN_ENDPOINT_PROBLEM,
                'SwastarkenclEmision',
                $issue->id
            );

            $curl->close();

            Tools::redirectAdmin(
                $this->context->link->getAdminLink(
                    'AdminOrders',
                    true,
                    [],
                    [
                        'vieworder' => true,
                        'starken-issue-problem' => true,
                        'id_order' => Tools::getValue('id_order')
                    ]
                )
            );
        }

        $curl->close();
    }

    public function getOrderShippingCostExternal($params)
    {
        return $this->getOrderShippingCost($params, Configuration::get('PS_SHIPPING_HANDLING'));
    }

    public function hookActionCartSave()
    {
        if (!$this->active) {
            return;
        }

        if (!isset($this->context->cart->id_address_delivery) || $this->context->cart->id_address_delivery <= 0) {
            $this->addLog(
                json_encode([
                    'message' => $this->l('Customer delivery address is not valid')
                ]),
                2,
                self::ENTITY_WITH_INVALID_ID,
                'Customer',
                $this->context->customer->id
            );
            return;
        }

        if ((int) Configuration::get('PS_SHOP_STATE_ID') <= 0) {
            $this->addLog(
                json_encode([
                    'message' => $this->l(
                        'Shop origin state is not valid. Please, select an origin state in Starken Module Configuration'
                    )
                ]),
                2,
                self::ENTITY_WITH_INVALID_ID,
                'Cart',
                $this->context->cart->id
            );
            return;
        }

        $destinationState = (int) SwastarkenclCustomersAgency::getStateIdByCustomer($this->context->customer->id);

        if ($destinationState <= 0) {
            $this->addLog(
                json_encode([
                    'message' => $this->l(
                        'Origin state is not valid. Customer need to set an origin state'
                    )
                ]),
                2,
                self::ENTITY_WITH_INVALID_ID,
                'Customer',
                $this->context->customer->id
            );
            return;
        }

        if ((int) Configuration::get('PS_SHOP_STATE_ID') <= 0) {
            $this->addLog(
                json_encode([
                    'message' => $this->l(
                        'Origin state is not valid. Customer need to set an origin state'
                    )
                ]),
                2,
                self::ENTITY_WITH_INVALID_ID,
                'Customer',
                $this->context->customer->id
            );
            return;
        }

        $weight = 0.0;
        $height = 0.0;
        $width = 0.0;
        $depth = 0.0;
        $volume = 0.0;
        $dimensions = [];
        $onlyOneProduct = true;
        $productIds = [];

        $cartProducts = $this->context->cart->getProducts();
        if (count($cartProducts) == 0) {
            return;
        }

        foreach ($cartProducts as $product) {
            if ($product['cart_quantity'] > 1) {
                $onlyOneProduct = false;
            }
            $dimensions[] = $product['width'];
            $dimensions[] = $product['height'];
            $dimensions[] = $product['depth'];
            $volume += ($product['width'] * $product['height'] * $product['depth']) * $product['cart_quantity'];
            $weight += $product['weight'] * $product['cart_quantity'];

            $productIds[] = $product['id_product'];
        }

        if (count($cartProducts) > 1) {
            $onlyOneProduct = false;
        }

        $width = max($dimensions);

        if ($width <= 0) {
            return;
        }

        $height = sqrt(($volume / $width) * 2 / 3);

        if ($height <= 0) {
            return;
        }

        $depth = $volume / $width / $height;

        if (!$onlyOneProduct) {
            $width = max($dimensions);
            $height = sqrt(($volume / $width) * 2 / 3);
            $depth = $volume / $width / $height;
        } else {
            $width = $dimensions[0];
            $height = $dimensions[1];
            $depth = $dimensions[2];
        }

        if ($width <= 0 || $height <= 0 || $depth <= 0 || $weight <= 0) {
            $this->addLog(
                json_encode([
                    'message' => $this->l(
                        'Some products in cart need its dimensions'
                    )
                ]),
                2,
                self::INVALID_PRODUCT_DIMENSIONS,
                'Product',
                null
            );
            return false;
        }

        $package = 'BULTO';
        if ($volume <= 2250 && $weight <= 0.3) {
            $package = 'SOBRE';
        }

        $ctacte = explode('-', Configuration::get('SWASTARKENCL_CHECKING_ACCOUNT_SELECTED'));
        $data = [
            'origen' => SwastarkenclState::getCityDSLByState((int) Configuration::get('PS_SHOP_STATE_ID')),
            'destino' => SwastarkenclState::getCityDSLByState($destinationState),
            // 'run' => $ctacte[0],
            'bulto' => $package,
            'alto' => (float) number_format($height, 2),
            'ancho' => (float) number_format($width, 2),
            'largo' => (float) number_format($depth, 2),
            'kilos' => (float) number_format($weight, 2),
            // 'precio' => (float) number_format($cartProductsPrice, 2),
            'todas_alternativas' => true,
        ];

        if (Configuration::get('SWASTARKENCL_ENABLE_CHECKING_ACCOUNT') && isset($ctacte)) {
            $data['ctacte'] = $ctacte[0];
            $data['ctacte_dv'] = $ctacte[1];
        }

        $curl = new Curl\Curl();
        $curl->setHeader('Content-Type', 'application/json');
        $curl->setHeader('Cache-Control', 'no-cache');
        $curl->setHeader('Authorization', 'Bearer ' . Configuration::get('SWASTARKENCL_USER_TOKEN'));
        $curl->post(Configuration::get('SWASTARKENCL_API_URL') . '/quote/cotizador-multiple', json_encode($data));

        if ($curl->error || (isset($curl->response->status) && in_array($curl->response->status, [500, 400, 404]))) {
            $this->context->cookie->__set('swastarkencl_shipping_product_ids', json_encode([]));
            $this->context->cookie->__set('swastarkencl_shipping_data', json_encode([]));
            $this->context->cookie->__set('swastarkencl_shipping_options_costs', "{}");
            $this->addLog(
                json_encode([
                    'message' => $this->l(
                        'There was an error trying to consult to the endpoint: starken->quote->cotizador'
                    ),
                    'request' => $data,
                    'response' => $curl->response,
                ]),
                3,
                self::STARKEN_ENDPOINT_PROBLEM,
                'Cart',
                $this->context->cart->id
            );
            $this->context->cookie->__set('swastarkencl_shipping_product_ids', json_encode([]));
            $this->context->cookie->__set('swastarkencl_shipping_data', json_encode([]));
            $this->context->cookie->__set('swastarkencl_shipping_options_costs', '{}');
            return false;
        }

        $this->context->cookie->__set('swastarkencl_shipping_product_ids', json_encode($productIds));
        $this->context->cookie->__set('swastarkencl_shipping_data', json_encode($data));
        $this->context->cookie->__set('swastarkencl_shipping_options_costs', json_encode($curl->response));
        $curl->close();
        $this->addLog(
            json_encode([
                'message' => $this->l('Request made to the endpoint: starken->quote->cotizador'),
                'request' => $data,
                'response' => $curl->response,
            ]),
            1,
            self::STARKEN_ENDPOINT_REQUEST_INFO,
            'Cart',
            $this->context->cart->id
        );

        return true;
    }

    public function hookActionCartUpdateQuantityBefore()
    {
        $this->hookActionCartSave();
    }

    public function hookActionBeforeCartUpdateQty()
    {
        $this->hookActionCartUpdateQuantityBefore();
    }

    public function getOrderShippingCost($params, $shipping_cost)
    {
        if (
            Validate::isLoadedObject($this->context->customer)
            && (int) SwastarkenclCustomersAgency::getStateIdByCustomer($this->context->customer->id) <= 0
        ) {
            return false;
        }

        if (!$this->active) {
            return false;
        }

        $shippingOptionsCosts = json_decode($this->context->cookie->swastarkencl_shipping_options_costs, true);

        if ($shippingOptionsCosts == null || !isset($shippingOptionsCosts['alternativas'])) {
            return false;
        }

        $swastarkenclCarrier = new SwastarkenclCarrier(
            SwastarkenclCarrier::getIdByCarrier($params->swaStarkenCarrier->id)
        );

        if ((int) $swastarkenclCarrier->payment_type <= 0) {
            $swastarkenclCarrier->payment_type = 2;
        }

        foreach ($shippingOptionsCosts['alternativas'] as $alternative) {
            if (
                $this->changeTypeValueBasedOnCarriers($alternative['servicio']) == $swastarkenclCarrier->service
                && $this->changeTypeValueBasedOnCarriers($alternative['entrega']) == $swastarkenclCarrier->delivery
                && $alternative['codigo_tipo_pago'] == $swastarkenclCarrier->payment_type
            ) {
                $alternativeCost = ((float) $alternative['precio'] + (float) $shipping_cost);
                if (Configuration::get('SWASTARKENCL_HIDE_SHIPPING_WITH_COST_0') && $alternativeCost == 0) {
                    return false;
                }
                return $alternativeCost;
            }
        }

        return false;
    }

    private function changeTypeValueBasedOnCarriers($type)
    {
        $type = Tools::strtoupper($type);
        switch ($type) {
            case 'EXPRESO':
                return 'EXPRESS';
            case 'SUCURSAL':
                return 'AGENCIA';
            default:
                return $type;
        }
    }

    public function createTab($name, $className, $parentID = -1, $position = 0, $active = 1)
    {
        $parentTab = new Tab();
        $parentTab->active = $active;
        $languages = Language::getLanguages();
        foreach ($languages as $language) {
            $parentTab->name[$language['id_lang']] = $name;
        }
        $parentTab->class_name = $className;
        $parentTab->module = $this->name;
        $parentTab->id_parent = $parentID;
        $parentTab->position = $position;
        return $parentTab->add();
    }

    public function removeTab($className)
    {
        $tab = new Tab(Tab::getIdFromClassName($className));
        return $tab->delete();
    }

    public static function pr($data, $useDump = false, $stopExecution = true)
    {
        echo '<pre>';
        if (!$useDump) {
            print_r($data);
        } else {
            var_dump($data);
        }
        echo '</pre>';
        if ($stopExecution) {
            exit;
        }
    }

    public function addLog(
        $message,
        $severity = 3,
        $errorCode = null,
        $objectType = null,
        $objectId = null,
        $allowDuplicate = false
    ) {
        if (Configuration::get('SWASTARKENCL_ENABLE_LOGS') && $severity == 1) {
            PrestaShopLogger::addLog(
                $message,
                $severity,
                $errorCode,
                $objectType,
                $objectId,
                $allowDuplicate,
                (isset($this->context->employee->id) ? $this->context->employee->id : null)
            );
        }

        if (Configuration::get('SWASTARKENCL_ENABLE_LOGS') || $severity != 1) {
            PrestaShopLogger::addLog(
                $message,
                $severity,
                $errorCode,
                $objectType,
                $objectId,
                $allowDuplicate,
                (isset($this->context->employee->id) ? $this->context->employee->id : null)
            );
        }
    }
}
