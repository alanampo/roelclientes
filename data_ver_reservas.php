<?php

include "./class_lib/sesionSecurity.php";

error_reporting(0);
require 'class_lib/class_conecta_mysql.php';
require 'class_lib/funciones.php';

$con = mysqli_connect($host, $user, $password, $dbname);
// Check connection
if (!$con) {
    die("Connection failed: " . mysqli_connect_error());
}
mysqli_query($con, "SET NAMES 'utf8'");
$consulta = $_POST["consulta"];

if ($consulta == "busca_stock_actual") {
    $query = "SELECT
  t.nombre as nombre_tipo,
  v.nombre as nombre_variedad,
  t.id as id_tipo,
  t.codigo,
  v.id_interno,
  v.precio_detalle as precio,
  v.id as id_variedad,
  SUM(s.cantidad) as cantidad,
  (SELECT IFNULL(SUM(r.cantidad),0) FROM reservas_productos r
        WHERE r.id_variedad = v.id AND (r.estado = 0 OR r.estado = 1)) as cantidad_reservada,
  (SELECT IFNULL(SUM(e.cantidad),0) FROM entregas_stock e
        INNER JOIN reservas_productos rp ON e.id_reserva_producto = rp.id
        WHERE rp.id_variedad = v.id AND rp.estado = 2) as cantidad_entregada
  FROM stock_productos s
  INNER JOIN articulospedidos ap
  ON s.id_artpedido = ap.id
  INNER JOIN variedades_producto v
  ON v.id = ap.id_variedad
  INNER JOIN tipos_producto t
  ON t.id = v.id_tipo
  WHERE ap.estado >= 8 AND v.precio_detalle IS NOT NULL AND v.precio_detalle > 0
  GROUP BY v.id;
          ";

    $val = mysqli_query($con, $query);

    if (mysqli_num_rows($val) > 0) {

        echo "<div class='box box-primary'>";
        echo "<div class='box-header with-border'>";
        echo "<h3 class='box-title'>Stock Actual</h3>";
        echo "<div class='box-tools pull-right'>";
        echo "<button class='btn btn-success' onclick='modalReservar()'><i class='fa fa-shopping-basket'></i> CREAR RESERVA</button>";
        echo "</div>";
        echo "</div>";
        echo "<div class='box-body'>";
        echo "<table id='tabla' class='table table-bordered table-responsive w-100 d-block d-md-table'>";
        echo "<thead>";
        echo "<tr>";
        echo "<th>Producto</th><th>Precio Unitario</th><th>Cant. Disponible Plantas</th>";
        echo "</tr>";
        echo "</thead>";
        echo "<tbody>";

        while ($ww = mysqli_fetch_array($val)) {
            $disponible = ((int) $ww["cantidad"] - (int) $ww["cantidad_reservada"] - (int) $ww["cantidad_entregada"]);

            if ($disponible > 0) {
                $cantidad = ($disponible <= 50 ? "<span class='text-danger font-weight-bold'>$disponible</span>" : "<span class='font-weight-bold'>$disponible</span>");
                echo "
          <tr class='text-center' style='cursor:pointer'>
            <td>$ww[nombre_variedad] ($ww[codigo]$ww[id_interno])</td>
            <td>$" . number_format($ww["precio"], 0, ',', '.') . "</td>
            <td>$cantidad</td>
          </tr>";
            }
        }
        echo "</tbody>";
        echo "</table>";
        echo "</div>";
        echo "</div>";
    } else {
        echo "<div class='callout callout-danger'><b>No se encontraron productos en stock...</b></div>";
    }
} else if ($consulta == "get_productos_para_reserva") {
    $query = "SELECT
        v.id as id_variedad,
        v.nombre as nombre_variedad,
        t.codigo,
        v.id_interno,
        (SUM(s.cantidad) - 
         IFNULL((SELECT SUM(r.cantidad) FROM reservas_productos r WHERE r.id_variedad = v.id AND r.estado >= 0), 0)
        ) as disponible
    FROM stock_productos s
    INNER JOIN articulospedidos ap ON s.id_artpedido = ap.id
    INNER JOIN variedades_producto v ON v.id = ap.id_variedad
    INNER JOIN tipos_producto t ON t.id = v.id_tipo
    WHERE ap.estado >= 8
    GROUP BY v.id
    HAVING disponible > 0;
    ";
    $val = mysqli_query($con, $query);
    $productos = array();
    while ($ww = mysqli_fetch_assoc($val)) {
        $productos[] = $ww;
    }
    echo json_encode($productos);
} else if ($consulta == "busca_reservas") {
    $query = "SELECT
            r.id as id_reserva,
            r.observaciones,
            DATE_FORMAT(r.fecha, '%d/%m/%y %H:%i') as fecha,
            DATE_FORMAT(r.fecha, '%Y%m%d%H%i') as fecha_raw,
            (SELECT MIN(rp.estado) FROM reservas_productos rp WHERE rp.id_reserva = r.id) as estado
            FROM
            reservas r
            WHERE r.id_cliente = $_SESSION[id_cliente]
            ORDER BY r.fecha DESC
            ;
        ";

    $val = mysqli_query($con, $query);

    if (mysqli_num_rows($val) > 0) {

        echo "<div class='box box-primary'>";
        echo "<div class='box-header with-border'>";
        echo "<h3 class='box-title'>Mis Compras</h3>";
        echo "</div>";
        echo "<div class='box-body'>";
        echo "<table id='tabla-reservas' class='table table-bordered table-responsive w-100 d-block d-md-table'>";
        echo "<thead>";
        echo "<tr>";
        echo "<th>ID</th><th>Fecha Reserva</th><th>Productos</th><th>Observaciones</th><th>Estado</th><th></th>";
        echo "</tr>";
        echo "</thead>";
        echo "<tbody>";

        while ($ww = mysqli_fetch_array($val)) {
            $id_reserva = $ww['id_reserva'];
            
            $productos_query = "SELECT 
                                    rp.id as id_reserva_producto,
                                    v.nombre as nombre_variedad,
                                    t.codigo,
                                    v.id_interno,
                                    rp.cantidad,
                                    rp.id_variedad,
                                    (SELECT IFNULL(SUM(e.cantidad),0) FROM entregas_stock e WHERE e.id_reserva_producto = rp.id) as cantidad_entregada,
                                    rp.estado
                                FROM reservas_productos rp
                                INNER JOIN variedades_producto v ON v.id = rp.id_variedad
                                INNER JOIN tipos_producto t ON t.id = v.id_tipo
                                WHERE rp.id_reserva = $id_reserva";

            $productos_result = mysqli_query($con, $productos_query);
            $productos_html = "<ul class='list-group'>";
            
            while ($producto = mysqli_fetch_array($productos_result)) {
                $nombre_prod = "$producto[nombre_variedad] ($producto[codigo]$producto[id_interno])";
                $estado_producto = boxEstadoReserva($producto['estado'], true);
                $cantidad_entregada_info = $producto['cantidad_entregada'] > 0 ? " (Entregado: {$producto['cantidad_entregada']})" : "";
                
                $productos_html .= "<li class='list-group-item d-flex justify-content-between align-items-center'>";
                $productos_html .= "<div>{$nombre_prod} - Cant: {$producto['cantidad']}{$cantidad_entregada_info} <span class='badge' style='background-color: unset;color:black;'>{$estado_producto}</span></div>";
                $productos_html .= "</li>";
            }
            $productos_html .= "</ul>";

            $estado_general = boxEstadoReserva($ww["estado"], true);
            $btn_cancelar = ($ww["estado"] == 0 ? "<button onclick='cancelarReserva($id_reserva)' class='btn btn-danger btn-sm mb-2' title='Cancelar Compra'><i class='fa fa-ban'></i></button>" : "");

            echo "
            <tr class='text-center'>
              <td><small>$id_reserva</small></td>
              <td><span style='display:none'>$ww[fecha_raw]</span>$ww[fecha]</td>
              <td class='text-left'>$productos_html</td>
              <td class='text-left'>$ww[observaciones]</td>
              <td>{$estado_general}</td>
              <td>
                <div class='d-flex flex-column'>
                  $btn_cancelar
                </div>
              </td>
            </tr>";
        }
        echo "</tbody>";
        echo "</table>";
        echo "</div>";
        echo "</div>";
    } else {
        echo "<div class='callout callout-danger'><b>No se encontraron reservas...</b></div>";
    }
}else if ($consulta == "guardar_reserva") {
    $observaciones = mysqli_real_escape_string($con, $_POST["observaciones"]);
    $productos = json_decode($_POST["productos"], true);

    try {
        mysqli_autocommit($con, false);
        $errors = array();

        // 1. Validar stock para todos los productos ANTES de insertar nada.
        foreach ($productos as $producto) {
            $id_variedad = (int) $producto['id_variedad'];
            $cantidad_solicitada = (int) $producto['cantidad'];

            $query_stock = "SELECT
                (SUM(s.cantidad) - 
                 IFNULL((SELECT SUM(r.cantidad) FROM reservas_productos r WHERE r.id_variedad = $id_variedad AND r.estado >= 0), 0)
                ) as disponible
                FROM stock_productos s
                INNER JOIN articulospedidos ap ON s.id_artpedido = ap.id
                WHERE ap.id_variedad = $id_variedad AND ap.estado >= 8";

            $res_stock = mysqli_query($con, $query_stock);
            $stock_data = mysqli_fetch_assoc($res_stock);
            $disponible = (int) $stock_data['disponible'];

            if ($disponible < $cantidad_solicitada) {
                $errors[] = "Stock insuficiente para el producto con ID $id_variedad. Solicitado: $cantidad_solicitada, Disponible: $disponible";
            }
        }

        if (count($errors) > 0) {
            mysqli_rollback($con);
            echo "error: " . implode("; ", $errors);
            exit;
        }

        // Determine id_usuario
        $id_cliente_session = $_SESSION['id_cliente'];
        $id_usuario_for_reservas_table = null; // Will be set if a linked user is found
        $id_usuario_for_productos_table = 'NULL'; // Default to NULL for reservas_productos

        $query_get_id_usuario = "SELECT id FROM usuarios WHERE id_cliente = $id_cliente_session LIMIT 1";
        $res_get_id_usuario = mysqli_query($con, $query_get_id_usuario);
        if ($res_get_id_usuario && mysqli_num_rows($res_get_id_usuario) > 0) {
            $user_row = mysqli_fetch_assoc($res_get_id_usuario);
            $id_usuario_for_reservas_table = $user_row['id'];
            $id_usuario_for_productos_table = $user_row['id']; // Also use for productos if found
        } else {
            // No user found linked to this client, so for 'reservas' table, we error out as per user's request.
            $errors[] = "No se encontró un usuario asociado al cliente para crear la compra. La compra no puede ser creada.";
        }

        if (count($errors) > 0) {
            mysqli_rollback($con);
            echo "error: " . implode("; ", $errors);
            exit;
        }

        $query_reserva = "INSERT INTO reservas (fecha, id_cliente, observaciones, id_usuario) VALUES (NOW(), $id_cliente_session, '$observaciones', $id_usuario_for_reservas_table)";

        if (!mysqli_query($con, $query_reserva)) {
            $errors[] = "Error al crear la reserva: " . mysqli_error($con);
        } else {
            $id_reserva = mysqli_insert_id($con);

            foreach ($productos as $producto) {
                $id_variedad = (int) $producto['id_variedad'];
                $cantidad = (int) $producto['cantidad'];
                $comentario = mysqli_real_escape_string($con, $producto['comentario']);

                // Use the determined id_usuario or NULL for reservas_productos
                $query_producto = "INSERT INTO reservas_productos (id_reserva, id_variedad, cantidad, comentario, estado, origen, id_usuario) VALUES ($id_reserva, $id_variedad, $cantidad, '$comentario', 0, 'PANEL CLIENTE', $id_usuario_for_productos_table)";

                if (!mysqli_query($con, $query_producto)) {
                    $errors[] = "Error al comprar producto ID $id_variedad: " . mysqli_error($con);
                }
            }
        }

        // 3. Commit o Rollback final.
        if (count($errors) === 0) {
            if (mysqli_commit($con)) {
                echo "success";
            } else {
                mysqli_rollback($con);
                echo "error: No se pudo confirmar la transacción.";
            }
        } else {
            mysqli_rollback($con);
            echo "error: " . implode("; ", $errors);
        }

    } catch (\Throwable $th) {
        mysqli_rollback($con);
        echo "error: " . $th->getMessage();
    } finally {
        mysqli_close($con);
    }
} else if ($consulta == "cancelar_reserva") {
    $id_reserva = $_POST["id_reserva"];

    try {
        mysqli_autocommit($con, false);
        $errors = array();

        // Verificar que la compra pertenezca al cliente de la sesión
        $query_check_owner = "SELECT id_cliente FROM reservas WHERE id = $id_reserva";
        $res_check_owner = mysqli_query($con, $query_check_owner);
        if(mysqli_num_rows($res_check_owner) > 0){
            $row = mysqli_fetch_assoc($res_check_owner);
            if($row['id_cliente'] != $_SESSION['id_cliente']){
                $errors[] = "No tienes permisos para cancelar esta reserva.";
            }
        } else {
            $errors[] = "La reserva no existe.";
        }

        // Verificar que la reserva no esté ya cancelada o entregada
        if(count($errors) == 0){
            $query_check = "SELECT * FROM reservas_productos WHERE id_reserva = $id_reserva AND estado >= 2";
            $res_check = mysqli_query($con, $query_check);
            if (mysqli_num_rows($res_check) > 0) {
                $errors[] = "La compra contiene productos que ya fueron procesados, no se puede cancelar.";
            }
        }

        if (count($errors) == 0) {
            // Actualizar estado de todos los productos de la reserva a cancelada (-1)
            $query = "UPDATE reservas_productos SET estado = -1 WHERE id_reserva = $id_reserva AND estado < 2";
            if (!mysqli_query($con, $query)) {
                $errors[] = mysqli_error($con);
            }
        }

        if (count($errors) === 0) {
            if (mysqli_commit($con)) {
                echo "success";
            } else {
                mysqli_rollback($con);
                echo "error: No se pudo confirmar la transacción";
            }
        } else {
            mysqli_rollback($con);
            echo "error: " . implode(", ", $errors);
        }

    } catch (\Throwable $th) {
        mysqli_rollback($con);
        echo "error: " . $th->getMessage();
    } finally {
        mysqli_close($con);
    }
}