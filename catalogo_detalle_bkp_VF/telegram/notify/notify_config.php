<?php
declare(strict_types=1);

/**
 * notify/notify_config.php
 * Destinos Telegram por tipo de alerta.
 * Puedes poner IDs privados y/o IDs de grupos (-123...).
 */
$NOTIFY_CHAT_IDS = [
  'new_customer' => ['9914324'],
  'new_order'    => ['9914324'],
  'new_cart'     => ['9914324'],
  'abandoned'    => ['9914324'],
  'status'       => ['9914324'],
  'kpi'          => ['9914324'],
];
