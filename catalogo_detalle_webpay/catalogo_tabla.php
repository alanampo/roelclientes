<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require __DIR__ . '/../class_lib/class_conecta_mysql.php';

$link = mysqli_connect($host, $user, $password, $dbname);
if (!$link) die("Error conexión: " . mysqli_connect_error());
mysqli_set_charset($link, 'utf8');

/*
 * Consulta: misma lógica que el backend y catálogo mayorista/detalle
 *  - SUM(s.cantidad) sobre stock_productos con ap.estado = 8
 *  - reservas pendientes: r.estado IN (0,1)
 *  - entregado: r.estado = 2 (usando entregas_stock.id_reserva_producto)
 *  - disponible_para_reservar = cantidad - reservada(0,1) - entregada(2)
 *  - Se calcula en un subquery por variedad (sv) y luego se añaden atributos
 */
$sql = "
SELECT
  sv.id_variedad,
  sv.tipo,
  sv.variedad,
  sv.referencia,
  sv.precio,
  sv.precio_detalle,
  sv.disponible_para_reservar,

  MAX(CASE WHEN a.nombre = 'TIPO DE PLANTA' THEN av.valor END) AS tipo_planta,

  GROUP_CONCAT(DISTINCT
    CASE
      WHEN a.nombre IS NULL THEN NULL
      WHEN a.nombre = 'TIPO DE PLANTA' THEN NULL
      WHEN NULLIF(TRIM(av.valor),'') IS NULL THEN NULL
      ELSE CONCAT(a.nombre, ': ', TRIM(av.valor))
    END
    ORDER BY a.nombre
    SEPARATOR '||'
  ) AS attrs_activos

FROM
(
  SELECT
    v.id                                       AS id_variedad,
    t.nombre                                   AS tipo,
    v.nombre                                   AS variedad,
    CONCAT(t.codigo, LPAD(v.id_interno,4,'0')) AS referencia,
    v.precio,
    v.precio_detalle,
    SUM(s.cantidad)                            AS cantidad,

    /* reservas pendientes: estados 0 y 1 */
    IFNULL((
      SELECT SUM(r.cantidad)
      FROM reservas_productos r
      WHERE r.id_variedad = v.id
        AND (r.estado = 0 OR r.estado = 1)
    ),0) AS cantidad_reservada,

    /* entregado: estado 2 (usando id_reserva_producto) */
    IFNULL((
      SELECT SUM(e.cantidad)
      FROM entregas_stock e
      JOIN reservas_productos r2 ON r2.id = e.id_reserva_producto
      WHERE r2.id_variedad = v.id
        AND r2.estado = 2
    ),0) AS cantidad_entregada,

    /* DISPONIBLE = cantidad - reservada(0,1) - entregada(2) */
    (
      SUM(s.cantidad)
      - IFNULL((
          SELECT SUM(r.cantidad)
          FROM reservas_productos r
          WHERE r.id_variedad = v.id
            AND (r.estado = 0 OR r.estado = 1)
        ),0)
      - IFNULL((
          SELECT SUM(e.cantidad)
          FROM entregas_stock e
          JOIN reservas_productos r2 ON r2.id = e.id_reserva_producto
          WHERE r2.id_variedad = v.id
            AND r2.estado = 2
        ),0)
    ) AS disponible_para_reservar

  FROM stock_productos s
  JOIN articulospedidos ap ON ap.id = s.id_artpedido
  JOIN variedades_producto v ON v.id = ap.id_variedad
  JOIN tipos_producto t     ON t.id = v.id_tipo
  WHERE ap.estado = 8
  GROUP BY
    v.id,
    t.nombre,
    v.nombre,
    v.id_interno,
    v.precio,
    v.precio_detalle
) AS sv
LEFT JOIN atributos_valores_variedades avv ON avv.id_variedad = sv.id_variedad
LEFT JOIN atributos_valores av           ON av.id = avv.id_atributo_valor
LEFT JOIN atributos a                    ON a.id = av.id_atributo
WHERE sv.disponible_para_reservar > 0
GROUP BY
  sv.id_variedad,
  sv.tipo,
  sv.variedad,
  sv.referencia,
  sv.precio,
  sv.precio_detalle,
  sv.disponible_para_reservar
ORDER BY sv.disponible_para_reservar DESC
LIMIT 100
";

$resultado = mysqli_query($link, $sql);
if (!$resultado) {
    die("Error SQL tabla detalle: " . mysqli_error($link));
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Listado de Plantines Disponibles Roelplant</title>
    
    
  <!-- Google Tag Manager -->
<script>(function(w,d,s,l,i){w[l]=w[l]||[];w[l].push({'gtm.start':
new Date().getTime(),event:'gtm.js'});var f=d.getElementsByTagName(s)[0],
j=d.createElement(s),dl=l!='dataLayer'?'&l='+l:'';j.async=true;j.src=
'https://www.googletagmanager.com/gtm.js?id='+i+dl;f.parentNode.insertBefore(j,f);
})(window,document,'script','dataLayer','GTM-PZ58VTFF');</script>
<!-- End Google Tag Manager -->

  <meta http-equiv="X-UA-Compatible" content="IE=edge">
  <meta name="generator" content="GrowMKTech">
  <meta name="viewport" content="width=device-width, initial-scale=1, minimum-scale=1">
  <link rel="shortcut icon" href="assets/images/favicon-128x128.png" type="image/x-icon">
  <link rel="canonical" href="https://roelplant.cl"/>

  <meta name="description" content="Plantines Ornamentales, Plantines de diferentes especies de interior y exterior, plantines de bajo consumo hídrico, plantas de cubre suelo con despacho a domicilio, plantinera y Vivero ubicado en Quillota. ">
  <meta name="author" content="Plantinera y Vivero Ornamental">
  <meta name="keywords" content="plantines, plantines por mayor, plantines al detalle, plantin, almacigos, vivero, esquejes, semillas, almaciguera quillota, plantas baratas, plantines ornamentales, plantines florales, plantines nativos, plantines rastreros, plantines perennes, bajo consumo hídrico, suculentas, fucsia, lavanda, osteospermum, rhus, plantinera, vivero de plantas, vivero quillota" />

  <!-- Open Graph metadata -->
  <meta property="og:title" content="Plantinera Ornamental | Propagación de plantas" />
  <meta property="og:description" content="Almacigos o plantines arbustivos, rastreros, plantas ornamentales, vivero de plantas y mucho más." />
  <meta property="og:url" content="https://roelplant.cl" />
  <meta property="og:image" content="https://roelplant.cl/assets/images/logo-roel-plant-610x417.png" />
  <meta property="og:site_name" content="Roelplant.cl" />
  <meta property="og:type" content="website" />
  
  <!-- Twitter Card metadata -->
  <meta name="twitter:card" content="summary_large_image">
  <meta name="twitter:title" content="Plantinera Ornamental | Propagación de plantas">
  <meta name="twitter:description" content="Almacigos o plantines arbustivos, rastreros, plantas ornamentales, vivero y mucho más.">
  <meta name="twitter:image" content="https://roelplant.cl/assets/images/logo-fondo-negroV2.png">
  
<script type="application/ld+json">
{
  "@context": "https://schema.org",
  "@type": "GardenStore", 
  "name": "Roelplant",
  "description": "Vivero especializado en plantas ornamentales y árboles nativos.",
  "url": "https://roelplant.cl",
  "logo": "https://roelplant.cl/assets/images/logo-blanco-266x153.png",
  "image": "https://roelplant.cl/assets/images/logo-blanco-266x153.png",
  "address": {
    "@type": "PostalAddress",
    "streetAddress": "Parcela 7, Camino la glorieta",
    "addressLocality": "Quillota",
    "addressRegion": "Valparaíso",
    "postalCode": "12345",
    "addressCountry": "CL"
  },
  "telephone": "+56-9-8422-6651",
  "priceRange": "$$",
  "contactPoint": {
    "@type": "ContactPoint",
    "telephone": "+56-9-8422-6651",
    "contactType": "Servicio de Ventas"
  }
}
</script>

<style>
.whatsapp-bubble {
  position: fixed;
  bottom: 20px;
  left: 20px;
  background-color: #25D366;
  border-radius: 50%;
  width: 60px;
  height: 60px;
  display: flex;
  align-items: center;
  justify-content: center;
  cursor: pointer;
  z-index: 9999;
  box-shadow: 0 4px 8px rgba(0,0,0,0.3);
  transition: transform 0.2s ease;
}
.whatsapp-bubble img {
  width: 35px;
  height: 35px;
}
.whatsapp-bubble:hover {
  transform: scale(1.05);
}
</style>

<!-- Burbuja WhatsApp -->
<div class="whatsapp-bubble"
     onclick="window.open('https://wa.me/56984226651?text=Estoy%20viendo%20el%20cat%C3%A1logo%20de%20stock%20semanal%20y%20me%20gustar%C3%ADa%20realizar%20un%20pedido','_blank')">
  <img src="https://upload.wikimedia.org/wikipedia/commons/6/6b/WhatsApp.svg" alt="WhatsApp">
</div>    
    
    
<style>
    body {
        font-family: Arial, sans-serif;
        background: #f5f7fa;
        margin: 0;
        padding: 20px;
    }
    h2 {
        text-align: center;
        color: #2c3e50;
        margin-bottom: 20px;
    }
    .tabla-contenedor {
        max-width: 95%;
        margin: auto;
        background: white;
        border-radius: 10px;
        overflow: hidden;
        box-shadow: 0 4px 15px rgba(0,0,0,0.1);
    }
    table {
        width: 100%;
        border-collapse: collapse;
        font-size: 14px;
    }
    thead {
        background: #2c3e50;
        color: white;
    }
    thead th {
        padding: 12px 10px;
        cursor: pointer;
        position: relative;
    }
    thead th:hover {
        background: #34495e;
    }
    tbody tr:nth-child(odd) { background: #f9f9f9; }
    tbody tr:hover { background: #eaf4ff; }
    td {
        padding: 10px;
        text-align: left;
        border-bottom: 1px solid #ddd;
    }
    .badge {
        background-color: #27ae60;
        color: white;
        padding: 3px 8px;
        border-radius: 8px;
        font-size: 12px;
    }
    .ver-catalogo {
        display: block;
        margin: 40px auto;
        background-color: #2980b9;
        color: white;
        padding: 15px 30px;
        font-size: 18px;
        border: none;
        border-radius: 8px;
        cursor: pointer;
        text-align: center;
    }
    .ver-catalogo:hover {
        background-color: #1f6690;
    }
</style>
<script>
    // Script básico para ordenar columnas
    function sortTable(n) {
        const table = document.getElementById("tablaCatalogo");
        let switching = true, dir = "asc", switchcount = 0;
        while (switching) {
            switching = false;
            const rows = table.rows;
            for (let i = 1; i < rows.length - 1; i++) {
                let shouldSwitch = false;
                const x = rows[i].getElementsByTagName("TD")[n];
                const y = rows[i + 1].getElementsByTagName("TD")[n];
                const cmpX = x.innerText.toLowerCase();
                const cmpY = y.innerText.toLowerCase();
                if (dir === "asc" && cmpX > cmpY) { shouldSwitch = true; break; }
                if (dir === "desc" && cmpX < cmpY) { shouldSwitch = true; break; }
            }
            if (shouldSwitch) {
                rows[i].parentNode.insertBefore(rows[i + 1], rows[i]);
                switching = true;
                switchcount++;
            } else if (switchcount === 0 && dir === "asc") {
                dir = "desc"; switching = true;
            }
        }
    }
</script>
</head>
<body>

<h2>Listado de Plantines Disponibles Roelplant</h2>

<div class="tabla-contenedor">
    <table id="tablaCatalogo">
        <thead>
            <tr>
                <th onclick="sortTable(0)">Variedad</th>
                <th onclick="sortTable(1)">Referencia</th>
                <th onclick="sortTable(2)">Tipo Planta</th>
                <th onclick="sortTable(3)">Atributos</th>
                <th onclick="sortTable(4)">Stock</th>
                <th onclick="sortTable(5)">Precio Imp. Incl.</th>
            </tr>
        </thead>
        <tbody>
            <?php
                $ivaMultiplier = get_iva_multiplier();
                while($row = mysqli_fetch_assoc($resultado)):
                $precio_detalle = isset($row['precio_detalle'])
                    ? number_format($row['precio_detalle'] * $ivaMultiplier, 0, ',', '.')
                    : '';

                // Procesar atributos
                $attrsRaw = trim((string)($row['attrs_activos'] ?? ''));
                $attrs = [];
                if($attrsRaw !== ''){
                  foreach(explode('||',$attrsRaw) as $kv){
                    $kv = trim((string)$kv);
                    if($kv !== '') $attrs[] = $kv;
                  }
                }
            ?>
            <tr>
                <td><?= htmlspecialchars($row['variedad']) ?></td>
                <td><?= htmlspecialchars($row['referencia']) ?></td>
                <td><span class="badge"><?= htmlspecialchars($row['tipo_planta'] ?? '—') ?></span></td>
                <td>
                  <?php if(!empty($attrs)): ?>
                    <small style="color:#666;"><?= htmlspecialchars(implode(' | ', array_slice($attrs, 0, 2))) ?></small>
                  <?php else: ?>
                    <small style="color:#999;">—</small>
                  <?php endif; ?>
                </td>
                <td><?= (int)$row['disponible_para_reservar'] ?></td>
                <td><?= $precio_detalle ? '$' . $precio_detalle : '-' ?></td>
            </tr>
            <?php endwhile; ?>
        </tbody>
    </table>
</div>

<!-- Botón para ver Catálogo -->
<button class="ver-catalogo" onclick="window.location.href='index.php'">
    Ver Catálogo
</button>

<!-- Nota Final -->
<div style="max-width: 900px; margin: 40px auto; padding: 20px; background-color: #fffbe6; border: 2px dashed #f39c12; border-radius: 10px; font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; color: #555;">
  <h3 style="color: #d35400;">📌 Nota Importante sobre los Pedidos</h3>
  <p><strong>🌿 Catálogo Detalle:</strong> Desde 5 plantines. Envíos disponibles a todo Chile con Starken y otros couriers. Todos los envíos deben ser previamente cancelados. <a href="https://roelplant.cl/terminos/" target="_blank">Revisa nuestros Términos y Condiciones</a>.</p>
  <p><strong>📦 Catálogo Mayorista:</strong> A partir de 100 plantines accedes al precio mayorista. Envíos a todo Chile a través de Starken y otros operadores logísticos. Todos los envíos deben ser previamente cancelados. Para pedidos de producción, el cliente debe respetar la fecha de retiro o recepción de los productos comprometidos. <a href="https://clientes.roelplant.cl/catalogo_mayorista/" target="_blank" rel="noopener">Ver Catálogo Mayorista</a></p>
</div>

<style>
.ig-bubble {
  position: fixed;
  bottom: 20px;
  right: 20px;
  background: linear-gradient(45deg, #f58529, #dd2a7b, #8134af, #515bd4);
  border-radius: 50%;
  width: 60px;
  height: 60px;
  display: flex;
  align-items: center;
  justify-content: center;
  cursor: pointer;
  z-index: 9999;
  box-shadow: 0 4px 8px rgba(0,0,0,0.3);
  transition: transform 0.2s ease;
}
.ig-bubble img {
  width: 30px;
  height: 30px;
}
.ig-bubble:hover {
  transform: scale(1.05);
}
</style>

<div class="ig-bubble" onclick="window.open('https://instagram.com/roelplant','_blank')">
  <img src="https://upload.wikimedia.org/wikipedia/commons/a/a5/Instagram_icon.png" alt="IG">
</div>

</body>
</html>
