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
                v.id AS id_variedad,
                v.nombre AS nombre,
                CONCAT(t.codigo, LPAD(v.id_interno, 2, '0')) AS referencia,
                t.nombre AS tipo_producto,
                MAX(av.valor) AS tipo_planta,
                MAX(v.descripcion) AS descripcion,
                v.precio AS precio_mayorista_sin_iva,
                v.precio_detalle AS precio_detalle_sin_iva,
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
              $whereExtra
            GROUP BY v.id, v.nombre, v.id_interno, v.precio, v.precio_detalle, t.codigo, t.nombre
            HAVING disponible_para_reservar > 0
            ORDER BY disponible_para_reservar DESC, v.nombre ASC
            LIMIT 200";

    $stmt = mysqli_prepare($con, $sql);

    if ($tipo) {
        mysqli_stmt_bind_param($stmt, $types, ...$params);
    }

    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);

    $items = [];
    while ($row = mysqli_fetch_assoc($result)) {
        $detalleNeto = (float)($row['precio_detalle_sin_iva'] ?? 0);
        $mayorNeto = (float)($row['precio_mayorista_sin_iva'] ?? 0);

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
            'imagen' => $row['imagen_url'] ?? null,
            'descripcion' => $row['descripcion'] !== null ? trim((string)$row['descripcion']) : null
        ];
    }

    mysqli_stmt_close($stmt);
    mysqli_close($con);

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
