<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require __DIR__ . '/../class_lib/class_conecta_mysql.php';

$link = mysqli_connect($host, $user, $password, $dbname);
if (!$link) die("Error conexión: " . mysqli_connect_error());
mysqli_set_charset($link, 'utf8');

// Consulta con precios y tipo de planta
$sql = "SELECT
            t.nombre AS tipo,
            v.nombre AS variedad,
            CONCAT(t.codigo, LPAD(v.id_interno, 2, '0')) AS referencia,
            v.precio,
            v.precio_detalle,
            (
              SUM(s.cantidad)
              - IFNULL((SELECT SUM(e.cantidad) FROM entregas_stock e JOIN reservas_productos r2 ON e.id_reserva = r2.id WHERE r2.id_variedad = v.id AND r2.estado >= 0), 0)
              - IFNULL((SELECT SUM(r.cantidad) FROM reservas_productos r WHERE r.id_variedad = v.id AND r.estado >= 0), 0)
            ) AS disponible_para_reservar,
            ANY_VALUE(av.valor) AS tipo_planta
        FROM stock_productos s
        JOIN articulospedidos ap ON ap.id = s.id_artpedido
        JOIN variedades_producto v ON v.id = ap.id_variedad
        JOIN tipos_producto t ON t.id = v.id_tipo
        LEFT JOIN atributos_valores_variedades avv ON avv.id_variedad = v.id
        LEFT JOIN atributos_valores av ON av.id = avv.id_atributo_valor
        LEFT JOIN atributos a ON a.id = av.id_atributo AND a.nombre = 'TIPO DE PLANTA'
        WHERE ap.estado >= 8
        GROUP BY v.id
        HAVING disponible_para_reservar > 0
        ORDER BY disponible_para_reservar DESC
        LIMIT 50";

$resultado = mysqli_query($link, $sql);

// Organizar productos en arrays según tipo_planta
$interior = [];
$exterior = [];
while ($row = mysqli_fetch_assoc($resultado)) {
    $tipo = strtoupper(trim($row['tipo_planta']));
    if ($tipo === 'PLANTAS DE INTERIOR') {
        $interior[] = $row;
    } elseif ($tipo === 'PLANTAS DE EXTERIOR') {
        $exterior[] = $row;
    }
}

// Función para renderizar catálogo
function render_catalogo($productos) {
    foreach ($productos as $producto) {
        $precio_mayorista = number_format($producto['precio'] * 1.19, 0, ',', '.');
        $precio_detalle = isset($producto['precio_detalle']) ? number_format($producto['precio_detalle'] * 1.19, 0, ',', '.') : null;
        ?>
        <div class="producto">
            <img src="https://via.placeholder.com/250x160?text=Imagen+pendiente" alt="Imagen de producto">

            <h3 class="variedad"><?= htmlspecialchars($producto['variedad'], ENT_QUOTES, 'UTF-8'); ?></h3>
            <p><strong>Referencia:</strong> <?= htmlspecialchars($producto['referencia'], ENT_QUOTES, 'UTF-8'); ?></p>
            <p><strong>Stock Disponible:</strong> <?= htmlspecialchars($producto['disponible_para_reservar'], ENT_QUOTES, 'UTF-8'); ?></p>
            <p><strong>Precio Mayorista:</strong> $<?= $precio_mayorista ?>  Imp. Incl.</p>
            <?php if ($precio_detalle): ?>
            <?php endif; ?>
            <button onclick="window.location.href='https://clientes.roelplant.cl/'">Reservar</button>
        </div>
        <?php
    }
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Catálogo Roelplant</title>
    <style>
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background-color: #f4f4f4;
            padding: 20px;
        }
        h1 {
            text-align: center;
            color: #2c3e50;
            font-size: 2.5em;
            margin: 20px 0 30px;
        }
        h2 {
            text-align: center;
            color: #2c3e50;
            margin: 40px 0 20px;
        }
        .catalogo {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            max-width: 1200px;
            margin: 0 auto;
        }
        .producto {
            background: white;
            border-radius: 10px;
            box-shadow: 0 4px 10px rgba(0,0,0,0.1);
            padding: 15px;
            text-align: center;
            transition: transform 0.2s ease;
            border-top: 5px solid #27ae60; /* borde superior verde */
        }
        .producto:hover { transform: scale(1.02); }
        .producto img {
            width: 100%;
            height: 160px;
            object-fit: cover;
            border-radius: 8px;
            margin-bottom: 12px;
            background: #e0e0e0;
        }
        .variedad {
            text-transform: uppercase;
            font-weight: bold;
            color: #27ae60;
            margin: 10px 0 10px;
            font-size: 1.2em;
        }
        .producto p { color: #555; margin: 5px 0; }
        .producto strong { color: #000; }
        .producto button {
            background-color: #27ae60;
            color: white;
            padding: 10px 20px;
            margin-top: 10px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
        }
        .producto button:hover { background-color: #219150; }
        .ver-listado {
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
        .ver-listado:hover {
            background-color: #1f6690;
        }
    </style>
</head>
<body>
<h1>Catálogo Mayorista Roelplant</h1>
<h2>Catálogo de Plantines Disponibles de Interior</h2>
<div class="catalogo">
    <?php render_catalogo($interior); ?>
</div>

<h2>Catálogo de Plantines Disponibles de Exterior</h2>
<div class="catalogo">
    <?php render_catalogo($exterior); ?>
</div>

<!-- Botón para ver listado -->
<button class="ver-listado" onclick="window.location.href='catalogo_tabla.php'">
    Ver listado
</button>

</body>
</html>
