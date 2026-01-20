<?php
// catalogo_detalle/api/production/_prod_db.php
// Conexión SOLO LECTURA a la BD de producción/stock (la misma usada por index.php)
// Busca class_lib/class_conecta_mysql.php hacia arriba, igual que el catálogo.

declare(strict_types=1);

function prod_db(): mysqli {
  static $db = null;
  if ($db instanceof mysqli) return $db;

  $conectaPaths = [
    __DIR__ . '/../../class_lib/class_conecta_mysql.php',
    __DIR__ . '/../../../class_lib/class_conecta_mysql.php',
    __DIR__ . '/../../../../class_lib/class_conecta_mysql.php',
    __DIR__ . '/../../../../../class_lib/class_conecta_mysql.php',
  ];
  $found = false;
  foreach ($conectaPaths as $p) {
    if (is_file($p)) { require $p; $found = true; break; }
  }
  if (!$found) {
    throw new RuntimeException('No se encontró class_lib/class_conecta_mysql.php para BD producción.');
  }

  // Variables esperadas desde class_conecta_mysql.php: $host, $user, $password, $dbname
  $db = @mysqli_connect($host ?? 'localhost', $user ?? '', $password ?? '', $dbname ?? '');
  if (!$db) {
    throw new RuntimeException('Error conexión BD producción: ' . mysqli_connect_error());
  }
  mysqli_set_charset($db, 'utf8');
  return $db;
}
