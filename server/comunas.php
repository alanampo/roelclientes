<?php
declare(strict_types=1);
header('Content-Type: application/json; charset=UTF-8');

// No requiere autenticación - endpoint público para registro y uso general
require_once __DIR__ . '/../class_lib/class_conecta_mysql.php';

$con = mysqli_connect($host, $user, $password, $dbname);
if (!$con) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Error de conexión a BD']);
    exit;
}
mysqli_query($con, "SET NAMES 'utf8'");

// Obtener todas las comunas
$sql = "SELECT id, nombre, ciudad as region FROM comunas ORDER BY ciudad ASC, nombre ASC";
$result = mysqli_query($con, $sql);

$comunas = [];
while ($row = mysqli_fetch_assoc($result)) {
    $comunas[] = [
        'id' => (int)$row['id'],
        'nombre' => $row['nombre'],
        'region' => $row['region']
    ];
}

mysqli_close($con);

echo json_encode([
    'success' => true,
    'comunas' => $comunas
]);
