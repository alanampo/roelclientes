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

class SwastarkenclStarkenEndpointsModuleFrontController extends ModuleFrontController
{
    public function __construct()
    {
        parent::__construct();
        $this->ajax = true;
    }

    public function postProcess()
    {
        if (!Tools::getValue('token') || Tools::getValue('token') != Configuration::get('SWASTARKENCL_TOKEN')) {
            header('Content-Type: text/json; charset=utf-8');
            header('HTTP/1.0 401 Unauthorized');
            echo json_encode(
                [
                    'ErrorMessage' => $this->module->l(
                        'You must enter a valid token to access this resource',
                        'starkenendpoints'
                    )
                ]
            );
        } else {
            // Check if input "check_carrier_id" is a payment on arrival shipping option
            if (Tools::isSubmit('check_carrier_id')) {
                $paymentType = SwastarkenclCarrier::getPaymentTypeByCarrierId(Tools::getValue('check_carrier_id'));
                if ($paymentType['payment_type'] == 3) {
                    echo json_encode([
                        'carrier_payment_type' => $paymentType['payment_type'],
                        'carrier_delivery_type' => $paymentType['delivery'],
                        'arrival_payment_message' => $this->module->l(
                            'The actual amount of the quote may change once the weight, dimensions and route entered 
                            in the form have been validated. You can confirm the value in the shipment tracking.',
                            'starkenendpoints'
                        ),
                    ]);
                } else {
                    echo json_encode([
                        'carrier_payment_type' => $paymentType['payment_type'],
                        'carrier_delivery_type' => $paymentType['delivery'],
                    ]);
                }

                exit;
            }

            if (Tools::isSubmit('rate')) {
                echo json_encode(['rated' => $this->module->hookActionCartSave()]);
                exit;
            }

            if (
                // Tools::isSubmit('agency_dls') &&
                Tools::isSubmit('state_id')
                && Tools::isSubmit('customer_id')
            ) {
                SwastarkenclCustomersAgency::deleteByCustomer(Tools::getValue('customer_id'));
                $swastarkenclCustomersAgency = new SwastarkenclCustomersAgency();
                $swastarkenclCustomersAgency->state_id = Tools::getValue('state_id');
                $swastarkenclCustomersAgency->customer_id = Tools::getValue('customer_id');
                $swastarkenclCustomersAgency->agency_dls = (int) Tools::getValue('agency_dls');
                $swastarkenclCustomersAgency->payment_on_arrival = Tools::getValue('payment_on_arrival');
                if ($swastarkenclCustomersAgency->save()) {
                    echo json_encode(
                        [
                            'message' => $this->module->l('Agency preferences was save', 'starkenendpoints'),
                            'result' => true
                        ]
                    );
                } else {
                    echo json_encode(['result' => false]);
                }
                exit;
            }

            if (Tools::isSubmit('ctacte')) {
                $curl = new Curl\Curl();
                $curl->setHeader('Content-Type', 'application/json');
                $curl->setHeader('Authorization', 'Bearer ' . Configuration::get('SWASTARKENCL_USER_TOKEN'));
                $curl->get(
                    Configuration::get('SWASTARKENCL_API_URL')
                        . '/emision/credito-cliente/cc/' . Tools::getValue('ctacte')
                );

                echo json_encode($curl->response);
                exit;
            }

            if (Tools::getIsset('send_mail')) {

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
                $order = new Order(Tools::getValue('order_id'));
                $carrier = new Carrier($order->id_carrier);
                $customer = new Customer(Tools::getValue('id_customer'));
                $firstName = $customer->firstname;
                $lastName = $customer->lastname;
                $email = $customer->email;

                $shop_url = str_replace('//', '/', $shop_url);
                $shop_url = str_replace('https:/', 'https://', $shop_url);

                if (Tools::getValue('orden_flete') == 0) {
                    $followup_link = $shop_url;
                } elseif (strlen($carrier->url) > 0) {
                    $followup_link = str_replace("@", Tools::getValue('orden_flete'), $carrier->url);
                } else {
                    $followup_link = "https://www.starken.cl/seguimiento?codigo=" . Tools::getValue('orden_flete');
                }

                $mail = Mail::Send(
                    (int)(Configuration::get('PS_LANG_DEFAULT')), // defaut language id
                    'in_transit_custom', // email template file to be use
                    'En transito', // email subject
                    array(
                        '{shop_url}' => $shop_url,
                        '{firstname}' => $firstName,
                        '{lastname}' => $lastName,
                        '{order_name}' => $order->reference,
                        '{followup}' => (Tools::getValue('orden_flete') == 0) ? $order->reference : Tools::getValue('orden_flete'), // email content
                        '{followup_link}' => $followup_link
                    ),
                    $email,
                    NULL, //receiver name
                    NULL, //from email address
                    NULL,  //from name
                    NULL, //file attachment
                    NULL, //mode smtp
                    _PS_MODULE_DIR_ . 'swastarkencl/mails'
                );
                echo json_encode($mail);
                exit;
            }

            $starkenCommuneId = SwastarkenclState::getStakenIdById(Tools::getValue('state_id'));
            $curl = new Curl\Curl();
            $curl->setHeader('Content-Type', 'application/json');
            $curl->setHeader('Authorization', 'Bearer ' . Configuration::get('SWASTARKENCL_USER_TOKEN'));
            $curl->get(Configuration::get('SWASTARKENCL_API_URL') . '/agency/comuna/' . $starkenCommuneId);

            echo json_encode($curl->response);
            exit;
        }
    }
}
