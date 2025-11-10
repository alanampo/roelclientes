<?php
declare(strict_types=1);
session_start();
header('Content-Type: application/json; charset=UTF-8');

try {
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

    $id_usuario = (int) $_SESSION['id_usuario'];
    $method = $_SERVER['REQUEST_METHOD'];

    // GET - Obtener datos del cliente
    if ($method === 'GET') {
        $sql = "SELECT c.* FROM clientes c
            JOIN usuarios u ON u.id_cliente = c.id_cliente
            WHERE u.id = ?";

        $stmt = mysqli_prepare($con, $sql);
        mysqli_stmt_bind_param($stmt, 'i', $id_usuario);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        $cliente = mysqli_fetch_assoc($result);

        mysqli_stmt_close($stmt);
        mysqli_close($con);

        if (!$cliente) {
            echo json_encode(['status' => 'success', 'data' => ['cliente' => null]]);
            exit;
        }

        echo json_encode([
            'status' => 'success',
            'data' => ['cliente' => $cliente]
        ]);
        exit;
    }

    // PUT - Actualizar datos del cliente
    if ($method === 'PUT') {
        $data = json_decode(file_get_contents('php://input'), true);

        // Validar datos requeridos
        if (empty($data['nombre'])) {
            http_response_code(400);
            echo json_encode(['status' => 'error', 'message' => 'Nombre es requerido']);
            exit;
        }

        // Verificar si el usuario ya tiene un cliente asociado
        $sql = "SELECT id_cliente FROM usuarios WHERE id = ?";
        $stmt = mysqli_prepare($con, $sql);
        mysqli_stmt_bind_param($stmt, 'i', $id_usuario);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        $usuario = mysqli_fetch_assoc($result);
        mysqli_stmt_close($stmt);

        if ($usuario && $usuario['id_cliente']) {
            // UPDATE - Cliente existente
            $id_cliente = (int) $usuario['id_cliente'];

            $sql = "UPDATE clientes SET
                nombre = ?,
                rut = ?,
                mail = ?,
                telefono = ?,
                domicilio = ?,
                domicilio2 = ?,
                region = ?,
                provincia = ?,
                comuna = ?
                WHERE id_cliente = ?";

            $stmt = mysqli_prepare($con, $sql);
            mysqli_stmt_bind_param(
                $stmt,
                'ssssssssii',
                $data['nombre'],
                $data['rut'] ?? '',
                $data['mail'] ?? '',
                $data['telefono'] ?? '',
                $data['domicilio'] ?? '',
                $data['domicilio2'] ?? '',
                $data['region'] ?? '',
                $data['provincia'] ?? '',
                $data['comuna'] ?? 0,
                $id_cliente
            );

            if (!mysqli_stmt_execute($stmt)) {
                mysqli_stmt_close($stmt);
                mysqli_close($con);
                http_response_code(500);
                echo json_encode(['status' => 'error', 'message' => 'Error al actualizar cliente']);
                exit;
            }

            mysqli_stmt_close($stmt);

        } else {
            // INSERT - Nuevo cliente
            $sql = "INSERT INTO clientes (nombre, rut, mail, telefono, domicilio, domicilio2, region, provincia, comuna, activo)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 1)";

            $stmt = mysqli_prepare($con, $sql);
            mysqli_stmt_bind_param(
                $stmt,
                'ssssssssi',
                $data['nombre'],
                $data['rut'] ?? '',
                $data['mail'] ?? '',
                $data['telefono'] ?? '',
                $data['domicilio'] ?? '',
                $data['domicilio2'] ?? '',
                $data['region'] ?? '',
                $data['provincia'] ?? '',
                $data['comuna'] ?? 0
            );

            if (!mysqli_stmt_execute($stmt)) {
                mysqli_stmt_close($stmt);
                mysqli_close($con);
                http_response_code(500);
                echo json_encode(['status' => 'error', 'message' => 'Error al crear cliente']);
                exit;
            }

            $id_cliente = mysqli_insert_id($con);
            mysqli_stmt_close($stmt);

            // Asociar el cliente al usuario
            $sql = "UPDATE usuarios SET id_cliente = ? WHERE id = ?";
            $stmt = mysqli_prepare($con, $sql);
            mysqli_stmt_bind_param($stmt, 'ii', $id_cliente, $id_usuario);
            mysqli_stmt_execute($stmt);
            mysqli_stmt_close($stmt);

            // Actualizar la sesión
            $_SESSION['id_cliente'] = $id_cliente;
        }

        // Obtener el cliente actualizado
        $sql = "SELECT * FROM clientes WHERE id_cliente = ?";
        $stmt = mysqli_prepare($con, $sql);
        $id_cliente_final = $usuario['id_cliente'] ?? $id_cliente;
        mysqli_stmt_bind_param($stmt, 'i', $id_cliente_final);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        $cliente = mysqli_fetch_assoc($result);
        mysqli_stmt_close($stmt);

        mysqli_close($con);

        echo json_encode([
            'status' => 'success',
            'message' => 'Cliente actualizado exitosamente',
            'data' => ['cliente' => $cliente]
        ]);
        exit;
    }

    http_response_code(405);
    echo json_encode(['status' => 'error', 'message' => 'Método no permitido']);

} catch (\Throwable $th) {
    die($th->getMessage()." ".$th->getTraceAsString());
}
