<?php
// ===== No cache =====
header("Cache-Control: no-cache, no-store, must-revalidate");
header("Pragma: no-cache");
header("Expires: 0");

ini_set('display_errors',1);
ini_set('display_startup_errors',1);
error_reporting(E_ALL);

require __DIR__ . '/../class_lib/class_conecta_mysql.php';
$link = mysqli_connect($host,$user,$password,$dbname);
if(!$link) die("Error conexión: ".mysqli_connect_error());
mysqli_set_charset($link,'utf8');

/* -------- Query: IGUAL a la del mayorista, pero usada para catálogo detalle -------- */
$sql = "
SELECT
  sv.id_variedad,
  sv.tipo,
  sv.variedad,
  sv.referencia,
  sv.precio,
  sv.precio_detalle,
  sv.disponible_para_reservar,
  ANY_VALUE(av.valor)           AS tipo_planta,
  ANY_VALUE(img.nombre_archivo) AS imagen,
  ANY_VALUE(v.descripcion)      AS descripcion
FROM
(
  /* === RESUMEN POR VARIEDAD, ADAPTADO A LA NUEVA BD (id_reserva_producto) === */
  SELECT
    v.id                                           AS id_variedad,
    t.nombre                                       AS tipo,
    v.nombre                                       AS variedad,
    CONCAT(t.codigo, LPAD(v.id_interno,4,'0'))     AS referencia,
    v.precio,
    v.precio_detalle,
    SUM(s.cantidad)                                AS cantidad,

    /* reservas pendientes: estados 0 y 1 */
    IFNULL((
      SELECT SUM(r.cantidad)
      FROM reservas_productos r
      WHERE r.id_variedad = v.id
        AND (r.estado = 0 OR r.estado = 1)
    ),0) AS cantidad_reservada,

    /* entregado: estado 2, usando la nueva columna id_reserva_producto */
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
  GROUP BY v.id
) AS sv
JOIN variedades_producto v         ON v.id = sv.id_variedad
LEFT JOIN imagenes_variedades img  ON img.id_variedad = sv.id_variedad
LEFT JOIN atributos_valores_variedades avv ON avv.id_variedad = sv.id_variedad
LEFT JOIN atributos_valores av     ON av.id = avv.id_atributo_valor
LEFT JOIN atributos a              ON a.id = av.id_atributo AND a.nombre = 'TIPO DE PLANTA'

/* Solo productos con algo disponible para reservar */
WHERE sv.disponible_para_reservar > 0

GROUP BY sv.id_variedad
ORDER BY sv.disponible_para_reservar DESC
LIMIT 200
";

$r = mysqli_query($link,$sql);
if(!$r){
  die("Error SQL catálogo detalle: ".mysqli_error($link));
}

/* -------- Agrupar -------- */
$interior=[];$exterior=[];$cubre_suelos=[];$hierbas=[];$arboles=[];$packs_interior=[];$packs_exterior=[];$invitro_interior=[];$semillas=[];
while($row=mysqli_fetch_assoc($r)){
  $tipo=strtoupper(trim($row['tipo_planta']??''));
  switch($tipo){
    case 'PLANTAS DE INTERIOR':$interior[]=$row;break;
    case 'PLANTAS DE EXTERIOR':$exterior[]=$row;break;
    case 'CUBRE SUELOS':$cubre_suelos[]=$row;break;
    case 'HIERBAS':$hierbas[]=$row;break;
    case 'ÁRBOLES':case 'ARBOLES':$arboles[]=$row;break;
    case 'PACKS INTERIOR':$packs_interior[]=$row;break;
    case 'PACKS EXTERIOR':$packs_exterior[]=$row;break;
    case 'INVITRO INTERIOR':$invitro_interior[]=$row;break;
    case 'SEMILLAS':$semillas[]=$row;break;
  }
}

function truncar($t,$l=120){
  $t=trim((string)$t);
  if($t==='')return'';
  return function_exists('mb_strimwidth')
    ? mb_strimwidth($t,0,$l,'…','UTF-8')
    : (strlen($t)>$l?substr($t,0,$l-2).'…':$t);
}

/* -------- Constantes URL -------- */
$catalogoBase = 'https://clientes.roelplant.cl/catalogo_detalle/';
$scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
$baseUrl = $scheme.'://'.$_SERVER['HTTP_HOST'].$_SERVER['REQUEST_URI'];
$priceValidUntil = (new DateTime('+14 days'))->format('Y-m-d');

/* -------- Render Cards -------- */
function render_catalogo(array $ps,string $catalogoBase,string $priceValidUntil):void{
  foreach($ps as $p){
    $pd = isset($p['precio_detalle']) ? number_format(((float)$p['precio_detalle'])*1.19,0,',','.') : '';
    $pm = number_format(((float)$p['precio'])*1.19,0,',','.');
    $imgFile = !empty($p['imagen'])? htmlspecialchars($p['imagen'],ENT_QUOTES,'UTF-8') : '';
    $img = $imgFile ? "https://control.roelplant.cl/uploads/variedades/{$imgFile}" : "https://via.placeholder.com/600x400?text=Imagen+pendiente";
    $imgCb = $img.(strpos($img,'?')===false?'?v=':'&v=').time();

    $d  = trim($p['descripcion']??'');
    $dAttr  = htmlspecialchars($d,ENT_QUOTES,'UTF-8');
    $dSnip  = htmlspecialchars(truncar($d,120),ENT_QUOTES,'UTF-8');

    $refRaw = (string)$p['referencia'];
    $ref    = htmlspecialchars($refRaw,ENT_QUOTES,'UTF-8');
    $refUrl = rawurlencode($refRaw);

    $name  = htmlspecialchars($p['variedad'],ENT_QUOTES,'UTF-8');
    $stock = (int)$p['disponible_para_reservar'];
    $prodUrl = $catalogoBase.'?ref='.$refUrl;
    ?>
    <div class="producto" role="button" tabindex="0"
         aria-label="Ver detalle <?= $name ?>"
         data-nombre="<?= $name ?>"
         data-ref="<?= $ref ?>"
         data-stock="<?= $stock ?>"
         data-preciodetalle="<?= $pd ?>"
         data-preciomayorista="<?= $pm ?>"
         data-imagen="<?= $img ?>"
         data-descripcion="<?= $dAttr ?>"
         onclick="openProductoModal(this)"
         onkeypress="if(event.key==='Enter'){openProductoModal(this);}">

      <div class="img-wrap">
        <img src="<?= $imgCb ?>" alt="Imagen de producto" loading="lazy" decoding="async">
      </div>

      <div class="contenido">
        <h3 class="variedad"><?= $name ?></h3>
        <p><strong>Referencia:</strong> <?= $ref ?></p>
        <p><strong>Stock:</strong> <?= $stock ?></p>
        <?php if($pd):?><p><strong>Detalle:</strong> $<?= $pd ?> Imp. incl.</p><?php endif; ?>
        <?php if($dSnip):?><p class="descripcion-snippet"><?= $dSnip ?></p><?php endif; ?>
      </div>

      <div class="acciones">
        <button class="btn-detalle" onclick="event.stopPropagation(); openProductoModal(this.closest('.producto'));">Ver detalle</button>
        <button class="btn-reservar" onclick="event.stopPropagation(); window.location.href='https://clientes.roelplant.cl/';">Reservar</button>
      </div>

      <!-- JSON-LD por producto -->
      <script type="application/ld+json">
      {
        "@context":"https://schema.org",
        "@type":"Product",
        "name": <?= json_encode($name) ?>,
        "image": [<?= json_encode($img) ?>],
        "sku": <?= json_encode($refRaw) ?>,
        "brand": {"@type":"Brand","name":"Roelplant"},
        "category": <?= json_encode($p['tipo'] ?? "PLANTINES INTERIOR") ?>,
        "description": <?= json_encode($d ?: $name) ?>,
        "url": <?= json_encode($prodUrl) ?>,
        "offers":{
          "@type":"Offer",
          "url": <?= json_encode($prodUrl) ?>,
          "priceCurrency":"CLP",
          "price": <?= json_encode($pd ?: $pm) ?>,
          "availability": "https://schema.org/<?= $stock>0?'InStock':'OutOfStock' ?>",
          "itemCondition":"https://schema.org/NewCondition",
          "priceValidUntil": <?= json_encode($priceValidUntil) ?>,
          "seller":{"@type":"Organization","name":"Roelplant"}
        }
      }
      </script>
    </div>
  <?php }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
<!-- Google tag (gtag.js) -->
<script async src="https://www.googletagmanager.com/gtag/js?id=G-B13EZZR4R7"></script>
<script>
  window.dataLayer = window.dataLayer || [];
  function gtag(){dataLayer.push(arguments);}
  gtag('js', new Date());
  gtag('config', 'G-B13EZZR4R7');
</script>

<meta charset="UTF-8">
<meta http-equiv="X-UA-Compatible" content="IE=edge">
<meta name="generator" content="Roelplant">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta http-equiv="Cache-Control" content="no-cache, no-store, must-revalidate">
<meta http-equiv="Pragma" content="no-cache">
<meta http-equiv="Expires" content="0">

<link rel="shortcut icon" href="https://roelplant.cl/assets/images/favicon-128x128.png?v=<?php echo time(); ?>" type="image/x-icon">
<link rel="icon" href="/favicon.ico">
<link rel="icon" type="image/png" sizes="32x32" href="https://roelplant.cl/assets/images/favicon-128x128.png">
<link rel="apple-touch-icon" sizes="180x180" href="https://roelplant.cl/assets/images/favicon-128x128.png">

<link rel="canonical" href="https://clientes.roelplant.cl/catalogo_detalle/">

<title>Catálogo de Plantines al Detalle | Roelplant</title>
<meta name="description" content="Plantines ornamentales al detalle: interior, exterior, cubresuelos y más. Despacho a todo Chile. Compra mínima 5 plantines. Vivero y plantinera en Quillota.">
<meta name="author" content="Roelplant">
<meta name="keywords" content="plantines, plantines al detalle, vivero, plantinera, semillas, plantas interior, plantas exterior, cubresuelos, suculentas, lavanda, monstera, philodendron, quillota, chile">
<meta name="robots" content="index,follow,max-snippet:-1,max-image-preview:large,max-video-preview:-1">

<!-- Open Graph -->
<meta property="og:type" content="website">
<meta property="og:title" content="Catálogo de Plantines al Detalle | Roelplant">
<meta property="og:description" content="Plantines ornamentales al detalle con envío a todo Chile. Interior, exterior y más.">
<meta property="og:url" content="https://clientes.roelplant.cl/catalogo_detalle/">
<meta property="og:site_name" content="Roelplant">
<meta property="og:image" content="https://roelplant.cl/assets/images/logo-fondo-negroV2.png">

<!-- Twitter -->
<meta name="twitter:card" content="summary_large_image">
<meta name="twitter:title" content="Catálogo de Plantines al Detalle | Roelplant">
<meta name="twitter:description" content="Plantines ornamentales al detalle con despacho a todo Chile.">
<meta name="twitter:image" content="https://roelplant.cl/assets/images/logo-fondo-negroV2.png">

<!-- JSON-LD (Organization + WebSite) -->
<script type="application/ld+json">
{
  "@context":"https://schema.org",
  "@type":"Organization",
  "name":"Roelplant",
  "url":"https://roelplant.cl/",
  "logo":"https://roelplant.cl/assets/images/logo-fondo-negroV2.png",
  "sameAs":[
    "https://www.instagram.com/roelplant"
  ]
}
</script>
<script type="application/ld+json">
{
  "@context":"https://schema.org",
  "@type":"WebSite",
  "name":"Roelplant",
  "url":"https://clientes.roelplant.cl/catalogo_detalle/",
  "potentialAction":{
    "@type":"SearchAction",
    "target":"https://clientes.roelplant.cl/catalogo_detalle/?s={search_term_string}",
    "query-input":"required name=search_term_string"
  }
}
</script>

<?php
$all = array_merge(
  $interior,$exterior,$cubre_suelos,$hierbas,$arboles,
  $packs_interior,$packs_exterior,$invitro_interior,$semillas
);
$items = [];
$pos=1;
foreach($all as $p){
  $refRaw = (string)$p['referencia'];
  $refUrl = rawurlencode($refRaw);
  $name   = htmlspecialchars($p['variedad'],ENT_QUOTES,'UTF-8');
  $prodUrl= $catalogoBase.'?ref='.$refUrl;
  $items[] = [
    "@type"=>"ListItem",
    "position"=>$pos++,
    "url"=>$prodUrl,
    "name"=>$name
  ];
}
?>
<script type="application/ld+json">
{
  "@context":"https://schema.org",
  "@type":"ItemList",
  "name":"Catálogo Detalle Roelplant",
  "itemListElement": <?= json_encode($items, JSON_UNESCAPED_UNICODE) ?>
}
</script>

<style>
:root{--brand:#27ae60;--ink:#2c3e50;--menu:#1f7a4e}
*{box-sizing:border-box}
body{font-family:Segoe UI,Tahoma,Geneva,Verdana,sans-serif;background:#f4f4f4;padding:20px}
h1{text-align:center;color:var(--ink);font-size:2.2em;margin:12px 0 30px}
h2{text-align:center;color:var(--ink);margin:40px 0 20px;scroll-margin-top:96px}

/* Grid */
.catalogo{display:grid;grid-template-columns:repeat(auto-fit,minmax(260px,1fr));gap:20px;max-width:1400px;margin:0 auto}
@media (min-width:1200px){.catalogo{grid-template-columns:repeat(4,minmax(0,1fr))}}

/* Card flex */
.producto{display:flex;flex-direction:column;background:#fff;border-radius:14px;box-shadow:0 8px 20px rgba(0,0,0,.08);padding:16px;text-align:center;transition:transform .18s ease,box-shadow .18s ease;border-top:5px solid var(--brand);cursor:pointer;min-height:420px;overflow:hidden}
.producto:hover{transform:translateY(-2px);box-shadow:0 10px 28px rgba(0,0,0,.12)}
.img-wrap{background:#f7f7f7;border-radius:10px;overflow:hidden;padding:6px;margin-bottom:12px}
.producto img{width:100%;height:auto;aspect-ratio:4/3;object-fit:contain;display:block}
.contenido{display:flex;flex-direction:column;gap:6px}
.variedad{text-transform:uppercase;font-weight:800;color:var(--brand);margin:4px 0 6px;font-size:1.06em;line-height:1.2;min-height:2.6em}
.producto p{color:#555;margin:0}
.descripcion-snippet{font-size:.95em;color:#3e3e3e}

/* Acciones */
.acciones{margin-top:auto;display:flex;gap:12px;justify-content:space-between;padding:12px 10px 0;flex-wrap:wrap}
.btn-detalle,.btn-reservar{background:var(--brand);color:#fff;padding:10px 14px;border:0;border-radius:10px;cursor:pointer;min-width:120px;height:40px;display:inline-flex;align-items:center;justify-content:center}
.btn-detalle{background:#2d6fb6}.btn-detalle:hover{background:#1f6690}.btn-reservar:hover{background:#1e8a4c}
@media (min-width:981px){
  .btn-detalle,.btn-reservar{
    flex:0 0 46%;max-width:46%;min-width:auto;height:36px;padding:8px 0;font-size:14px
  }
}

/* Ver listado */
.ver-listado{display:block;width:fit-content;margin:32px auto 8px;padding:12px 20px;background:#2d6fb6;color:#fff;border:0;border-radius:12px;font-weight:700;cursor:pointer;box-shadow:0 6px 16px rgba(0,0,0,.12)}
.ver-listado:hover{background:#1f6690}

/* Modal */
.modal{position:fixed;inset:0;background:rgba(0,0,0,.55);display:none;align-items:center;justify-content:center;z-index:10000;padding:20px}
.modal.open{display:flex}
.modal-content{background:#fff;border-radius:14px;max-width:920px;width:100%;padding:20px;position:relative;display:grid;grid-template-columns:1fr 1.2fr;gap:20px}
.modal-close{position:absolute;top:8px;right:10px;font-size:28px;line-height:28px;border:0;background:transparent;cursor:pointer}
.modal img{width:100%;border-radius:10px;object-fit:contain;aspect-ratio:4/3;background:#f7f7f7;padding:6px}
.modal h3{margin:0 0 6px;color:#2c3e50}.modal p{margin:6px 0;color:#333}
.modal .modal-desc{white-space:pre-wrap;border-top:1px dashed #ddd;margin-top:10px;padding-top:10px;color:#444}
.modal .acciones-modal{margin-top:12px;display:flex;gap:8px}
@media (max-width:720px){
  .modal{padding:12px}
  .modal-content{grid-template-columns:1fr;width:92vw;max-height:88vh;overflow:auto;padding:16px;gap:12px}
  .modal img{aspect-ratio:1/1;max-height:36vh;padding:4px}
  .modal .acciones-modal{
    position:sticky;bottom:0;background:#fff;padding-top:8px;margin-top:12px;
    box-shadow:0 -6px 12px rgba(0,0,0,.06);gap:10px;flex-direction:column
  }
  .modal .acciones-modal .btn-reservar,
  .modal .acciones-modal .btn-detalle{width:100%}
}

/* Burbujas */
.whatsapp-bubble,.ig-bubble{
  position:fixed;bottom:20px;width:56px;height:56px;display:flex;
  align-items:center;justify-content:center;border-radius:50%;cursor:pointer;
  z-index:9000;box-shadow:0 4px 8px rgba(0,0,0,.3)
}
.whatsapp-bubble{background:#25D366;left:20px}
.whatsapp-bubble img{width:32px;height:32px}
.ig-bubble{
  right:20px;
  background:linear-gradient(45deg,#f58529,#dd2a7b,#8134af,#515bd4)
}
.ig-bubble img{width:28px;height:28px}
@media (max-width:980px){.whatsapp-bubble{bottom:84px;left:20px}}
@media (min-width:981px){
  .whatsapp-bubble{left:auto;right:20px;bottom:20px}
  .ig-bubble{right:20px;bottom:90px}
}

/* Menú lateral + móvil */
.tech-menu{
  position:fixed;top:88px;left:20px;width:260px;background:#fff;
  border:1px solid #e5e7eb;border-radius:12px;box-shadow:0 10px 24px rgba(0,0,0,.08);
  padding:12px;z-index:9500
}
.tech-menu h3{margin:6px 8px 8px;font-size:16px;color:var(--ink)}
.tech-menu ul{list-style:none;margin:0;padding:0;max-height:70vh;overflow:auto}
.tech-menu a{
  display:block;padding:10px 12px;text-decoration:none;color:#334155;border-radius:10px
}
.tech-menu a.active,.tech-menu a:hover{background:#eaf7f0;color:var(--menu)}
.tech-menu .divider{border-top:1px solid #eee;margin:8px 0}
body{padding-left:300px}
@media (max-width:980px){
  body{padding-left:0}
  h2{scroll-margin-top:76px}
  .catalogo{grid-template-columns:repeat(auto-fit,minmax(180px,1fr))}
  .menu-toggle{
    position:sticky;top:8px;z-index:9600;margin:0 auto 10px;display:flex;
    gap:8px;align-items:center;justify-content:center;background:#1f7a4e;
    color:#fff;border:0;border-radius:999px;padding:10px 16px;font-weight:600;
    box-shadow:0 6px 16px rgba(0,0,0,.12)
  }
  .menu-toggle svg{width:18px;height:18px}
  .tech-menu{
    position:fixed;left:12px;right:12px;top:64px;width:auto;margin:0;
    border-radius:14px;transform:translateY(-130%);transition:transform .25s ease;
    max-height:72vh;overflow:auto
  }
  .tech-menu.open{transform:translateY(0)}
}
@media (prefers-reduced-motion:reduce){
  *{transition:none!important;scroll-behavior:auto!important}
}
</style>
</head>
<body>
<h1>Catálogo Al detalle Roelplant</h1>

<button class="menu-toggle" id="menuToggle" aria-controls="techMenu" aria-expanded="false" style="display:none">
  <svg viewBox="0 0 24 24" fill="currentColor" aria-hidden="true">
    <path d="M3 6h18v2H3V6zm4 5h10v2H7v-2zm-2 5h14v2H5v-2z"/>
  </svg>
  Filtros
</button>

<div class="whatsapp-bubble" onclick="window.open('https://wa.me/56933217944?text=Estoy%20viendo%20el%20cat%C3%A1logo%20de%20stock%20semanal%20y%20me%20gustar%C3%ADa%20realizar%20un%20pedido','_blank')">
  <img src="https://upload.wikimedia.org/wikipedia/commons/6/6b/WhatsApp.svg" alt="WhatsApp">
</div>

<?php
$secciones=[
  'interior'=>['Catálogo de Plantines Disponibles de Interior',$interior],
  'exterior'=>['Catálogo de Plantines Disponibles de Exterior',$exterior],
  'cubre-suelos'=>['Catálogo de Cubre Suelos',$cubre_suelos],
  'hierbas'=>['Catálogo de Hierbas',$hierbas],
  'arboles'=>['Catálogo de Árboles',$arboles],
  'packs-interior'=>['Packs Interior',$packs_interior],
  'packs-exterior'=>['Packs Exterior',$packs_exterior],
  'invitro-interior'=>['In-Vitro Interior',$invitro_interior],
  'semillas'=>['Semillas',$semillas],
];
?>
<nav class="tech-menu" id="techMenu" aria-label="Filtrar por tipo">
  <h3>Tipos</h3>
  <ul>
    <?php foreach($secciones as $id=>$sec){ if(count($sec[1])===0) continue; ?>
      <li><a href="#<?= $id ?>"><?= htmlspecialchars($sec[0],ENT_QUOTES,'UTF-8') ?></a></li>
    <?php } ?>
    <li class="divider"></li>
    <li><a href="#top">↑ Arriba</a></li>
  </ul>
</nav>
<a id="top"></a>

<?php foreach($secciones as $id=>$sec){ if(count($sec[1])===0) continue; ?>
  <h2 id="<?= $id ?>"><?= htmlspecialchars($sec[0],ENT_QUOTES,'UTF-8') ?></h2>
  <div class="catalogo"><?php render_catalogo($sec[1],$catalogoBase,$priceValidUntil); ?></div>
<?php } ?>

<button class="ver-listado" onclick="window.location.href='catalogo_tabla.php?v=<?php echo time(); ?>'">Ver listado</button>

<!-- Nota Final -->
<div style="max-width: 900px; margin: 24px auto 40px; padding: 20px; background-color: #fffbe6; border: 2px dashed #f39c12; border-radius: 10px; color: #555;">
  <h3 style="color: #d35400; margin: 0 0 10px;">📌 Nota Importante sobre los Pedidos</h3>

  <p><strong>🌿 Catálogo Detalle:</strong> Desde 5 plantines. Envíos disponibles a todo Chile con Starken y otros couriers. Todos los envíos deben ser previamente cancelados.
    <a href="https://roelplant.cl/terminos/" target="_blank" rel="noopener">Revisa nuestros Términos y Condiciones</a>.
  </p>

  <p><strong>📦 Catálogo Mayorista:</strong> A partir de 100 plantines accedes al precio mayorista. Envíos a todo Chile con Starken y otros operadores logísticos. Todos los envíos deben ser previamente cancelados.
    Para pedidos de producción, el cliente debe respetar la fecha de retiro o recepción comprometida.
    <a href="https://clientes.roelplant.cl/catalogo_mayorista/" target="_blank" rel="noopener">Ver Catálogo Mayorista</a>.
  </p>

  <hr style="border:0;border-top:1px dashed #f39c12; margin:16px 0">

  <h4 style="color:#b24c00; margin: 12px 0 6px;">🧪 Raíz en plantines: control de calidad y garantía</h4>
  <ul style="margin:0 0 10px 18px; padding:0;">
    <li><strong>Control de salida:</strong> Todos los plantines se revisan; deben presentar raíz funcional (blanca o parda sana). Algunas especies tienen raíces finas o cortas por fisiología.</li>
    <li><strong>Recepción y evidencia:</strong> Al recibir, abre de inmediato. Registra <u>video/unboxing</u> y fotos del sistema radicular y etiqueta del envío.</li>
    <li><strong>Garantía DOA 24 h:</strong> Si un plantín llega <em>sin raíz viable</em>, reporta dentro de 24 horas desde la entrega con evidencia. Opciones: <u>reposición</u> en la próxima salida o <u>nota de crédito</u> por la unidad observada.</li>
    <li><strong>Exclusiones:</strong> Daños por apertura tardía, riego inapropiado, estrés térmico del destinatario, o manipulación posterior al arribo.</li>
  </ul>

  <h4 style="color:#b24c00; margin: 12px 0 6px;">🧫 Plantines in vitro: tamaño y etapa de aclimatación</h4>
  <ul style="margin:0 0 10px 18px; padding:0;">
    <li><strong>Tamaño comercial:</strong> In vitro se entregan típicamente entre <strong>2–6 cm</strong> según especie y lote. Son <em>etapa juvenil</em> y requieren aclimatación.</li>
    <li><strong>Aclimatación recomendada (2–4 semanas):</strong> Sombra 50–60%, riego suave/nebulizado, sustrato aireado. Sin fertilizar la primera semana; luego dosis bajas.</li>
    <li><strong>Alternativas de tamaño:</strong> Si necesitas mayor tamaño, podemos programar <em>precrecimiento</em> bajo pedido. <strong>Lead time estimado: 6–10 semanas</strong> sujeto a especie y cupo.</li>
  </ul>

  <h4 style="color:#b24c00; margin: 12px 0 6px;">📷 Procedimiento de recepción</h4>
  <ol style="margin:0 0 10px 18px; padding:0;">
    <li>Abrir cajas al recibir. No dejar en vehículo o sol directo.</li>
    <li>Registrar video/fotos de estado general y etiquetas.</li>
    <li>Hidratar y estabilizar a sombra liviana.</li>
    <li>Reportar incidencias con evidencia dentro de <strong>24 h</strong>.</li>
  </ol>

  <p style="margin:10px 0 0 0; font-size: 13px; color:#6a5a00;">
    <strong>Nota técnica:</strong> Tamaño y masa radicular pueden variar por especie, temporada y lote. El enraizamiento continúa y se expande tras el trasplante si se siguen las recomendaciones de aclimatación.
  </p>
</div>

<div class="ig-bubble" onclick="window.open('https://instagram.com/roelplant','_blank')">
  <img src="https://upload.wikimedia.org/wikipedia/commons/a/a5/Instagram_icon.png" alt="IG">
</div>

<div id="productoModal" class="modal" aria-hidden="true">
  <div class="modal-content" role="dialog" aria-modal="true" aria-labelledby="modalTitle">
    <button class="modal-close" onclick="closeProductoModal()" aria-label="Cerrar">×</button>
    <img id="mImagen" src="" alt="Imagen del producto">
    <div>
      <h3 id="modalTitle"></h3>
      <p id="mRef"></p>
      <p id="mStock"></p>
      <p id="mPrecioDetalle" style="display:none"></p>
      <p id="mPrecioMayorista"></p>
      <div id="mDesc" class="modal-desc"></div>
      <div class="acciones-modal">
        <button class="btn-reservar" onclick="window.location.href='https://clientes.roelplant.cl/';">Reservar</button>
        <button class="btn-detalle" onclick="closeProductoModal()">Cerrar</button>
      </div>
    </div>
  </div>
</div>

<script>
const mq=window.matchMedia('(max-width:980px)');
const btn=document.getElementById('menuToggle');
const menu=document.getElementById('techMenu');
function upd(){btn.style.display=mq.matches?'flex':'none'}
(mq.addEventListener?mq.addEventListener('change',upd):mq.addListener(upd));upd();
btn?.addEventListener('click',()=>{
  const open=menu.classList.toggle('open');
  btn.setAttribute('aria-expanded',open?'true':'false')
});

document.querySelectorAll('.tech-menu a[href^="#"]').forEach(a=>{
  a.addEventListener('click',e=>{
    const t=document.querySelector(a.getAttribute('href'));
    if(t){
      e.preventDefault();
      if(menu.classList.contains('open'))menu.classList.remove('open');
      t.scrollIntoView({behavior:'smooth',block:'start'})
    }
  });
});

const links2=[...document.querySelectorAll('.tech-menu a[href^="#"]')];
const secs=links2.map(l=>document.querySelector(l.getAttribute('href'))).filter(Boolean);
const io=new IntersectionObserver(es=>{
  es.forEach(en=>{
    if(en.isIntersecting){
      links2.forEach(l=>l.classList.toggle('active',l.getAttribute('href')===('#'+en.target.id)))
    }
  })
},{rootMargin:'-55% 0px -40% 0px',threshold:0});
secs.forEach(s=>io.observe(s));

function openProductoModal(card){
  const m=document.getElementById('productoModal');
  document.getElementById('mImagen').src=card.dataset.imagen||'';
  document.getElementById('modalTitle').textContent=card.dataset.nombre||'';
  document.getElementById('mRef').textContent='Referencia: '+(card.dataset.ref||'');
  document.getElementById('mStock').textContent='Stock disponible: '+(card.dataset.stock||'');
  const pd=card.dataset.preciodetalle||'';
  const pm=card.dataset.preciomayorista||'';
  const pdEl=document.getElementById('mPrecioDetalle');
  const pmEl=document.getElementById('mPrecioMayorista');
  if(pd){
    pdEl.textContent='Precio detalle: $'+pd+' Imp. incl.';
    pdEl.style.display='block';
  }else{
    pdEl.style.display='none';
  }
  pmEl.textContent=pm?('Precio mayorista: $'+pm+' Imp. incl.'):'';  
  document.getElementById('mDesc').textContent=card.dataset.descripcion||'Sin descripción';
  m.classList.add('open');
  m.setAttribute('aria-hidden','false');
  document.body.style.overflow='hidden';
}
function closeProductoModal(){
  const m=document.getElementById('productoModal');
  m.classList.remove('open');
  m.setAttribute('aria-hidden','true');
  document.body.style.overflow='';
}
document.getElementById('productoModal').addEventListener('click',e=>{
  if(e.target===e.currentTarget)closeProductoModal();
});
document.addEventListener('keydown',e=>{
  if(e.key==='Escape')closeProductoModal();
});

(function(){
  const params=new URLSearchParams(location.search);
  const ref=params.get('ref');
  if(!ref) return;
  if(typeof CSS==='undefined' || typeof CSS.escape!=='function'){
    window.CSS = window.CSS || {};
    CSS.escape = function(s){return (s+'').replace(/"/g,'\\"').replace(/'/g,"\\'");};
  }
  const card=document.querySelector('.producto[data-ref="'+CSS.escape(ref)+'"]');
  if(card){
    openProductoModal(card);
    card.scrollIntoView({behavior:'smooth',block:'center'});
  }
})();
</script>
</body>
</html>
