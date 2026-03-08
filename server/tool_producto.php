<?php
declare(strict_types=1);
error_reporting(E_ALL);
ini_set('display_errors', '1');

// Usar la autenticación del catálogo
require __DIR__ . '/../catalogo_detalle/api/_bootstrap.php';

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Headers: Content-Type');

try {
    // Verificar autenticación del catálogo
    $customerId = require_auth();

    $db = db();

    // Leer input
    $raw = file_get_contents('php://input') ?: '';
    $data = json_decode($raw, true) ?: [];
    $nombre = trim($data['nombre'] ?? '');

    if ($nombre === '') {
        http_response_code(400);
        echo json_encode(['status' => 'error', 'error' => 'nombre vacío']);
        exit;
    }

    $ivaPct = 0.19;
    $like = "%" . $db->real_escape_string($nombre) . "%";

    // Consultar producto con stock disponible (misma estructura que el catálogo)
    $sql = "SELECT
                sv.id_variedad,
                sv.variedad AS nombre,
                sv.referencia,
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
                  AND v.nombre LIKE ?
                GROUP BY v.id
            ) AS sv
            JOIN variedades_producto v ON v.id = sv.id_variedad
            LEFT JOIN imagenes_variedades img ON img.id_variedad = sv.id_variedad
            LEFT JOIN atributos_valores_variedades avv ON avv.id_variedad = sv.id_variedad
            LEFT JOIN atributos_valores av ON av.id = avv.id_atributo_valor
            LEFT JOIN atributos a ON a.id = av.id_atributo AND a.nombre = 'TIPO DE PLANTA'
            WHERE sv.disponible_para_reservar > 0
            GROUP BY sv.id_variedad
            LIMIT 1";

    $stmt = $db->prepare($sql);
    $stmt->bind_param('s', $like);
    $stmt->execute();
    $result = $stmt->get_result();
    $producto = $result->fetch_assoc();

    $stmt->close();

    if (!$producto) {
        echo json_encode(['status' => 'not_found', 'message' => 'Sin coincidencias']);
        exit;
    }

    // Formatear respuesta
    $detalleNeto = (float)($producto['precio_detalle_sin_iva'] ?? 0);
    $mayorNeto = (float)($producto['precio_mayorista_sin_iva'] ?? 0);

    // Construir URL de imagen
    $imagenUrl = null;
    if (!empty($producto['imagen_nombre'])) {
        $imagenUrl = 'https://control.roelplant.cl/uploads/variedades/' . $producto['imagen_nombre'];
    }

    echo json_encode([
        'status' => 'ok',
        'id_variedad' => (int)($producto['id_variedad'] ?? 0),
        'variedad' => $producto['nombre'],
        'referencia' => $producto['referencia'],
        'tipo_planta' => $producto['tipo_planta'] ?? null,
        'precio' => (int)round($mayorNeto * (1 + $ivaPct)),
        'precio_detalle' => (int)round($detalleNeto * (1 + $ivaPct)),
        'stock' => max(0, (int)($producto['disponible_para_reservar'] ?? 0)),
        'disponible_para_reservar' => max(0, (int)($producto['disponible_para_reservar'] ?? 0)),
        'unidad' => 'plantines',
        'imagen' => $imagenUrl,
        'descripcion' => $producto['descripcion'] ?? null
    ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

} catch (\Throwable $th) {
    http_response_code(500);
    echo json_encode([
        'status' => 'error',
        'error' => 'Error del servidor: ' . $th->getMessage(),
        'trace' => $th->getTraceAsString()
    ]);
}
