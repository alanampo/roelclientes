<?php
declare(strict_types=1);
error_reporting(E_ALL);
ini_set('display_errors', '1');

session_start();
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Headers: Content-Type');

try {
    // Verificar sesión
    if (!isset($_SESSION['id_usuario']) || !$_SESSION['id_usuario']) {
        http_response_code(401);
        echo json_encode(['status' => 'error', 'error' => 'No autenticado']);
        exit;
    }

    require_once __DIR__ . '/../class_lib/class_conecta_mysql.php';

    $con = mysqli_connect($host, $user, $password, $dbname);
    if (!$con) {
        http_response_code(500);
        echo json_encode(['status' => 'error', 'error' => 'Error de conexión a BD']);
        exit;
    }
    mysqli_query($con, "SET NAMES 'utf8'");

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
    $like = "%" . mysqli_real_escape_string($con, $nombre) . "%";

    // Consultar producto con stock disponible
    $sql = "SELECT
                v.id AS id_variedad,
                v.nombre AS nombre,
                CONCAT(t.codigo, LPAD(v.id_interno, 2, '0')) AS referencia,
                v.precio AS precio_mayorista_sin_iva,
                v.precio_detalle AS precio_detalle_sin_iva,
                MAX(av.valor) AS tipo_planta,
                MAX(v.descripcion) AS descripcion,
                (
                    IFNULL(SUM(s.cantidad), 0) -
                    IFNULL((SELECT SUM(r.cantidad) FROM reservas_productos r
                            WHERE r.id_variedad = v.id AND (r.estado = 0 OR r.estado = 1)), 0) -
                    IFNULL((SELECT SUM(e.cantidad) FROM entregas_stock e
                            JOIN reservas_productos r2 ON r2.id = e.id_reserva
                            WHERE r2.id_variedad = v.id AND r2.estado = 2), 0)
                ) AS disponible_para_reservar,
                (
                    SELECT CONCAT('https://control.roelplant.cl/uploads/variedades/variedad_', v.id, '_', iv.nombre_archivo, '.jpeg')
                    FROM imagenes_variedades iv
                    WHERE iv.id_variedad = v.id
                    ORDER BY iv.id DESC
                    LIMIT 1
                ) AS imagen_url
            FROM stock_productos s
            JOIN articulospedidos ap ON ap.id = s.id_artpedido
            JOIN variedades_producto v ON v.id = ap.id_variedad
            JOIN tipos_producto t ON t.id = v.id_tipo
            LEFT JOIN atributos_valores_variedades avv ON avv.id_variedad = v.id
            LEFT JOIN atributos_valores av ON av.id = avv.id_atributo_valor
            LEFT JOIN atributos a ON a.id = av.id_atributo AND a.nombre = 'TIPO DE PLANTA'
            WHERE ap.estado >= 8
              AND (v.eliminada IS NULL OR v.eliminada = 0)
              AND v.nombre LIKE ?
            GROUP BY v.id, v.nombre, v.id_interno, v.precio, v.precio_detalle, t.codigo
            HAVING disponible_para_reservar > 0
            ORDER BY v.nombre ASC
            LIMIT 1";

    $stmt = mysqli_prepare($con, $sql);
    mysqli_stmt_bind_param($stmt, 's', $like);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $producto = mysqli_fetch_assoc($result);

    mysqli_stmt_close($stmt);
    mysqli_close($con);

    if (!$producto) {
        echo json_encode(['status' => 'not_found', 'message' => 'Sin coincidencias']);
        exit;
    }

    // Formatear respuesta
    $detalleNeto = (float)($producto['precio_detalle_sin_iva'] ?? 0);
    $mayorNeto = (float)($producto['precio_mayorista_sin_iva'] ?? 0);

    echo json_encode([
        'status' => 'ok',
        'variedad' => $producto['nombre'],
        'referencia' => $producto['referencia'],
        'tipo_planta' => $producto['tipo_planta'] ?? null,
        'precio' => (int)round($mayorNeto * (1 + $ivaPct)),
        'precio_detalle' => (int)round($detalleNeto * (1 + $ivaPct)),
        'stock' => max(0, (int)($producto['disponible_para_reservar'] ?? 0)),
        'disponible_para_reservar' => max(0, (int)($producto['disponible_para_reservar'] ?? 0)),
        'unidad' => 'plantines',
        'imagen' => $producto['imagen_url'] ?? null,
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
