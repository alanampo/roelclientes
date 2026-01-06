<?php
// ===== No cache =====
header("Cache-Control: no-cache, no-store, must-revalidate");
header("Pragma: no-cache");
header("Expires: 0");

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require __DIR__ . '/../class_lib/class_conecta_mysql.php';
$link = mysqli_connect($host, $user, $password, $dbname);
if (!$link) {
    die("Error conexión: " . mysqli_connect_error());
}
mysqli_set_charset($link, 'utf8');

/* ====== CONFIG OGIMG (MISMO secreto que en detalle/ogimg.php) ====== */
const OGIMG_SECRET = 'CAMBIA-ESTO-POR-UN-SECRETO-LARGO-Y-ALEATORIO-32+CHARS';

/* -------- Query: resumen por variedad (misma lógica que backend) -------- */
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

$r = mysqli_query($link, $sql);
if (!$r) {
    die("Error SQL catálogo: " . mysqli_error($link));
}

/* -------- Agrupar -------- */
$interior = [];
$exterior = [];
$cubre_suelos = [];
$hierbas = [];
$arboles = [];
$packs_interior = [];
$packs_exterior = [];
$invitro_interior = [];

while ($row = mysqli_fetch_assoc($r)) {
    $tipo = strtoupper(trim($row['tipo_planta'] ?? ''));
    switch ($tipo) {
        case 'PLANTAS DE INTERIOR':
            $interior[] = $row;
            break;
        case 'PLANTAS DE EXTERIOR':
            $exterior[] = $row;
            break;
        case 'CUBRE SUELOS':
            $cubre_suelos[] = $row;
            break;
        case 'HIERBAS':
            $hierbas[] = $row;
            break;
        case 'ÁRBOLES':
        case 'ARBOLES':
            $arboles[] = $row;
            break;
        case 'PACKS INTERIOR':
            $packs_interior[] = $row;
            break;
        case 'PACKS EXTERIOR':
            $packs_exterior[] = $row;
            break;
        case 'INVITRO INTERIOR':
            $invitro_interior[] = $row;
            break;
    }
}

function truncar($t, $l = 120)
{
    $t = trim((string)$t);
    if ($t === '') return '';
    return function_exists('mb_strimwidth')
        ? mb_strimwidth($t, 0, $l, '…', 'UTF-8')
        : (strlen($t) > $l ? substr($t, 0, $l - 2) . '…' : $t);
}

/* -------- Helper URL base -------- */
$scheme        = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
$catalogoBase  = $scheme . '://' . $_SERVER['HTTP_HOST'] . $_SERVER['PHP_SELF']; // p.ej. /catalogo_mayorista/index.php
$priceValidUntil = (new DateTime('+14 days'))->format('Y-m-d');

/* -------- OpenGraph dinámico por ?ref= (con proxy ogimg.php) -------- */
$refParam = isset($_GET['ref']) ? trim((string)$_GET['ref']) : '';

$ogTitle = 'Catálogo Mayorista de Plantines | Roelplant';
$ogDesc  = 'Mayorista de plantines con envíos a todo Chile. Interior, exterior, cubre suelos y más.';
$ogUrl   = $catalogoBase;
$canonicalUrl = $catalogoBase;

// Por defecto: logo
$ogImage = 'https://roelplant.cl/assets/images/logo-fondo-negroV2.png';

if ($refParam !== '') {
    $refParamClean = $refParam;
    $refParamUrl   = rawurlencode($refParamClean);
    $ogUrl         = $catalogoBase . (strpos($catalogoBase, '?') === false ? '?' : '&') . 'ref=' . $refParamUrl;
    $canonicalUrl  = $ogUrl;

    $allForRef = array_merge(
        $interior,
        $exterior,
        $cubre_suelos,
        $hierbas,
        $arboles,
        $packs_interior,
        $packs_exterior,
        $invitro_interior
    );

    $found = null;
    foreach ($allForRef as $pp) {
        if ((string)$pp['referencia'] === $refParamClean) {
            $found = $pp;
            break;
        }
    }

    if ($found) {
        $nameOg  = (string)($found['variedad'] ?? '');
        $stockOg = (int)($found['disponible_para_reservar'] ?? 0);

        $pmOg = number_format(((float)($found['precio'] ?? 0)) * 1.19, 0, ',', '.');
        $precioVisibleOg = '$' . $pmOg . ' (Mayorista, imp. incl.)';

        $descOg = trim((string)($found['descripcion'] ?? ''));
        $descOg = $descOg ? truncar($descOg, 140) : '';

        $ogTitle = ($nameOg !== '' ? ($nameOg . ' | Roelplant') : $ogTitle);

        $parts = [];
        $parts[] = 'Precio: ' . $precioVisibleOg;
        $parts[] = 'Stock: ' . $stockOg;
        $parts[] = 'Ref: ' . $refParamClean;
        if ($descOg) $parts[] = $descOg;
        $ogDesc = truncar(implode(' · ', $parts), 240);

        $sig = hash_hmac('sha256', $refParamClean, OGIMG_SECRET);
        $ogImage = 'https://clientes.roelplant.cl/ogimg.php?ref=' . $refParamUrl . '&sig=' . $sig;
    }
}

$ogTitleH    = htmlspecialchars($ogTitle, ENT_QUOTES, 'UTF-8');
$ogDescH     = htmlspecialchars($ogDesc, ENT_QUOTES, 'UTF-8');
$ogUrlH      = htmlspecialchars($ogUrl, ENT_QUOTES, 'UTF-8');
$canonicalH  = htmlspecialchars($canonicalUrl, ENT_QUOTES, 'UTF-8');
$ogImageH    = htmlspecialchars($ogImage, ENT_QUOTES, 'UTF-8');

/* -------- Render Cards -------- */
function render_catalogo(array $ps, string $catalogoBase, string $priceValidUntil): void
{
    foreach ($ps as $p) {
        $pmNumber = ((float)$p['precio']) * 1.19;
        $pm       = number_format($pmNumber, 0, ',', '.');

        $imgFile = !empty($p['imagen']) ? htmlspecialchars($p['imagen'], ENT_QUOTES, 'UTF-8') : '';
        $img = $imgFile
            ? "https://control.roelplant.cl/uploads/variedades/{$imgFile}"
            : "https://via.placeholder.com/600x400?text=Imagen+pendiente";
        $imgCb = $img . (strpos($img, '?') === false ? '?v=' : '&v=') . time();

        $d     = trim($p['descripcion'] ?? '');
        $dAttr = htmlspecialchars($d, ENT_QUOTES, 'UTF-8');
        $dSnip = htmlspecialchars(truncar($d, 120), ENT_QUOTES, 'UTF-8');

        $refRaw = (string)$p['referencia'];
        $ref    = htmlspecialchars($refRaw, ENT_QUOTES, 'UTF-8');
        $refUrl = rawurlencode($refRaw);

        $name  = htmlspecialchars($p['variedad'], ENT_QUOTES, 'UTF-8');
        $stock = (int)$p['disponible_para_reservar'];

        $prodUrl = $catalogoBase . (strpos($catalogoBase, '?') === false ? '?' : '&') . 'ref=' . $refUrl;
        $prodUrlAttr = htmlspecialchars($prodUrl, ENT_QUOTES, 'UTF-8');

        // Texto visible para WhatsApp (igual enfoque que detalle)
        $precioVisibleTxt  = '$' . $pm . ' (Mayorista, imp. incl.)';
        $precioVisibleAttr = htmlspecialchars($precioVisibleTxt, ENT_QUOTES, 'UTF-8');
        ?>
        <div class="producto" role="button" tabindex="0"
             aria-label="Ver detalle <?= $name ?>"
             data-nombre="<?= $name ?>"
             data-ref="<?= $ref ?>"
             data-stock="<?= $stock ?>"
             data-preciomayorista="<?= $pm ?>"
             data-preciovisible="<?= $precioVisibleAttr ?>"
             data-imagen="<?= $img ?>"
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
            <p><strong>Stock:</strong> <?= $stock ?></p>
            <p><strong>Precio Mayorista:</strong> $<?= $pm ?> Imp. incl.</p>
            <?php if ($dSnip): ?>
                <p class="descripcion-snippet"><?= $dSnip ?></p>
            <?php endif; ?>
          </div>

          <div class="acciones">
            <button class="btn-detalle"
                    onclick="event.stopPropagation(); openProductoModal(this.closest('.producto'));">
                Ver detalle
            </button>
            <!-- Mantengo clase btn-reservar para NO romper tracking, pero ahora compra por WhatsApp -->
            <button class="btn-reservar"
                    onclick="event.stopPropagation(); openWhatsApp(this.closest('.producto'));">
                Comprar
            </button>
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
            "category": <?= json_encode($p['tipo'] ?? "PLANTINES") ?>,
            "description": <?= json_encode($d ?: $name) ?>,
            "url": <?= json_encode($prodUrl) ?>,
            "offers":{
              "@type":"Offer",
              "url": <?= json_encode($prodUrl) ?>,
              "priceCurrency":"CLP",
              "price": <?= json_encode((int)round($pmNumber)) ?>,
              "availability": "https://schema.org/<?= $stock>0 ? 'InStock' : 'OutOfStock' ?>",
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

<!-- Google Tag Manager -->
<script>(function(w,d,s,l,i){w[l]=w[l]||[];w[l].push({'gtm.start':
new Date().getTime(),event:'gtm.js'});var f=d.getElementsByTagName(s)[0],
j=d.createElement(s),dl=l!='dataLayer'?'&l='+l:'';j.async=true;j.src=
'https://www.googletagmanager.com/gtm.js?id='+i+dl;f.parentNode.insertBefore(j,f);
})(window,document,'script','dataLayer','GTM-PKRXTP4L');</script>
<!-- End Google Tag Manager -->

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

<title>Catálogo Mayorista de Plantines | Roelplant</title>
<meta name="description" content="Precios mayoristas desde 100 plantines. Interior, exterior, cubre suelos, hierbas y packs. Envíos a todo Chile. Producción a pedido y retiro en vivero (Quillota).">
<meta name="author" content="Roelplant">
<meta name="keywords" content="plantines por mayor, mayorista, vivero, plantinera, plantas interior, plantas exterior, cubre suelos, hierbas, packs, producción a pedido, quillota, chile">
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
<meta property="og:locale" content="es_CL">

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
    "https://www.instagram.com/roelplant",
    "https://wa.me/56984226651"
  ]
}
</script>
<script type="application/ld+json">
{
  "@context":"https://schema.org",
  "@type":"WebSite",
  "name":"Roelplant",
  "url":"https://roelplant.cl/",
  "inLanguage":"es-CL",
  "potentialAction":{
    "@type":"SearchAction",
    "target":"https://clientes.roelplant.cl/catalogo_mayorista/index.php?q={search_term_string}",
    "query-input":"required name=search_term_string"
  }
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
.img-wrap{background:#f7f7f7;border-radius:10px;overflow:hidden;padding:6px;margin-bottom:12px;aspect-ratio:4/3}
.producto img{width:100%;height:100%;aspect-ratio:4/3;object-fit:contain;display:block}
.contenido{display:flex;flex-direction:column;gap:6px}
.variedad{text-transform:uppercase;font-weight:800;color:var(--brand);margin:4px 0 6px;font-size:1.06em;line-height:1.2;min-height:2.6em}
.producto p{color:#555;margin:0}
.descripcion-snippet{font-size:.95em;color:#3e3e3e}

/* Acciones */
.acciones{margin-top:auto;display:flex;gap:12px;justify-content:space-between;padding:12px 10px 0;flex-wrap:wrap}
.btn-detalle,.btn-reservar{background:var(--brand);color:#fff;padding:10px 14px;border:0;border-radius:10px;cursor:pointer;min-width:120px;height:40px;display:inline-flex;align-items:center;justify-content:center}
.btn-detalle{background:#2d6fb6}.btn-detalle:hover{background:#1f6690}.btn-reservar:hover{background:#1e8a4c}
@media (min-width:981px){.btn-detalle,.btn-reservar{flex:0 0 46%;max-width:46%;min-width:auto;height:36px;padding:8px 0;font-size:14px}}

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
  .modal .acciones-modal{position:sticky;bottom:0;background:#fff;padding-top:8px;margin-top:12px;box-shadow:0 -6px 12px rgba(0,0,0,.06);gap:10px;flex-direction:column}
  .modal .acciones-modal .btn-reservar,.modal .acciones-modal .btn-detalle{width:100%}
}

/* Burbujas + menú */
.whatsapp-bubble,.ig-bubble{position:fixed;bottom:20px;width:56px;height:56px;display:flex;align-items:center;justify-content:center;border-radius:50%;cursor:pointer;z-index:9000;box-shadow:0 4px 8px rgba(0,0,0,.3)}
.whatsapp-bubble{background:#25D366;left:20px}.whatsapp-bubble img{width:32px;height:32px}
.ig-bubble{right:20px;background:linear-gradient(45deg,#f58529,#dd2a7b,#8134af,#515bd4)}.ig-bubble img{width:28px;height:28px}
@media (max-width:980px){.whatsapp-bubble{bottom:84px;left:20px}}
@media (min-width:981px){.whatsapp-bubble{left:auto;right:20px;bottom:20px}.ig-bubble{right:20px;bottom:90px}}

.tech-menu{position:fixed;top:88px;left:20px;width:260px;background:#fff;border:1px solid #e5e7eb;border-radius:12px;box-shadow:0 10px 24px rgba(0,0,0,.08);padding:12px;z-index:9500}
.tech-menu h3{margin:6px 8px 8px;font-size:16px;color:var(--ink)}
.tech-menu ul{list-style:none;margin:0;padding:0;max-height:70vh;overflow:auto}
.tech-menu a{display:block;padding:10px 12px;text-decoration:none;color:#334155;border-radius:10px}
.tech-menu a.active,.tech-menu a:hover{background:#eaf7f0;color:var(--menu)}
.tech-menu .divider{border-top:1px solid #eee;margin:8px 0}
body{padding-left:300px}
@media (max-width:980px){
  body{padding-left:0}
  h2{scroll-margin-top:76px}
  .catalogo{grid-template-columns:repeat(auto-fit,minmax(180px,1fr))}
  .menu-toggle{position:sticky;top:8px;z-index:9600;margin:0 auto 10px;display:flex;gap:8px;align-items:center;justify-content:center;background:var(--menu);color:#fff;border:0;border-radius:999px;padding:10px 16px;font-weight:600;box-shadow:0 6px 16px rgba(0,0,0,.12)}
  .menu-toggle svg{width:18px;height:18px}
  .tech-menu{position:fixed;left:12px;right:12px;top:64px;width:auto;margin:0;border-radius:14px;transform:translateY(-130%);transition:transform .25s ease;max-height:72vh;overflow:auto}
  .tech-menu.open{transform:translateY(0)}
}
@media (prefers-reduced-motion:reduce){*{transition:none!important;scroll-behavior:auto!important}}
</style>
</head>
<body>

<!-- Google Tag Manager (noscript) -->
<noscript>
  <iframe src="https://www.googletagmanager.com/ns.html?id=GTM-PKRXTP4L"
          height="0" width="0" style="display:none;visibility:hidden"></iframe>
</noscript>
<!-- End Google Tag Manager (noscript) -->

<h1>Catálogo Mayorista Roelplant</h1>

<!-- Botón filtros móvil -->
<button class="menu-toggle" id="menuToggle" aria-controls="techMenu" aria-expanded="false" style="display:none">
  <svg viewBox="0 0 24 24" fill="currentColor" aria-hidden="true">
    <path d="M3 6h18v2H3V6zm4 5h10v2H7v-2zm-2 5h14v2H5v-2z"/>
  </svg>
  Filtros
</button>

<!-- WhatsApp -->
<div class="whatsapp-bubble"
     onclick="window.open('https://wa.me/56984226651?text=Estoy%20viendo%20el%20cat%C3%A1logo%20de%20mayoristas%20y%20me%20gustar%C3%ADa%20realizar%20un%20pedido','_blank')">
  <img src="https://upload.wikimedia.org/wikipedia/commons/6/6b/WhatsApp.svg" alt="WhatsApp">
</div>

<?php
$secciones = [
  'interior'         => ['Catálogo de Plantines Disponibles de Interior', $interior],
  'exterior'         => ['Catálogo de Plantines Disponibles de Exterior', $exterior],
  'cubre-suelos'     => ['Catálogo de Cubre Suelos', $cubre_suelos],
  'hierbas'          => ['Catálogo de Hierbas', $hierbas],
  'arboles'          => ['Catálogo de Árboles', $arboles],
  'packs-interior'   => ['Packs Interior', $packs_interior],
  'packs-exterior'   => ['Packs Exterior', $packs_exterior],
  'invitro-interior' => ['In-Vitro Interior', $invitro_interior],
];
?>
<nav class="tech-menu" id="techMenu" aria-label="Filtrar por tipo">
  <h3>Tipos</h3>
  <ul>
    <?php foreach ($secciones as $id => $sec) { if (count($sec[1]) === 0) continue; ?>
      <li><a href="#<?= $id ?>"><?= htmlspecialchars($sec[0], ENT_QUOTES, 'UTF-8') ?></a></li>
    <?php } ?>
    <li class="divider"></li>
    <li><a href="#top">↑ Arriba</a></li>
  </ul>
</nav>
<a id="top"></a>

<?php foreach ($secciones as $id => $sec) { if (count($sec[1]) === 0) continue; ?>
  <h2 id="<?= $id ?>"><?= htmlspecialchars($sec[0], ENT_QUOTES, 'UTF-8') ?></h2>
  <div class="catalogo"><?php render_catalogo($sec[1], $catalogoBase, $priceValidUntil); ?></div>
<?php } ?>

<button class="ver-listado" onclick="window.location.href='catalogo_tabla.php?v=<?php echo time(); ?>'">
  Ver listado
</button>

<!-- Nota Final -->
<div style="max-width: 900px; margin: 40px auto; padding: 22px; background-color: #fffbe6; border: 2px dashed #f39c12; border-radius: 12px; font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; color: #555; line-height: 1.55;">
  <h3 style="color: #d35400; margin-top: 0;">📌 Nota importante</h3>

  <p><strong>Mayorista:</strong> Desde <strong>100 plantines</strong>. Envíos a todo Chile. Pagos <strong>previos al despacho</strong>.
    Revisa nuestros
    <a href="https://roelplant.cl/terminos/" target="_blank" rel="noopener">Términos y Condiciones</a>.
  </p>

  <p><strong>Producción a pedido:</strong> Coordinación de <strong>tiempos</strong> y <strong>cantidades</strong> con el equipo de ventas.
    Solicítalo aquí:
    <a href="https://share.hsforms.com/1zC9tJ1BLQrmqSrV_VZRk7Aea51p" target="_blank" rel="noopener">Solicita presupuesto</a>.
  </p>

  <ul style="padding-left: 18px; margin: 14px 0;">
    <li><strong>Precios:</strong> expresados en CLP e incluyen IVA (salvo se indique lo contrario). Vigencia sujeta a <em>stock</em> y a variaciones de temporada.</li>
    <li><strong>Mínimos por variedad:</strong> pueden aplicar para asegurar homogeneidad del lote. Consultar al momento de reservar.</li>
    <li><strong>Despachos:</strong> Starken u otros operadores. Embalaje reforzado y rotulación “producto vivo”. Seguimiento vía número de envío.</li>
    <li><strong>Recepción:</strong> al recibir, <strong>hidrate</strong> y <strong>aclimátese</strong> las plantas. Reporte incidencias con fotos dentro de las primeras 24 h.</li>
    <li><strong>Medios de pago:</strong> transferencia bancaria.</li>
    <li><strong>Retiro en vivero:</strong> disponible previa coordinación de fecha y horario.</li>
    <li><strong>Condiciones climáticas:</strong> en olas de calor/frío intenso, los despachos pueden reprogramarse para proteger el material vegetal.</li>
    <li><strong>Contacto ventas:</strong> WhatsApp
      <a href="https://wa.me/56984226651" target="_blank" rel="noopener">+56 9 8422 6651</a>
      <a href="https://wa.me/56984226651" target="_blank" rel="noopener">+56 9 8422 6651</a> ·
      Instagram <a href="https://instagram.com/roelplant" target="_blank" rel="noopener">@roelplant</a>
    </li>
  </ul>

  <p style="margin-bottom: 0; font-size: 0.95em; color: #666;">
    📆 Última actualización de condiciones: <span id="notaFechaAct"></span>.
  </p>
</div>

<script>
  (function(){
    try{
      const el = document.getElementById('notaFechaAct');
      if(!el) return;
      const f = new Date();
      const opts = { year:'numeric', month:'long', day:'numeric' };
      el.textContent = f.toLocaleDateString('es-CL', opts);
    }catch(e){}
  })();
</script>

<!-- IG -->
<div class="ig-bubble" onclick="window.open('https://instagram.com/roelplant','_blank')">
  <img src="https://upload.wikimedia.org/wikipedia/commons/a/a5/Instagram_icon.png" alt="IG">
</div>

<!-- Modal -->
<div id="productoModal" class="modal" aria-hidden="true">
  <div class="modal-content" role="dialog" aria-modal="true" aria-labelledby="modalTitle">
    <button class="modal-close" onclick="closeProductoModal()" aria-label="Cerrar">×</button>
    <img id="mImagen" src="" alt="Imagen del producto">
    <div>
      <h3 id="modalTitle"></h3>
      <p id="mRef"></p>
      <p id="mStock"></p>
      <p id="mPrecioMayorista"></p>
      <div id="mDesc" class="modal-desc"></div>
      <div class="acciones-modal">
        <button class="btn-reservar" onclick="comprarWhatsAppActual();">Comprar</button>
        <button class="btn-detalle" onclick="closeProductoModal()">Cerrar</button>
      </div>
    </div>
  </div>
</div>

<script>
// Toggle móvil
const mq  = window.matchMedia('(max-width:980px)');
const btn = document.getElementById('menuToggle');
const menu= document.getElementById('techMenu');

function upd(){ btn.style.display = mq.matches ? 'flex' : 'none'; }
(mq.addEventListener ? mq.addEventListener('change',upd) : mq.addListener(upd));
upd();

btn?.addEventListener('click', ()=>{
  const open = menu.classList.toggle('open');
  btn.setAttribute('aria-expanded', open ? 'true' : 'false');
});

// Scroll + activo
document.querySelectorAll('.tech-menu a[href^="#"]').forEach(a=>{
  a.addEventListener('click',e=>{
    const t = document.querySelector(a.getAttribute('href'));
    if(t){
      e.preventDefault();
      if(menu.classList.contains('open')) menu.classList.remove('open');
      t.scrollIntoView({behavior:'smooth',block:'start'});
    }
  });
});

const links = [...document.querySelectorAll('.tech-menu a[href^="#"]')];
const secs  = links.map(l=>document.querySelector(l.getAttribute('href'))).filter(Boolean);

const io = new IntersectionObserver(es=>{
  es.forEach(en=>{
    if(en.isIntersecting){
      links.forEach(l=>{
        l.classList.toggle('active', l.getAttribute('href') === ('#'+en.target.id));
      });
    }
  });
},{rootMargin:'-55% 0px -40% 0px',threshold:0});

secs.forEach(s=>io.observe(s));

let __currentCardForBuy = null;

function openWhatsApp(card){
  if(!card) return;
  const nombre = card.dataset.nombre || '';
  const url    = card.dataset.url || location.href;
  const precio = card.dataset.preciovisible || '';
  const desc   = (card.dataset.descripcion || '').trim();

  const msg = `Hola Quiero comprar ${nombre}\nPrecio: ${precio}\n\nDescripción:\n ${desc}\n\nDetalle: ${url}`;
  const wa  = 'https://wa.me/56984226651?text=' + encodeURIComponent(msg);
  window.open(wa,'_blank');
}

function comprarWhatsAppActual(){
  if(__currentCardForBuy) openWhatsApp(__currentCardForBuy);
}

// Modal base
function openProductoModal(card){
  __currentCardForBuy = card;

  const m = document.getElementById('productoModal');
  document.getElementById('mImagen').src = card.dataset.imagen || '';
  document.getElementById('modalTitle').textContent = card.dataset.nombre || '';
  document.getElementById('mRef').textContent   = 'Referencia: ' + (card.dataset.ref   || '');
  document.getElementById('mStock').textContent = 'Stock disponible: ' + (card.dataset.stock || '');
  document.getElementById('mPrecioMayorista').textContent =
    'Precio mayorista: $' + (card.dataset.preciomayorista || '') + ' Imp. incl.';
  document.getElementById('mDesc').textContent  = card.dataset.descripcion || 'Sin descripción';
  m.classList.add('open');
  m.setAttribute('aria-hidden','false');
  document.body.style.overflow='hidden';
}
function closeProductoModal(){
  const m = document.getElementById('productoModal');
  m.classList.remove('open');
  m.setAttribute('aria-hidden','true');
  document.body.style.overflow='';
}
document.getElementById('productoModal').addEventListener('click',e=>{
  if(e.target===e.currentTarget) closeProductoModal();
});
document.addEventListener('keydown',e=>{
  if(e.key==='Escape') closeProductoModal();
});

// Auto-abrir modal si viene ?ref=...
(function(){
  const params = new URLSearchParams(location.search);
  const ref = params.get('ref');
  if(!ref) return;
  if(typeof CSS==='undefined' || typeof CSS.escape!=='function'){
    window.CSS = window.CSS || {};
    CSS.escape = function(s){return (s+'').replace(/"/g,'\\"').replace(/'/g,"\\'");};
  }
  const card = document.querySelector('.producto[data-ref="'+CSS.escape(ref)+'"]');
  if(card){
    openProductoModal(card);
    card.scrollIntoView({behavior:'smooth',block:'center'});
  }
})();
</script>

<script>
// ====== DataLayer helpers ======
window.dataLayer = window.dataLayer || [];
const dl = (o)=>window.dataLayer.push(o);

// Convierte "1.190" o "$1.190 Imp. incl." -> 1190 (CLP)
function moneyToNumber(s){
  if(!s) return 0;
  s = String(s).replace(/[^\d,.,-]/g,'').replace(/\./g,'').replace(',', '.');
  const n = Number(s);
  return isNaN(n) ? 0 : n;
}

// ====== List view (al cargar) ======
document.addEventListener('DOMContentLoaded', () => {
  const cards = Array.from(document.querySelectorAll('.catalogo .producto'));
  if (!cards.length) return;
  const items = cards.slice(0, 50).map((el, i) => ({
    item_id: el.dataset.ref || '',
    item_name: el.dataset.nombre || '',
    item_category: el.closest('section,[id]')?.id || 'catalogo',
    index: i + 1,
    price: moneyToNumber(el.dataset.preciodetalle || el.dataset.preciomayorista)
  }));
  dl({ecommerce:null});
  dl({event:'view_item_list', ecommerce:{items, currency:'CLP'}});
});

// ====== Modal: view_item ======
function trackViewItem(card){
  const price = moneyToNumber(card.dataset.preciodetalle || card.dataset.preciomayorista);
  dl({ecommerce:null});
  dl({
    event:'view_item',
    ecommerce:{
      currency:'CLP',
      value: price,
      items:[{
        item_id: card.dataset.ref || '',
        item_name: card.dataset.nombre || '',
        item_category: card.closest('section,[id]')?.id || 'catalogo',
        price
      }]
    }
  });
}

// Envolver la función existente de modal con tracking
const _openProductoModal = window.openProductoModal;
window.openProductoModal = function(card){
  _openProductoModal(card);
  try{ trackViewItem(card); }catch(e){}
};

// ====== Clics en botones/enlaces ======
document.addEventListener('click', (e)=>{
  const a = e.target.closest('a');
  if(a){
    dl({
      event:'link_click',
      link_url: a.href || '',
      link_text: (a.textContent||'').trim(),
      link_id: a.id || '',
      link_classes: a.className || ''
    });
  }
  const btn = e.target.closest('.btn-reservar, .btn-detalle');
  if(btn){
    const card = btn.closest('.producto');
    const id   = card?.dataset.ref || '';
    const name = card?.dataset.nombre || '';
    const price= moneyToNumber(card?.dataset.preciodetalle || card?.dataset.preciomayorista);

    if(btn.classList.contains('btn-reservar')){
      dl({ecommerce:null});
      dl({
        event:'add_to_cart',
        ecommerce:{ currency:'CLP', value:price, items:[{item_id:id, item_name:name, price}] }
      });
    }else{
      dl({event:'select_item', ecommerce:{items:[{item_id:id, item_name:name}]}}); 
    }
  }
});

// ====== Scroll depth (25/50/75/100%) ======
(function(){
  const sent = {25:false,50:false,75:false,100:false};
  const fire = (p)=>{
    [25,50,75,100].forEach(t=>{
      if(!sent[t] && p>=t){
        sent[t]=true;
        dl({event:'scroll_depth', percent:t});
      }
    });
  };
  const onScroll = ()=>{
    const h = document.documentElement;
    const p = ((window.scrollY + window.innerHeight) / h.scrollHeight) * 100;
    fire(p);
  };
  const thr = (fn, wait)=>{
    let t=0;
    return ()=>{
      const n=Date.now();
      if(n-t>wait){ t=n; fn(); }
    };
  };
  document.addEventListener('scroll', thr(onScroll, 500));
  onScroll();
})();
</script>

</body>
</html>
