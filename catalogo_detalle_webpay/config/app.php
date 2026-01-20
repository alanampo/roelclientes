<?php
// catalogo_detalle/config/app.php
declare(strict_types=1);

/**
 * Ajustes generales del sistema de carrito/checkout.
 *
 * IMPORTANTE:
 * - WHATSAPP_SELLER_E164: número del vendedor en formato E.164 sin '+' (ej: 56912345678).
 * - Los costos de envío son "estimados" (puedes ajustarlos a tu estándar).
 */

return [
  'WHATSAPP_SELLER_E164' => '56984226651',

  // Texto que se antepone al mensaje de WhatsApp
  'WHATSAPP_PREFIX' => "Pedido generado desde Catálogo Roelplant",

  // Opciones de envío (estimado). Valores en CLP.
  'SHIPPING_OPTIONS' => [
    [ 'code' => 'retiro',       'label' => 'Retiro en vivero',          'cost_clp' => 0 ],
    [ 'code' => 'caja_chica',   'label' => 'Envío caja chica (estimado)',   'cost_clp' => 5990 ],
    [ 'code' => 'caja_mediana', 'label' => 'Envío caja mediana (estimado)', 'cost_clp' => 8990 ],
    [ 'code' => 'caja_grande',  'label' => 'Envío caja grande (estimado)',  'cost_clp' => 12990 ],
  ],

  // Prefijo del código de pedido
  'ORDER_PREFIX' => 'RP',

  // Configuración Webpay (Transbank)
  'WEBPAY_ENVIRONMENT' => getenv('WEBPAY_ENVIRONMENT') ?: 'integration', // 'integration' o 'production'
  'WEBPAY_COMMERCE_CODE' => getenv('WEBPAY_COMMERCE_CODE') ?: '597055555532', // Código de comercio
  'WEBPAY_API_KEY' => getenv('WEBPAY_API_KEY') ?: '579B532A7440BB0C9079DED94D31EA1615BACEB56610332264630D42D0A36B1C', // Llave secreta
];
