<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require __DIR__ . '/../class_lib/class_conecta_mysql.php';

$link = mysqli_connect($host, $user, $password, $dbname);
if (!$link) die("Error conexión: " . mysqli_connect_error());
mysqli_set_charset($link, 'utf8');

// Consulta
$sql = "SELECT
            t.nombre AS tipo,
            v.nombre AS variedad,
            CONCAT(t.codigo, LPAD(v.id_interno, 2, '0')) AS referencia,
            v.precio,
            v.precio_detalle,
            (
              SUM(s.cantidad)
              - IFNULL((SELECT SUM(e.cantidad) FROM entregas_stock e JOIN reservas_productos r2 ON e.id_reserva_producto = r2.id WHERE r2.id_variedad = v.id AND r2.estado >= 0), 0)
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
        LIMIT 100";

$resultado = mysqli_query($link, $sql);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Listado de Plantines Disponibles Roelplant</title>
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
        .producto button:hover { background-color: #219150; }
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
                
                <th onclick="sortTable(1)">Variedad</th>
                <th onclick="sortTable(2)">Referencia</th>
                <th onclick="sortTable(3)">Tipo Planta</th>
                <th onclick="sortTable(4)">Stock</th>
                
                <th onclick="sortTable(6)">Precio Imp. Incl.</th>
            </tr>
        </thead>
        <tbody>
            <?php while($row = mysqli_fetch_assoc($resultado)): 
                $precio_mayorista = number_format($row['precio'] * 1.19, 0, ',', '.');
                $precio_detalle = isset($row['precio_detalle']) ? number_format($row['precio_detalle'] * 1.19, 0, ',', '.') : '';
            ?>
            <tr>
                
                <td><?= htmlspecialchars($row['variedad']) ?></td>
                <td><?= htmlspecialchars($row['referencia']) ?></td>
                <td><span class="badge"><?= htmlspecialchars($row['tipo_planta']) ?></span></td>
                <td><?= htmlspecialchars($row['disponible_para_reservar']) ?></td>
                
                <td><?= $precio_detalle ? '$'.$precio_detalle : '-' ?></td>
            </tr>
            <?php endwhile; ?>
        </tbody>
    </table>
</div>

<!-- Bot��n para ver Catalogo -->
<button class="ver-catalogo" onclick="window.location.href='index.php'">
    Ver Catalogo
</button>
</body>
</html>
