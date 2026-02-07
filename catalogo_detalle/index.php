<?php
// catalogo_detalle/index.php
// ===== No cache =====
header("Cache-Control: no-cache, no-store, must-revalidate");
header("Pragma: no-cache");
header("Expires: 0");

ini_set('display_errors',1);
ini_set('display_startup_errors',1);
error_reporting(E_ALL);

// BD STOCK (NO SE MODIFICA)
$conectaPaths = [
  __DIR__ . '/../class_lib/class_conecta_mysql.php',
  __DIR__ . '/../../class_lib/class_conecta_mysql.php',
  __DIR__ . '/../../../class_lib/class_conecta_mysql.php',
];
$found = false;
foreach ($conectaPaths as $p) {
  if (is_file($p)) { require $p; $found = true; break; }
}
if (!$found) {
  http_response_code(500);
  die("No se encontró class_lib/class_conecta_mysql.php. Revisa la ruta relativa.");
}
$link = mysqli_connect($host,$user,$password,$dbname);
if(!$link) die("Error conexión: ".mysqli_connect_error());
mysqli_set_charset($link,'utf8');

// Cargar .env para IVA
$envPaths = [
  __DIR__ . '/../.env',
  __DIR__ . '/../../.env',
];
foreach ($envPaths as $envPath) {
  if (is_file($envPath)) {
    $lines = file($envPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
      if (strpos(trim($line), '#') === 0) continue;
      if (strpos($line, '=') !== false) {
        [$key, $value] = explode('=', $line, 2);
        $key = trim($key);
        $value = trim($value);
        if ((strpos($value, '"') === 0 && strrpos($value, '"') === strlen($value) - 1) ||
            (strpos($value, "'") === 0 && strrpos($value, "'") === strlen($value) - 1)) {
          $value = substr($value, 1, -1);
        }
        if (!getenv($key)) {
          putenv("{$key}={$value}");
        }
      }
    }
    break;
  }
}

// Función para obtener porcentaje de IVA
function get_iva_percentage(): int {
  $iva = (int)(getenv('IVA_PERCENTAGE') ?: 19);
  return $iva > 0 ? $iva : 19;
}

// Función para obtener multiplicador de IVA
function get_iva_multiplier(): float {
  return 1 + (get_iva_percentage() / 100);
}

/* ====== CONFIG OGIMG (cambia este secreto por uno largo y aleatorio) ====== */
const OGIMG_SECRET = 'CAMBIA-ESTO-POR-UN-SECRETO-LARGO-Y-ALEATORIO-32+CHARS';

/* -------- Query: catálogo detalle (solo lectura a tu BD stock) --------
   FIX: Obtiene atributos de forma correcta:
   - tipo_planta se obtiene con MAX(CASE) para evitar confusiones
   - attrs_activos agrupa otros atributos sin TIPO DE PLANTA
   - Permite búsqueda y filtrado por atributos
-------- */
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
  ) AS attrs_activos,

  MIN(img.nombre_archivo) AS imagen,
  MIN(v.descripcion)      AS descripcion
FROM
(
  SELECT
    v.id                                           AS id_variedad,
    t.nombre                                       AS tipo,
    v.nombre                                       AS variedad,
    CONCAT(t.codigo, LPAD(v.id_interno,4,'0'))     AS referencia,
    v.precio,
    v.precio_detalle,
    SUM(s.cantidad)                                AS cantidad,

    IFNULL((
      SELECT SUM(r.cantidad)
      FROM reservas_productos r
      WHERE r.id_variedad = v.id
        AND (r.estado = 0 OR r.estado = 1)
    ),0) AS cantidad_reservada,

    IFNULL((
      SELECT SUM(e.cantidad)
      FROM entregas_stock e
      JOIN reservas_productos r2 ON r2.id = e.id_reserva_producto
      WHERE r2.id_variedad = v.id
        AND r2.estado = 2
    ),0) AS cantidad_entregada,

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
JOIN variedades_producto v              ON v.id = sv.id_variedad
LEFT JOIN imagenes_variedades img       ON img.id_variedad = sv.id_variedad
LEFT JOIN atributos_valores_variedades avv ON avv.id_variedad = sv.id_variedad
LEFT JOIN atributos_valores av          ON av.id = avv.id_atributo_valor
LEFT JOIN atributos a                   ON a.id = av.id_atributo
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

function _pretty_attr_label(string $k): string {
  $k = trim($k);
  if ($k === '') return $k;
  if (function_exists('mb_strtolower') && function_exists('mb_convert_case')) {
    $k = mb_strtolower($k, 'UTF-8');
    return mb_convert_case($k, MB_CASE_TITLE, 'UTF-8');
  }
  return ucwords(strtolower($k));
}

/* -------- Constantes URL -------- */
$catalogoBase = 'https://clientes.roelplant.cl/catalogo_detalle_webpay/';
$scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
$baseUrl = $scheme.'://'.$_SERVER['HTTP_HOST'].$_SERVER['REQUEST_URI'];
$priceValidUntil = (new DateTime('+14 days'))->format('Y-m-d');

/* -------- OpenGraph dinámico por ?ref= (con proxy ogimg.php) -------- */
$refParam = isset($_GET['ref']) ? trim((string)$_GET['ref']) : '';
$ogTitle = 'Catálogo de Plantines al Detalle | Roelplant';
$ogDesc  = 'Plantines ornamentales al detalle con envío a todo Chile. Interior, exterior y más.';
$ogUrl   = $catalogoBase;
$canonicalUrl = $catalogoBase;
$ogImage = 'https://roelplant.cl/assets/images/logo-fondo-negroV2.png';

if($refParam !== ''){
  $refParamClean = $refParam;
  $refParamUrl   = rawurlencode($refParamClean);
  $ogUrl = $catalogoBase.'?ref='.$refParamUrl;
  $canonicalUrl = $ogUrl;

  $allForRef = array_merge(
    $interior,$exterior,$cubre_suelos,$hierbas,$arboles,
    $packs_interior,$packs_exterior,$invitro_interior,$semillas
  );
  $found = null;
  foreach($allForRef as $pp){
    if((string)$pp['referencia'] === $refParamClean){ $found = $pp; break; }
  }

  if($found){
    $ivaMultiplier = get_iva_multiplier();
    $nameOg  = (string)($found['variedad'] ?? '');
    $stockOg = (int)($found['disponible_para_reservar'] ?? 0);
    $pdOg = isset($found['precio_detalle']) ? number_format(((float)$found['precio_detalle'])*$ivaMultiplier,0,',','.') : '';
    $pmOg = number_format(((float)($found['precio'] ?? 0))*$ivaMultiplier,0,',','.');
    $precioVisibleOg = $pdOg ? ('$'.$pdOg.' (Detalle, imp. incl.)') : ('$'.$pmOg.' (Mayorista, imp. incl.)');
    $descOg = trim((string)($found['descripcion'] ?? ''));
    $descOg = $descOg ? truncar($descOg, 140) : '';

    $ogTitle = ($nameOg !== '' ? ($nameOg.' | Roelplant') : $ogTitle);

    $parts = [];
    if($precioVisibleOg) $parts[] = 'Precio: '.$precioVisibleOg;
    $parts[] = 'Stock: '.$stockOg;
    $parts[] = 'Ref: '.$refParamClean;
    if($descOg) $parts[] = $descOg;
    $ogDesc = truncar(implode(' · ', $parts), 240);

    $sig = hash_hmac('sha256', $refParamClean, OGIMG_SECRET);
    $ogImage = 'https://clientes.roelplant.cl/ogimg.php?ref='.$refParamUrl.'&sig='.$sig;
  }
}

$ogTitleH = htmlspecialchars($ogTitle, ENT_QUOTES, 'UTF-8');
$ogDescH  = htmlspecialchars($ogDesc, ENT_QUOTES, 'UTF-8');
$ogUrlH   = htmlspecialchars($ogUrl, ENT_QUOTES, 'UTF-8');
$canonicalH = htmlspecialchars($canonicalUrl, ENT_QUOTES, 'UTF-8');
$ogImageH = htmlspecialchars($ogImage, ENT_QUOTES, 'UTF-8');

/* -------- Render Cards -------- */
function render_catalogo(array $ps,string $catalogoBase,string $priceValidUntil):void{
  $ivaMultiplier = get_iva_multiplier();
  foreach($ps as $p){
    $pdNum = isset($p['precio_detalle']) ? (float)$p['precio_detalle'] : 0;
    $pmNum = isset($p['precio']) ? (float)$p['precio'] : 0;

    $pd = $pdNum>0 ? number_format($pdNum*$ivaMultiplier,0,',','.') : '';
    $pm = number_format($pmNum*$ivaMultiplier,0,',','.');

    $unitPriceClpInt = (int)round(($pdNum>0?$pdNum:$pmNum)*$ivaMultiplier);

    // Procesar atributos
    $attrsRaw = trim((string)($p['attrs_activos'] ?? ''));
    $attrs = [];
    if($attrsRaw !== ''){
      foreach(explode('||',$attrsRaw) as $kv){
        $kv = trim((string)$kv);
        if($kv !== '') $attrs[] = $kv;
      }
    }
    $attrsDataAttr = htmlspecialchars($attrsRaw, ENT_QUOTES, 'UTF-8');

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
    $idVar = (int)$p['id_variedad'];

    $prodUrl = $catalogoBase.'?ref='.$refUrl;
    $prodUrlAttr = htmlspecialchars($prodUrl, ENT_QUOTES, 'UTF-8');

    $precioVisibleTxt = $pd ? ('$'.$pd.' (Detalle, imp. incl.)') : ('$'.$pm.' (Mayorista, imp. incl.)');
    $precioVisibleAttr = htmlspecialchars($precioVisibleTxt, ENT_QUOTES, 'UTF-8');
    ?>
    <div class="producto" role="button" tabindex="0"
         aria-label="Ver detalle <?= $name ?>"
         data-idvariedad="<?= (int)$idVar ?>"
         data-nombre="<?= $name ?>"
         data-ref="<?= $ref ?>"
         data-stock="<?= $stock ?>"
         data-preciodetalle="<?= $pd ?>"
         data-preciomayorista="<?= $pm ?>"
         data-attrs="<?= $attrsDataAttr ?>"
         data-unitpriceclp="<?= (int)$unitPriceClpInt ?>"
         data-preciovisible="<?= $precioVisibleAttr ?>"
         data-imagen="<?= htmlspecialchars($img,ENT_QUOTES,'UTF-8') ?>"
         data-url="<?= $prodUrlAttr ?>"
         data-descripcion="<?= $dAttr ?>"
         onclick="openProductoModal(this)"
         onkeypress="if(event.key==='Enter'){openProductoModal(this);}">

      <div class="img-wrap">
        <img src="<?= $imgCb ?>" alt="Imagen de producto" loading="lazy" decoding="async">
      </div>

      <div class="contenido">
        <h3 class="variedad"><?= $name ?></h3>
        <p><strong>Referencia:</strong> <?= $ref ?></p>
        <p><strong>Stock disponible:</strong> <?= $stock ?></p>

        <?php if(!empty($attrs)): ?>
          <div class="attrs">
            <?php foreach($attrs as $kv):
              $parts = explode(':', $kv, 2);
              $k = trim((string)($parts[0] ?? ''));
              $v = trim((string)($parts[1] ?? ''));
              if($k==='' || $v==='') continue;
              $kH = htmlspecialchars(_pretty_attr_label($k), ENT_QUOTES, 'UTF-8');
              $vH = htmlspecialchars($v, ENT_QUOTES, 'UTF-8');
            ?>
              <p class="attr-line"><strong><?= $kH ?>:</strong> <?= $vH ?></p>
            <?php endforeach; ?>
          </div>
        <?php endif; ?>

        <?php if($pd): ?>
          <p><strong>Precio detalle:</strong> <?= '$'.$pd ?> Imp. incl.</p>
        <?php endif; ?>

        <?php if($dSnip):?><p class="descripcion-snippet"><?= $dSnip ?></p><?php endif; ?>
      </div>

      <div class="acciones">
        <button class="btn-detalle" onclick="event.stopPropagation(); openProductoModal(this.closest('.producto'));">Ver detalle</button>
        <button class="btn-reservar" onclick="event.stopPropagation(); addToCartFromCard(this.closest('.producto'), 1);">Agregar al carrito</button>
      </div>

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
          "price": <?= json_encode($unitPriceClpInt) ?>,
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

<!-- Configuración de rutas dinámicas (DEBE SER LO PRIMERO) -->
<?php include __DIR__ . '/config/routes.php'; ?>

<!-- Meta Pixel Code -->
<script>
!function(f,b,e,v,n,t,s)
{if(f.fbq)return;n=f.fbq=function(){n.callMethod?
n.callMethod.apply(n,arguments):n.queue.push(arguments)};
if(!f._fbq)f._fbq=n;n.push=n;n.loaded=!0;n.version='2.0';
n.queue=[];t=b.createElement(e);t.async=!0;
t.src=v;s=b.getElementsByTagName(e)[0];
s.parentNode.insertBefore(t,s)}(window, document,'script',
'https://connect.facebook.net/en_US/fbevents.js');
fbq('init', '8850593125018234');
fbq('track', 'PageView');
</script>
<noscript><img height="1" width="1" style="display:none"
src="https://www.facebook.com/tr?id=8850593125018234&ev=PageView&noscript=1"
/></noscript>
<!-- End Meta Pixel Code -->

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

<link rel="canonical" href="<?= $canonicalH ?>">

<title>Catálogo de Plantines al Detalle | Roelplant</title>
<meta name="description" content="Plantines ornamentales al detalle: interior, exterior, cubresuelos y más. Despacho a todo Chile. Compra mínima 5 plantines. Vivero y plantinera en Quillota.">
<meta name="author" content="Roelplant">
<meta name="keywords" content="plantines, plantines al detalle, vivero, plantinera, semillas, plantas interior, plantas exterior, cubresuelos, suculentas, lavanda, monstera, philodendron, quillota, chile">
<meta name="robots" content="index,follow,max-snippet:-1,max-image-preview:large,max-video-preview:-1">

<!-- Open Graph -->
<meta property="og:type" content="website">
<meta property="og:title" content="<?= $ogTitleH ?>">
<meta property="og:description" content="<?= $ogDescH ?>">
<meta property="og:url" content="<?= $ogUrlH ?>">
<meta property="og:site_name" content="Roelplant">
<meta property="og:image" content="<?= $ogImageH ?>">
<meta property="og:image:width" content="800">
<meta property="og:image:height" content="800">

<!-- Twitter -->
<meta name="twitter:card" content="summary_large_image">
<meta name="twitter:title" content="<?= $ogTitleH ?>">
<meta name="twitter:description" content="<?= $ogDescH ?>">
<meta name="twitter:image" content="<?= $ogImageH ?>">

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
  "url":"https://clientes.roelplant.cl/catalogo_detalle_webpay/",
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

<link rel="stylesheet" href="<?php echo htmlspecialchars(buildUrl('assets/styles.css?v=4'), ENT_QUOTES, 'UTF-8'); ?>">

<style>
/* ====== BUSCADOR TOP (no interfiere con assets/styles.css) ====== */
.topbar{ gap:12px; flex-wrap:wrap; }
.top-search{ flex:1 1 320px; max-width:520px; min-width:240px; }
.top-search-input{
  width:100%;
  height:40px;
  border:1px solid #e5e7eb;
  border-radius:999px;
  padding:0 14px;
  background:#f8fafc;
  outline:none;
  font-size:14px;
}
.top-search-input:focus{ background:#fff; border-color:#93c5fd; box-shadow:0 0 0 3px rgba(59,130,246,.15); }

/* ====== Atributos ====== */
.attrs{
  margin:8px 0 10px;
  padding:8px 10px;
  background:#f8fafc;
  border:1px solid #e5e7eb;
  border-radius:10px;
}
.attr-line{ margin:0; font-size:13px; color:#111827; line-height:1.35; }
.attr-line + .attr-line{ margin-top:4px; }
.attr-line strong{ color:#374151; }

/* ====== Botones Flotantes RRSS ====== */
.float-social{
  position:fixed;
  bottom:20px;
  right:20px;
  display:flex;
  flex-direction:column;
  gap:12px;
  z-index:999;
}
.float-social a{
  display:flex;
  align-items:center;
  justify-content:center;
  width:56px;
  height:56px;
  border-radius:50%;
  box-shadow:0 4px 12px rgba(0,0,0,0.15);
  text-decoration:none;
  transition:all 0.3s ease;
}
.float-social a:hover{
  transform:scale(1.1);
  box-shadow:0 6px 16px rgba(0,0,0,0.2);
}
.float-social a svg{
  width:28px;
  height:28px;
  color:#fff;
}
.fab-wa{
  background:#25d366;
}
.fab-wa:hover{
  background:#20ba5a;
}
.fab-ig{
  background: radial-gradient(circle at 30% 110%, #fdf497 0%, #fdf497 5%, #fd5949 45%, #d6249f 60%, #285AEB 90%);
}
.fab-ig:hover{
  transform:scale(1.1);
}
</style>
<link rel="stylesheet" href="<?php echo htmlspecialchars(buildUrl('assets/carrusel.css'), ENT_QUOTES, 'UTF-8'); ?>">
</head>
<body>

<!-- Topbar -->
<div class="topbar" role="banner">
  <div class="brand">
    <img src="https://roelplant.cl/assets/images/favicon-128x128.png" alt="Roelplant">
    <strong>Roelplant · Catálogo detalle</strong>
  </div>

  <!-- Buscador (filtra tarjetas por nombre/ref/atributos/desc) -->
  <form class="top-search" role="search" aria-label="Buscar productos" onsubmit="return false;" autocomplete="off" style="all: unset; display: block; flex: 1 1 320px; max-width: 520px; min-width: 240px;">
    <input id="catalogSearch" class="top-search-input" type="text" placeholder="Buscar por nombre, referencia o atributo…" autocomplete="off" spellcheck="false">
  </form>

  <div class="actions">
    <a class="btn-top" href="../catalogo_mayorista/">Ir al Catálogo Mayorista</a>
    <button class="cart-pill" id="btnCart" type="button" onclick="openCartModal()">
      Carrito <span class="badge" id="cartCount">0</span>
    </button>
    <a class="btn-top" id="btnAccount" href="profile.php" style="display:none">Mi perfil</a>
    <a class="btn-top" id="btnOrders" href="my_orders.php" style="display:none">Mis pedidos</a>
    <button class="btn-top primary" id="btnAuth" type="button" onclick="openAuthModal()">Ingresar / Registrarse</button>
    <button class="btn-top danger" id="btnLogout" type="button" style="display:none" onclick="doLogout()">Salir</button>
  </div>
</div>

<!-- Carrusel Hero -->
<section class="hero-carousel" aria-label="Carrusel de destacados Roelplant">
  <div class="c-inner" data-carousel="" tabindex="0" aria-roledescription="carousel" data-interval="5200">
    <div class="c-track" aria-live="polite" style="transform: translate3d(-200%, 0px, 0px);">

      <article class="c-slide" aria-hidden="true">
        <a class="c-link" href="https://clientes.roelplant.cl/catalogo_detalle/?ref=E1004" aria-label="Ir a Plantines de Veronica Brillantísima">
          <img class="c-img" src="assets/img/brillantisima.png" alt="Plantines de Veronica Brillantísima, belleza y color para tu jardín" width="1600" height="600" loading="eager" fetchpriority="high" decoding="async">
          <div class="c-caption">
            <h2>Plantines de Veronica Brillantísima</h2>
            <p>Belleza y color en tu jardín</p>
          </div>
        </a>
      </article>

      <article class="c-slide" aria-hidden="true">
        <a class="c-link" href="?ref=E0002" aria-label="Ir a Plantines Veronica buxifolia">
          <img class="c-img" src="assets/img/veronica_buxifolia.png" alt="Plantines de Veronica buxifolia, exuberante y resistente" width="1600" height="600" loading="lazy" decoding="async">
          <div class="c-caption">
            <h2>Plantines Veronica buxifolia</h2>
            <p>Exuberante y resistente</p>
          </div>
        </a>
      </article>

      <article class="c-slide" aria-hidden="false">
        <a class="c-link" href="https://clientes.roelplant.cl/catalogo_mayorista/" aria-label="Ir a Plantines al detalle desde 5 unidades">
          <img class="c-img" src="assets/img/plantines_por_mayor.png" alt="Plantines al detalle desde 5 unidades" width="1600" height="600" loading="lazy" decoding="async">
          <div class="c-caption">
            <h2>Plantines al por mayor</h2>
            <p>Sobre 200 unidades</p>
          </div>
        </a>
      </article>

      <article class="c-slide" aria-hidden="true">
        <a class="c-link" href="https://clientes.roelplant.cl/catalogo_detalle/" target="_blank" rel="noopener" aria-label="Ir a Producción a medida: regístrate y solicita una producción">
          <img class="c-img" src="assets/img/pedidos_produccion.png" alt="Haz tu producción con nosotros: regístrate y solicita una producción a medida" width="1600" height="600" loading="lazy" decoding="async">
          <div class="c-caption">
            <h2>Haz tu producción con nosotros</h2>
            <p>Regístrate y solicita una producción a medida</p>
          </div>
        </a>
      </article>

    </div>

    <button class="c-nav prev" type="button" aria-label="Anterior">
      <svg viewBox="0 0 24 24" aria-hidden="true">
        <path d="M15.5 19.5 8 12l7.5-7.5" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"></path>
      </svg>
    </button>

    <button class="c-nav next" type="button" aria-label="Siguiente">
      <svg viewBox="0 0 24 24" aria-hidden="true">
        <path d="M8.5 4.5 16 12l-7.5 7.5" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"></path>
      </svg>
    </button>

    <div class="c-dots" role="tablist" aria-label="Seleccionar slide"><button type="button" class="c-dot" role="tab" aria-label="Ir al slide 1" aria-selected="false"></button><button type="button" class="c-dot" role="tab" aria-label="Ir al slide 2" aria-selected="false"></button><button type="button" class="c-dot" role="tab" aria-label="Ir al slide 3" aria-selected="true"></button><button type="button" class="c-dot" role="tab" aria-label="Ir al slide 4" aria-selected="false"></button></div>
  </div>
</section>

<h1>Catálogo Al detalle Roelplant</h1>

<button class="menu-toggle" id="menuToggle" aria-controls="techMenu" aria-expanded="false" style="display:none">
  <svg viewBox="0 0 24 24" fill="currentColor" aria-hidden="true">
    <path d="M3 6h18v2H3V6zm4 5h10v2H7v-2zm-2 5h14v2H5v-2z"/>
  </svg>
  Filtros
</button>

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

<!-- Nota Final (sin cambios) -->
<div style="max-width: 900px; margin: 24px auto 40px; padding: 20px; background-color: #fffbe6; border: 2px dashed #f39c12; border-radius: 10px; color: #555;">
  <h3 style="color: #d35400; margin: 0 0 10px;">📌 Nota Importante sobre los Pedidos</h3>
  <p><strong>🌿 Catálogo Detalle:</strong> Desde 5 plantines. Envíos disponibles a todo Chile con Starken y otros couriers. Todos los envíos deben ser previamente cancelados. Los envíos son todos por pagar al courier seleccionado.
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

<!-- MODAL DETALLE PRODUCTO -->
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
        <div class="qty-ctl">
          <button type="button" onclick="chgModalQty(-1)">−</button>
          <input class="inp" id="mQty" type="number" min="1" value="1">
          <button type="button" onclick="chgModalQty(1)">+</button>
        </div>
        <button class="btn-reservar" onclick="addToCartCurrent()">Agregar al carrito</button>
        <button class="btn-detalle" onclick="closeProductoModal()">Cerrar</button>
      </div>
      <div class="notice">Para agregar al carrito debes iniciar sesión con tu correo.</div>
    </div>
  </div>
</div>

<!-- MODAL AUTH -->
<div id="authModal" class="modal" aria-hidden="true">
  <div class="modal-sheet" role="dialog" aria-modal="true" aria-labelledby="authTitle">
    <button class="modal-close" onclick="closeAuthModal()" aria-label="Cerrar">×</button>
    <h3 id="authTitle" style="margin:0 0 10px;color:#111827">Ingresar / Registrarse</h3>
    <div class="tabs">
      <button class="tab-btn active" id="tabLogin" onclick="setAuthTab('login')">Ingresar</button>
      <button class="tab-btn" id="tabRegister" onclick="setAuthTab('register')">Registrarse</button>
    </div>

    <div id="authErr" class="err" style="display:none"></div>

    <div id="paneLogin">
      <div class="form-grid">
        <input class="inp" id="loginEmail" type="email" placeholder="Email (correo@dominio.com)">
        <input class="inp" id="loginPass" type="password" placeholder="Contraseña">
      </div>
      <div class="form-actions">
        <button class="btn-top primary" type="button" onclick="doLogin()">Ingresar</button>
      </div>
      <div class="notice">Ingresa con tu email. (El RUT se solicita al registrarte.)</div>
    </div>

    <div id="paneRegister" style="display:none">
      <div class="form-grid">
        <input class="inp" id="regRut" placeholder="RUT (12.345.678-5)">
        <input class="inp" id="regEmail" type="email" placeholder="Email (correo@dominio.com)">

        <input class="inp" id="regNombre" placeholder="Nombre completo">
        <input class="inp" id="regTelefono" placeholder="Teléfono">

        <select class="inp" id="regRegion" aria-label="Región"><option value="">Selecciona Región</option></select>
        <select class="inp" id="regComuna" aria-label="Comuna" disabled><option value="">Selecciona Comuna</option></select>

        <input class="inp span2" id="regCiudad" placeholder="Ciudad">
        <input class="inp span2" id="regDomicilio" placeholder="Domicilio / Dirección">

        <input class="inp span2" id="regPass" type="password" placeholder="Contraseña (mín 8)">
      </div>
      <div class="form-actions">
        <button class="btn-top primary" type="button" onclick="doRegister()">Crear cuenta</button>
      </div>
    </div>
  </div>
</div>

<!-- MODAL CARRITO -->
<div id="cartModal" class="modal" aria-hidden="true">
  <div class="modal-sheet" role="dialog" aria-modal="true" aria-labelledby="cartTitle">
    <button class="modal-close" onclick="closeCartModal()" aria-label="Cerrar">×</button>
    <h3 id="cartTitle" style="margin:0 0 10px;color:#111827">Tu carrito</h3>

    <div id="cartErr" class="err" style="display:none"></div>
    <div id="cartList" class="cart-list"></div>

    <div class="cart-total">
      <div>Total</div>
      <div id="cartTotal">$0</div>
    </div>
    <div class="form-actions" style="justify-content:flex-end;gap:10px;margin-top:12px;">
  <button class="btn-top primary" type="button" id="btnGoCheckout">Generar compra</button>
</div>
<div class="small" style="margin-top:6px;color:#6b7280;">
  Revisa tu carrito y luego genera tu compra para enviar el pedido por WhatsApp.
</div>
  </div>
</div>

<div id="toast" class="toast" role="status" aria-live="polite"></div>

  <script src="<?php echo htmlspecialchars(buildUrl('assets/locations_cl.js?v=1'), ENT_QUOTES, 'UTF-8'); ?>"></script>
  <script src="<?php echo htmlspecialchars(buildUrl('assets/app.js?v=5'), ENT_QUOTES, 'UTF-8'); ?>"></script>

<!-- Buscador (filtrado local) + Modal attrs (sin tocar backend JS) -->
<script>
(function(){
  const inp = document.getElementById('catalogSearch');
  if(!inp) return;

  // Desactivar autocomplete agresivamente
  inp.autocomplete = 'off';
  inp.setAttribute('autocomplete', 'off');
  inp.addEventListener('focus', () => {
    inp.autocomplete = 'off';
    inp.setAttribute('autocomplete', 'off');
  });
  inp.addEventListener('input', () => {
    inp.setAttribute('autocomplete', 'off');
  });

  const norm = (s) => String(s||'')
    .toLowerCase()
    .normalize('NFD').replace(/[\u0300-\u036f]/g,'')
    .replace(/\s+/g,' ')
    .trim();

  function applyFilter(){
    const q = norm(inp.value);
    const cards = Array.from(document.querySelectorAll('.producto'));

    cards.forEach(card=>{
      if(!q){ card.style.display = ''; return; }
      const hay = [
        card.dataset.nombre,
        card.dataset.ref,
        card.dataset.attrs,
        card.dataset.descripcion
      ].map(norm).join(' ');
      card.style.display = hay.includes(q) ? '' : 'none';
    });

    // Oculta secciones sin resultados
    document.querySelectorAll('h2[id]').forEach(h2=>{
      const cat = h2.nextElementSibling;
      if(!cat || !cat.classList || !cat.classList.contains('catalogo')) return;
      const any = Array.from(cat.querySelectorAll('.producto')).some(el => el.style.display !== 'none');
      h2.style.display  = any ? '' : 'none';
      cat.style.display = any ? '' : 'none';
    });
  }

  inp.addEventListener('input', applyFilter);

  // Prefill desde ?s= o ?search_term_string=
  const sp = new URLSearchParams(window.location.search);
  const s = sp.get('s') || sp.get('search_term_string') || '';
  if(s){ inp.value = s; applyFilter(); }

  // Hook suave: si existe openProductoModal, extendemos para mostrar attrs y precio detalle
  const _origOpen = window.openProductoModal;
  if(typeof _origOpen === 'function'){
    window.openProductoModal = function(card){
      _origOpen(card);

      try{
        const attrsRaw = (card && card.dataset && card.dataset.attrs) ? String(card.dataset.attrs) : '';
        const mAttrs = document.getElementById('mAttrs');
        if(mAttrs){
          const parts = attrsRaw.split('||').map(x=>x.trim()).filter(Boolean);
          if(parts.length){
            mAttrs.innerHTML = parts.map(kv=>{
              const idx = kv.indexOf(':');
              if(idx<=0) return '';
              const k = kv.slice(0,idx).trim();
              const v = kv.slice(idx+1).trim();
              if(!k || !v) return '';
              const kNice = k.toLowerCase().replace(/\b\w/g, c=>c.toUpperCase());
              return `<p class="attr-line"><strong>${kNice}:</strong> ${v}</p>`;
            }).join('');
            mAttrs.style.display = '';
          }else{
            mAttrs.innerHTML = '';
            mAttrs.style.display = 'none';
          }
        }

        const pd = (card && card.dataset) ? (card.dataset.preciodetalle || '') : '';
        const pm = (card && card.dataset) ? (card.dataset.preciomayorista || '') : '';
        const mPD = document.getElementById('mPrecioDetalle');
        const mPM = document.getElementById('mPrecioMayorista');

        if(mPD){
          if(pd){
            mPD.style.display = '';
            mPD.innerHTML = `<strong>Precio detalle:</strong> $${pd} Imp. incl.`;
          }else if(pm){
            mPD.style.display = '';
            mPD.innerHTML = `<strong>Precio:</strong> $${pm} Imp. incl.`;
          }else{
            mPD.style.display = '';
            mPD.innerHTML = `<strong>Precio:</strong> No disponible`;
          }
        }
        if(mPM){
          if(pm && pd){
            mPM.style.display = '';
            mPM.innerHTML = `<strong>Precio mayorista:</strong> $${pm} Imp. incl.`;
          }else{
            mPM.style.display = 'none';
            mPM.innerHTML = '';
          }
        }
      }catch(e){}
    }
  }
})();

// ====== CARRUSEL ======
(function(){
  const carouselEl = document.querySelector('[data-carousel]');
  if (!carouselEl) return;

  const track = carouselEl.querySelector('.c-track');
  const slides = carouselEl.querySelectorAll('.c-slide');
  const dots = carouselEl.querySelectorAll('.c-dot');
  const prevBtn = carouselEl.querySelector('.c-nav.prev');
  const nextBtn = carouselEl.querySelector('.c-nav.next');

  let current = 2; // Empieza en el slide 3 (índice 2)
  const total = slides.length;
  let autoplayTimer = null;

  function updateCarousel() {
    const offset = -current * 100;
    track.style.transform = `translate3d(${offset}%, 0, 0)`;

    slides.forEach((s, i) => {
      s.setAttribute('aria-hidden', i === current ? 'false' : 'true');
    });

    dots.forEach((d, i) => {
      d.setAttribute('aria-selected', i === current ? 'true' : 'false');
    });
  }

  function go(n) {
    current = (n + total) % total;
    updateCarousel();
    resetAutoplay();
  }

  function resetAutoplay() {
    clearTimeout(autoplayTimer);
    const interval = parseInt(carouselEl.getAttribute('data-interval') || 5000);
    autoplayTimer = setTimeout(() => go(current + 1), interval);
  }

  prevBtn?.addEventListener('click', () => go(current - 1));
  nextBtn?.addEventListener('click', () => go(current + 1));

  dots.forEach((dot, i) => {
    dot.addEventListener('click', () => go(i));
  });

  carouselEl.addEventListener('keydown', (e) => {
    if (e.key === 'ArrowLeft') go(current - 1);
    if (e.key === 'ArrowRight') go(current + 1);
  });

  updateCarousel();
  resetAutoplay();
})();
</script>

<!-- Burbujas flotantes RRSS -->
<div class="float-social" aria-label="Accesos rápidos">
  <!-- WhatsApp -->
  <a class="fab-wa"
     href="https://wa.me/56984226651?text=%C2%A1Hola%21%20Quisiera%20saber%20m%C3%A1s%20sobre%20los%20servicios%20de%20producci%C3%B3n%20de%20Roelplant%20y%20la%20disponibilidad%20de%20plantines.%20%C2%BFPodr%C3%ADan%20ayudarme%3F"
     target="_blank" rel="noopener"
     aria-label="Escribir por WhatsApp a Roelplant">
    <svg viewBox="0 0 32 32" aria-hidden="true" focusable="false">
      <path fill="currentColor" d="M19.11 17.62c-.27-.14-1.62-.8-1.87-.89-.25-.09-.43-.14-.61.14-.18.27-.7.89-.86 1.07-.16.18-.32.2-.59.07-.27-.14-1.13-.42-2.16-1.33-.8-.71-1.34-1.58-1.5-1.85-.16-.27-.02-.42.12-.55.12-.12.27-.32.41-.48.14-.16.18-.27.27-.46.09-.18.05-.34-.02-.48-.07-.14-.61-1.47-.84-2.02-.22-.53-.45-.46-.61-.46l-.52-.01c-.18 0-.48.07-.73.34-.25.27-.95.93-.95 2.27 0 1.34.98 2.63 1.11 2.82.14.18 1.93 2.95 4.68 4.14.65.28 1.16.45 1.56.57.66.21 1.26.18 1.73.11.53-.08 1.62-.66 1.85-1.3.23-.64.23-1.18.16-1.3-.07-.12-.25-.2-.52-.34z"/>
      <path fill="currentColor" d="M16 3C8.83 3 3 8.73 3 15.78c0 2.3.62 4.55 1.8 6.5L3 29l6.92-1.79a13.24 13.24 0 0 0 6.08 1.47c7.17 0 13-5.73 13-12.78C29 8.73 23.17 3 16 3zm0 23.36c-1.93 0-3.83-.5-5.5-1.45l-.39-.22-4.1 1.06 1.09-3.99-.25-.41a11.17 11.17 0 0 1-1.73-5.98C5.12 9.98 10.07 5.12 16 5.12c5.93 0 10.88 4.86 10.88 10.66S21.93 26.36 16 26.36z"/>
    </svg>
  </a>

  <!-- Instagram -->
  <a class="fab-ig"
     href="https://www.instagram.com/roelplant/"
     target="_blank" rel="noopener"
     aria-label="Abrir Instagram de Roelplant">
    <svg viewBox="0 0 24 24" aria-hidden="true" focusable="false">
      <path fill="currentColor" d="M7.5 2h9A5.5 5.5 0 0 1 22 7.5v9A5.5 5.5 0 0 1 16.5 22h-9A5.5 5.5 0 0 1 2 16.5v-9A5.5 5.5 0 0 1 7.5 2zm9 2h-9A3.5 3.5 0 0 0 4 7.5v9A3.5 3.5 0 0 0 7.5 20h9a3.5 3.5 0 0 0 3.5-3.5v-9A3.5 3.5 0 0 0 16.5 4z"/>
      <path fill="currentColor" d="M12 7a5 5 0 1 1 0 10 5 5 0 0 1 0-10zm0 2a3 3 0 1 0 0 6 3 3 0 0 0 0-6z"/>
      <circle cx="17.5" cy="6.5" r="1" fill="currentColor"/>
    </svg>
  </a>
</div>

</body>
</html>
