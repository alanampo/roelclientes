<?php

header('Content-type: application/json; charset=utf-8');
error_reporting(0);

require 'class_lib/class_conecta_mysql.php';
require 'class_lib/funciones.php';

$con = mysqli_connect($host, $user, $password, $dbname);
if (!$con) {
    echo json_encode(['success' => false, 'message' => 'Error de conexión a la base de datos']);
    exit;
}

mysqli_query($con, "SET NAMES 'utf8'");

$consulta = $_POST["consulta"] ?? '';

// Verificar si el email ya existe
if ($consulta == "check_email") {
    $email = trim($_POST['email'] ?? '');

    if (empty($email)) {
        echo json_encode(['exists' => false]);
        exit;
    }

    $stmt = mysqli_prepare($con, "SELECT id FROM usuarios WHERE nombre = ? LIMIT 1");
    mysqli_stmt_bind_param($stmt, 's', $email);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);

    echo json_encode(['exists' => mysqli_num_rows($result) > 0]);
    mysqli_stmt_close($stmt);
    exit;
}

// Verificar si el RUT ya existe
if ($consulta == "check_rut") {
    $rut = trim($_POST['rut'] ?? '');

    if (empty($rut)) {
        echo json_encode(['exists' => false]);
        exit;
    }

    $stmt = mysqli_prepare($con, "SELECT id_cliente FROM clientes WHERE rut = ? LIMIT 1");
    mysqli_stmt_bind_param($stmt, 's', $rut);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);

    echo json_encode(['exists' => mysqli_num_rows($result) > 0]);
    mysqli_stmt_close($stmt);
    exit;
}

// Procesar el registro completo
if ($consulta == "register") {
    // Obtener y validar datos
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $nombre = trim($_POST['nombre'] ?? '');
    $rut = trim($_POST['rut'] ?? '');
    $telefono = trim($_POST['telefono'] ?? '');
    $domicilio = trim($_POST['domicilio'] ?? '');
    $domicilio2 = trim($_POST['domicilio2'] ?? '');
    $comuna = intval($_POST['comuna'] ?? 0);
    $ciudad = trim($_POST['ciudad'] ?? '');
    $provincia = trim($_POST['provincia'] ?? '');
    $region = trim($_POST['region'] ?? '');

    // Validar campos requeridos
    if (empty($email) || empty($password) || empty($nombre) || empty($rut) ||
        empty($telefono) || empty($domicilio) || $comuna == 0 || empty($ciudad)) {
        echo json_encode(['success' => false, 'message' => 'Todos los campos requeridos deben ser completados']);
        exit;
    }

    // Validar formato de email
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        echo json_encode(['success' => false, 'message' => 'Formato de email inválido']);
        exit;
    }

    // Validar RUT formato
    if (!validarRUT($rut)) {
        echo json_encode(['success' => false, 'message' => 'Formato de RUT inválido']);
        exit;
    }

    // Verificar que el email no exista
    $stmt = mysqli_prepare($con, "SELECT id FROM usuarios WHERE nombre = ? LIMIT 1");
    mysqli_stmt_bind_param($stmt, 's', $email);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    if (mysqli_num_rows($result) > 0) {
        mysqli_stmt_close($stmt);
        echo json_encode(['success' => false, 'message' => 'El email ya está registrado']);
        exit;
    }
    mysqli_stmt_close($stmt);

    // Verificar que el RUT no exista
    $stmt = mysqli_prepare($con, "SELECT id_cliente FROM clientes WHERE rut = ? LIMIT 1");
    mysqli_stmt_bind_param($stmt, 's', $rut);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    if (mysqli_num_rows($result) > 0) {
        mysqli_stmt_close($stmt);
        echo json_encode(['success' => false, 'message' => 'El RUT ya está registrado']);
        exit;
    }
    mysqli_stmt_close($stmt);

    // Hash de la contraseña
    $passwordHash = password_hash($password, PASSWORD_DEFAULT);

    // Validar que la región seleccionada coincida con la región de la comuna
    $stmt = mysqli_prepare($con, "SELECT ciudad FROM comunas WHERE id = ? LIMIT 1");
    mysqli_stmt_bind_param($stmt, 'i', $comuna);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    if (!$row = mysqli_fetch_assoc($result)) {
        mysqli_stmt_close($stmt);
        echo json_encode(['success' => false, 'message' => 'Comuna no encontrada']);
        exit;
    }
    mysqli_stmt_close($stmt);

    $comunaRegion = $row['ciudad']; // La BD almacena la región como 'ciudad'

    // Validar que la región enviada coincida con la región de la comuna
    if ($region !== $comunaRegion) {
        echo json_encode(['success' => false, 'message' => 'La región seleccionada no coincide con la comuna']);
        exit;
    }

    // Iniciar transacción
    mysqli_begin_transaction($con);

    try {
        // 1. Insertar en la tabla clientes
        $stmt = mysqli_prepare($con,
            "INSERT INTO clientes (nombre, razon_social, rut, domicilio, domicilio2, ciudad, comuna, provincia, region, telefono, mail, activo)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1)"
        );

        $razon_social = $nombre; // Usar el mismo nombre como razón social

        mysqli_stmt_bind_param($stmt, 'ssssssissss',
            $nombre,
            $razon_social,
            $rut,
            $domicilio,
            $domicilio2,
            $ciudad,
            $comuna,
            $provincia,
            $region,
            $telefono,
            $email
        );

        if (!mysqli_stmt_execute($stmt)) {
            throw new Exception('Error al crear el cliente: ' . mysqli_stmt_error($stmt));
        }

        $id_cliente = mysqli_insert_id($con);
        mysqli_stmt_close($stmt);

        // 2. Insertar en la tabla usuarios
        $stmt = mysqli_prepare($con,
            "INSERT INTO usuarios (nombre, nombre_real, password, tipo_usuario, id_cliente)
             VALUES (?, ?, ?, 0, ?)"
        );

        mysqli_stmt_bind_param($stmt, 'sssi', $email, $nombre, $passwordHash, $id_cliente);

        if (!mysqli_stmt_execute($stmt)) {
            throw new Exception('Error al crear el usuario: ' . mysqli_stmt_error($stmt));
        }

        mysqli_stmt_close($stmt);

        // Confirmar transacción
        mysqli_commit($con);

        echo json_encode([
            'success' => true,
            'message' => 'Registro exitoso',
            'id_cliente' => $id_cliente
        ]);

    } catch (Exception $e) {
        // Revertir transacción en caso de error
        mysqli_rollback($con);
        echo json_encode([
            'success' => false,
            'message' => 'Error al procesar el registro: ' . $e->getMessage()
        ]);
    }

    exit;
}

// Si no se reconoce la consulta
echo json_encode(['success' => false, 'message' => 'Consulta no reconocida']);

// Función para validar RUT chileno
function validarRUT($rut) {
    // Limpiar el RUT
    $rut = preg_replace('/[^0-9kK]/', '', $rut);

    if (strlen($rut) < 2) {
        return false;
    }

    $dv = strtolower(substr($rut, -1));
    $number = intval(substr($rut, 0, -1));

    if ($number == 0) {
        return false;
    }

    // Calcular dígito verificador
    $suma = 0;
    $multiplo = 2;

    $numberStr = strval($number);
    for ($i = strlen($numberStr) - 1; $i >= 0; $i--) {
        $suma += intval($numberStr[$i]) * $multiplo;
        $multiplo = $multiplo == 7 ? 2 : $multiplo + 1;
    }

    $resto = $suma % 11;
    $dvCalculado = 11 - $resto;

    if ($dvCalculado == 11) {
        $dvEsperado = '0';
    } else if ($dvCalculado == 10) {
        $dvEsperado = 'k';
    } else {
        $dvEsperado = strval($dvCalculado);
    }

    return $dv === $dvEsperado;
}
