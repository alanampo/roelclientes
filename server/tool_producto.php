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
    $tipo = trim($data['tipo'] ?? '');

    // LOG para debugging
    error_log('[TOOL_PRODUCTO] Buscando - nombre: ' . $nombre . ', tipo: ' . $tipo);

    if ($nombre === '') {
        http_response_code(400);
        echo json_encode(['status' => 'error', 'error' => 'nombre vacío']);
        exit;
    }

    $ivaPct = 0.19;

    // Hacer búsqueda más flexible: buscar tanto con espacios como sin espacios
    // Esto permite encontrar "MONA LISA" cuando se busca "mona lisa" o "monalisa"
    $nombreSinEspacios = str_replace(' ', '', $nombre);
    $like = "%" . $db->real_escape_string($nombre) . "%";
    $likeSinEspacios = "%" . $db->real_escape_string($nombreSinEspacios) . "%";

    // Preparar filtro de tipo si está presente
    $tipoWhere = '';
    $tipoParam = null;
    if ($tipo !== '') {
        // Usar coincidencia exacta (case-insensitive) para tipos
        $tipoWhere = ' AND UPPER(t.nombre) = ?';
        $tipoParam = strtoupper($db->real_escape_string($tipo));
    }

    // Consultar productos con stock disponible (misma estructura que el catálogo)
    // Ahora retorna TODOS los resultados que coincidan, no solo 1
    $sql = "SELECT
                sv.id_variedad,
                sv.variedad AS nombre,
                sv.referencia,
                sv.tipo AS tipo_producto,
                sv.precio AS precio_mayorista_sin_iva,
                sv.precio_detalle AS precio_detalle_sin_iva,
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
                  AND (v.nombre LIKE ? OR REPLACE(v.nombre, ' ', '') LIKE ?)
                  $tipoWhere
                GROUP BY v.id
            ) AS sv
            JOIN variedades_producto v ON v.id = sv.id_variedad
            LEFT JOIN imagenes_variedades img ON img.id_variedad = sv.id_variedad
            LEFT JOIN atributos_valores_variedades avv ON avv.id_variedad = sv.id_variedad
            LEFT JOIN atributos_valores av ON av.id = avv.id_atributo_valor
            LEFT JOIN atributos a ON a.id = av.id_atributo
            WHERE sv.disponible_para_reservar > 0
            GROUP BY sv.id_variedad
            ORDER BY sv.disponible_para_reservar DESC";

    $stmt = $db->prepare($sql);

    if ($tipoParam !== null) {
        $stmt->bind_param('sss', $like, $likeSinEspacios, $tipoParam);
    } else {
        $stmt->bind_param('ss', $like, $likeSinEspacios);
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

        // Procesar atributos
        $attrsRaw = trim((string)($row['attrs_activos'] ?? ''));
        $attrs = [];
        if ($attrsRaw !== '') {
            foreach (explode('||', $attrsRaw) as $kv) {
                $kv = trim((string)$kv);
                if ($kv !== '') {
                    $parts = explode(':', $kv, 2);
                    if (count($parts) === 2) {
                        $attrs[] = [
                            'nombre' => trim($parts[0]),
                            'valor' => trim($parts[1])
                        ];
                    }
                }
            }
        }

        $items[] = [
            'id_variedad' => (int)($row['id_variedad'] ?? 0),
            'variedad' => $row['nombre'],
            'referencia' => $row['referencia'],
            'tipo_producto' => $row['tipo_producto'] ?? null,
            'tipo_planta' => $row['tipo_planta'] ?? null,
            'attrs' => $attrs,
            'attrs_raw' => $attrsRaw,
            'precio' => (int)round($mayorNeto * (1 + $ivaPct)),
            'precio_detalle' => (int)round($detalleNeto * (1 + $ivaPct)),
            'stock' => max(0, (int)($row['disponible_para_reservar'] ?? 0)),
            'disponible_para_reservar' => max(0, (int)($row['disponible_para_reservar'] ?? 0)),
            'unidad' => 'plantines',
            'imagen' => $imagenUrl,
            'descripcion' => $row['descripcion'] ?? null
        ];
    }

    $stmt->close();

    if (empty($items)) {
        echo json_encode(['status' => 'not_found', 'message' => 'Sin coincidencias']);
        exit;
    }

    // Si hay un solo resultado, retornar como antes (para compatibilidad)
    if (count($items) === 1) {
        echo json_encode(array_merge(['status' => 'ok'], $items[0]), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        exit;
    }

    // Si hay múltiples resultados, retornar array
    echo json_encode([
        'status' => 'multiple',
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
