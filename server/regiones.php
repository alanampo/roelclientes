<?php
declare(strict_types=1);
session_start();
header('Content-Type: application/json; charset=UTF-8');

// Verificar sesión
if (!isset($_SESSION['id_usuario']) || !$_SESSION['id_usuario']) {
    http_response_code(401);
    echo json_encode(['status' => 'error', 'message' => 'No autenticado']);
    exit;
}

require_once __DIR__ . '/../class_lib/class_conecta_mysql.php';

$con = mysqli_connect($host, $user, $password, $dbname);
if (!$con) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Error de conexión a BD']);
    exit;
}
mysqli_query($con, "SET NAMES 'utf8'");

// Obtener regiones únicas (ciudades en la tabla comunas)
$sql = "SELECT DISTINCT ciudad as nombre FROM comunas ORDER BY ciudad ASC";
$result = mysqli_query($con, $sql);

$regiones = [];
while ($row = mysqli_fetch_assoc($result)) {
    $regiones[] = $row['nombre'];
}

mysqli_close($con);

echo json_encode([
    'status' => 'success',
    'data' => ['regiones' => $regiones]
]);
