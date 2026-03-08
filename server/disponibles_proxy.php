<?php
declare(strict_types=1);
error_reporting(E_ALL);
ini_set('display_errors', '1');

// Limpiar cache de PHP si está disponible
if (function_exists('opcache_reset')) {
    opcache_reset();
}
clearstatcache(true);

// Usar la autenticación del catálogo
require __DIR__ . '/../catalogo_detalle/api/_bootstrap.php';

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Headers: Content-Type');

try {
    // Verificar autenticación del catálogo
    $customerId = require_auth();

    $db = db();

    // Leer parámetro de tipo
    $tipo = $_GET['tipo'] ?? null;
    if (!$tipo) {
        $raw = file_get_contents('php://input') ?: '';
        if ($raw) {
            $j = json_decode($raw, true);
            if (is_array($j)) $tipo = $j['tipo'] ?? null;
        }
    }

    $ivaPct = 0.19;

    // Construir consulta con filtro opcional por tipo
    $whereExtra = '';
    $params = [];
    $types = '';

    if ($tipo) {
        $tipoNorm = '%' . strtoupper(trim($tipo)) . '%';
        $whereExtra = ' AND (av.valor LIKE ? OR t.nombre LIKE ?)';
        $params = [$tipoNorm, $tipoNorm];
        $types = 'ss';
    }

    $sql = "SELECT
                sv.id_variedad,
                sv.variedad AS nombre,
                sv.referencia,
                sv.tipo AS tipo_producto,
                sv.precio AS precio_mayorista_sin_iva,
                sv.precio_detalle AS precio_detalle_sin_iva,
                sv.disponible_para_reservar,
                MAX(av.valor) AS tipo_planta,
                MIN(v.descripcion) AS descripcion,
                MIN(img.nombre_archivo) AS imagen_nombre
            FROM (
                SELECT
                    v.id AS id_variedad,
                    v.nombre AS variedad,
                    CONCAT(t.codigo, LPAD(v.id_interno, 4, '0')) AS referencia,
                    t.nombre AS tipo,
                    v.precio,
                    v.precio_detalle,
                    (
                        SUM(s.cantidad)
                        - IFNULL((
                            SELECT SUM(r.cantidad)
                            FROM reservas_productos r
                            WHERE r.id_variedad = v.id
                              AND (r.estado = 0 OR r.estado = 1)
                        ), 0)
                        - IFNULL((
                            SELECT SUM(e.cantidad)
                            FROM entregas_stock e
                            JOIN reservas_productos r2 ON r2.id = e.id_reserva_producto
                            WHERE r2.id_variedad = v.id
                              AND r2.estado = 2
                        ), 0)
                    ) AS disponible_para_reservar
                FROM stock_productos s
                JOIN articulospedidos ap ON ap.id = s.id_artpedido
                JOIN variedades_producto v ON v.id = ap.id_variedad
                JOIN tipos_producto t ON t.id = v.id_tipo
                WHERE ap.estado = 8
                GROUP BY v.id
            ) AS sv
            JOIN variedades_producto v ON v.id = sv.id_variedad
            LEFT JOIN imagenes_variedades img ON img.id_variedad = sv.id_variedad
            LEFT JOIN atributos_valores_variedades avv ON avv.id_variedad = sv.id_variedad
            LEFT JOIN atributos_valores av ON av.id = avv.id_atributo_valor
            LEFT JOIN atributos a ON a.id = av.id_atributo AND a.nombre = 'TIPO DE PLANTA'
            WHERE sv.disponible_para_reservar > 0
              $whereExtra
            GROUP BY v.id, v.nombre, v.id_interno, v.precio, v.precio_detalle, t.codigo, t.nombre
            HAVING disponible_para_reservar > 0
            ORDER BY disponible_para_reservar DESC, v.nombre ASC
            LIMIT 200";

    $stmt = $db->prepare($sql);

    if ($tipo) {
        $stmt->bind_param($types, ...$params);
    }

    $stmt->execute();
    $result = $stmt->get_result();

    $items = [];
    while ($row = $result->fetch_assoc()) {
        $detalleNeto = (float)($row['precio_detalle_sin_iva'] ?? 0);
        $mayorNeto = (float)($row['precio_mayorista_sin_iva'] ?? 0);

        // Construir URL de imagen
        $imagenUrl = null;
        if (!empty($row['imagen_nombre'])) {
            $imagenUrl = 'https://control.roelplant.cl/uploads/variedades/' . $row['imagen_nombre'];
        }

        $items[] = [
            'status' => 'ok',
            'id_variedad' => (int)$row['id_variedad'],
            'variedad' => $row['nombre'],
            'referencia' => $row['referencia'],
            'tipo_planta' => $row['tipo_planta'] ?: $row['tipo_producto'],
            'precio' => (int)round($mayorNeto * (1 + $ivaPct)),
            'precio_detalle' => (int)round($detalleNeto * (1 + $ivaPct)),
            'stock' => max(0, (int)$row['disponible_para_reservar']),
            'disponible_para_reservar' => max(0, (int)$row['disponible_para_reservar']),
            'unidad' => 'plantines',
            'imagen' => $imagenUrl,
            'descripcion' => $row['descripcion'] !== null ? trim((string)$row['descripcion']) : null
        ];
    }

    $stmt->close();

    echo json_encode([
        'status' => 'ok',
        'count' => count($items),
        'items' => $items
    ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

} catch (\Throwable $th) {
    http_response_code(500);
    echo json_encode([
        'status' => 'error',
        'error' => 'Error del servidor: ' . $th->getMessage(),
        'trace' => $th->getTraceAsString()
    ]);
}
