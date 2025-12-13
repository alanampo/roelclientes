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

class SwastarkenclEmision extends ObjectModel
{
    public $id_order;
    public $id_emision;
    public $tipo_entrega;
    public $tipo_pago;
    public $tipo_servicio;
    public $cuenta_corriente;
    public $centro_costo;
    public $valor;
    public $codigo_agencia_origen;
    public $codigo_agencia_destino;
    public $destinatario_rut;
    public $destinatario_nombres;
    public $destinatario_paterno;
    public $destinatario_materno;
    public $destinatario_razon_social;
    public $destinatario_direccion;
    public $destinatario_numeracion;
    public $destinatario_departamento;
    public $destinatario_codigo_comuna;
    public $destinatario_telefono;
    public $destinatario_email;
    public $destinatario_contacto;
    public $contenido;
    public $kilos_total;
    public $valor_declarado;
    public $orden_flete;
    public $estado;
    public $impresiones;
    public $encargos;
    public $user;
    public $master;
    public $master_id;
    public $user_id;
    public $etiqueta;
    public $status;
    public $created_at;
    public $direccion_normalizada;
    public $latitud;
    public $longitud;
    public $retiro_asociado;
    public $queue_id;
    public $observacion;
    public $retry;
    public $updated_at;
    
    public static $definition = [
        'table' => 'swastarkencl_emisiones',
        'primary' => 'id_swastarkencl_emisiones',
        'fields' => [
            'id_order' => ['type' => self::TYPE_INT, 'validate' => 'isUnsignedId', 'required' => true],
            'id_emision' => ['type' => self::TYPE_INT, 'validate' => 'isUnsignedId', 'required' => true],
            'tipo_entrega' => ['type' => self::TYPE_STRING, 'validate' => 'isString'],
            'tipo_pago' => ['type' => self::TYPE_STRING, 'validate' => 'isString'],
            'tipo_servicio' => ['type' => self::TYPE_STRING, 'validate' => 'isString'],
            'cuenta_corriente' => ['type' => self::TYPE_STRING, 'validate' => 'isString'],
            'centro_costo' => ['type' => self::TYPE_STRING, 'validate' => 'isString'],
            'valor' => ['type' => self::TYPE_FLOAT, 'validate' => 'isFloat'],
            'codigo_agencia_origen' => ['type' => self::TYPE_INT, 'validate' => 'isUnsignedId', 'required' => true],
            'codigo_agencia_destino' => ['type' => self::TYPE_INT, 'validate' => 'isUnsignedId'],
            'tipo_servicio' => ['type' => self::TYPE_STRING, 'validate' => 'isString'],
            'destinatario_rut' => ['type' => self::TYPE_STRING, 'validate' => 'isString', 'required' => true],
            'destinatario_nombres' => ['type' => self::TYPE_STRING, 'validate' => 'isString'],
            'destinatario_paterno' => ['type' => self::TYPE_STRING, 'validate' => 'isString'],
            'destinatario_materno' => ['type' => self::TYPE_STRING, 'validate' => 'isString'],
            'destinatario_razon_social' => ['type' => self::TYPE_STRING, 'validate' => 'isString'],
            'destinatario_direccion' => ['type' => self::TYPE_STRING, 'validate' => 'isString'],
            'destinatario_numeracion' => ['type' => self::TYPE_STRING, 'validate' => 'isString'],
            'destinatario_departamento' => ['type' => self::TYPE_STRING, 'validate' => 'isString'],
            'destinatario_codigo_comuna' => ['type' => self::TYPE_INT, 'validate' => 'isUnsignedId'],
            'destinatario_telefono' => ['type' => self::TYPE_STRING, 'validate' => 'isString', 'required' => true],
            'destinatario_email' => ['type' => self::TYPE_STRING, 'validate' => 'isString', 'required' => true],
            'destinatario_contacto' => ['type' => self::TYPE_STRING, 'validate' => 'isString', 'required' => true],
            'contenido' => ['type' => self::TYPE_STRING, 'validate' => 'isString', 'required' => true],
            'kilos_total' => ['type' => self::TYPE_FLOAT, 'validate' => 'isFloat', 'required' => true],
            'valor_declarado' => ['type' => self::TYPE_FLOAT, 'validate' => 'isFloat', 'required' => true],
            'orden_flete' => ['type' => self::TYPE_INT, 'validate' => 'isUnsignedId'],
            'estado' => ['type' => self::TYPE_STRING, 'validate' => 'isString'],
            'impresiones' => ['type' => self::TYPE_INT, 'validate' => 'isUnsignedId'],
            'encargos' => ['type' => self::TYPE_STRING, 'validate' => 'isString'],
            'user' => ['type' => self::TYPE_STRING, 'validate' => 'isString'],
            'master' => ['type' => self::TYPE_STRING, 'validate' => 'isString'],
            'master_id' => ['type' => self::TYPE_INT, 'validate' => 'isUnsignedId'],
            'user_id' => ['type' => self::TYPE_INT, 'validate' => 'isUnsignedId'],
            'etiqueta' => ['type' => self::TYPE_STRING, 'validate' => 'isString'],
            'status' => ['type' => self::TYPE_STRING, 'validate' => 'isString'],
            'created_at' => ['type' => self::TYPE_STRING, 'validate' => 'isString'],
            'direccion_normalizada' => ['type' => self::TYPE_STRING, 'validate' => 'isString'],
            'latitud' => ['type' => self::TYPE_STRING, 'validate' => 'isString'],
            'longitud' => ['type' => self::TYPE_STRING, 'validate' => 'isString'],
            'retiro_asociado' => ['type' => self::TYPE_STRING, 'validate' => 'isString'],
            'queue_id' => ['type' => self::TYPE_STRING, 'validate' => 'isString'],
            'observacion' => ['type' => self::TYPE_STRING, 'validate' => 'isString'],
            'retry' => ['type' => self::TYPE_STRING, 'validate' => 'isString'],
            'updated_at' => ['type' => self::TYPE_STRING, 'validate' => 'isString'],
        ],
    ];

    public static function getIdByOrder($orderId)
    {
        return Db::getInstance()->getValue('
            SELECT `id_swastarkencl_emisiones`
            FROM `' . _DB_PREFIX_ . 'swastarkencl_emisiones`
            WHERE `id_order` = ' . pSQL($orderId) . '
        ');
    }
}
