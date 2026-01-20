<?php
// catalogo_detalle/api/production/list.php
declare(strict_types=1);

require __DIR__ . '/../_bootstrap.php';
require __DIR__ . '/_prod_db.php';

// auth
require_auth();

// Estados considerados "en producción" (WIP)
$wipEstados = [0,1,2,3,4,5,6,60];
$in = implode(',', array_map('intval', $wipEstados));

try {
  $pdb = prod_db();

  // Listado de variedades en WIP + precio mayorista
  // Nota: en tu BD, el precio mayorista suele estar en v.precio
  $sql = "
    SELECT
      vp.id                                   AS id_variedad,
      vp.nombre                               AS nombre,
      CONCAT(tp.codigo, LPAD(vp.id_interno,4,'0')) AS referencia,
      vp.precio                               AS precio_mayorista,
      SUM(ap.cant_plantas)                    AS unidades_wip
    FROM articulospedidos ap
    JOIN variedades_producto vp ON vp.id = ap.id_variedad
    JOIN tipos_producto tp      ON tp.id = vp.id_tipo
    WHERE ap.estado IN ($in)
    GROUP BY vp.id, vp.nombre, tp.codigo, vp.id_interno, vp.precio
    HAVING unidades_wip > 0
    ORDER BY unidades_wip DESC
    LIMIT 400
  ";

  $res = mysqli_query($pdb, $sql);
  if (!$res) {
    json_out(['ok'=>false,'error'=>'Error SQL producción: '.mysqli_error($pdb)], 500);
  }

  $items = [];
  while ($row = mysqli_fetch_assoc($res)) {
    $items[] = [
      'id_variedad' => (int)$row['id_variedad'],
      'nombre' => (string)$row['nombre'],
      'referencia' => (string)$row['referencia'],
      'precio_mayorista_clp' => (int)$row['precio_mayorista'],
      'unidades_wip' => (int)$row['unidades_wip'],
    ];
  }

  json_out(['ok'=>true, 'items'=>$items]);

} catch (Throwable $e) {
  json_out(['ok'=>false,'error'=>'Error producción: '.$e->getMessage()], 500);
}
