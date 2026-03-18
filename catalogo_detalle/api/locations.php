<?php
// catalogo_detalle/api/locations.php
// Retorna regiones y comunas desde locations_cl.js + datos de ciudad desde BD
declare(strict_types=1);
require __DIR__ . '/_bootstrap.php';

$db = db();

// Obtener todas las comunas con sus ciudades desde la BD
$query = "SELECT nombre, ciudad FROM comunas ORDER BY nombre ASC";
$result = mysqli_query($db, $query);

$comunasDB = [];
if ($result) {
  while ($row = mysqli_fetch_assoc($result)) {
    $nombre = trim((string)$row['nombre']);
    $ciudad = trim((string)$row['ciudad']);
    $comunasDB[$nombre] = $ciudad;
  }
}

json_out([
  'ok' => true,
  'comunas_ciudades' => $comunasDB
]);
