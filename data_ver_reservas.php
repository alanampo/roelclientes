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
  v.precio,
  v.id as id_variedad,
  SUM(s.cantidad) as cantidad,
  (SELECT IFNULL(SUM(r.cantidad),0) FROM reservas_productos r
        WHERE r.id_variedad = v.id AND r.estado >= 0) as cantidad_reservada,
  (SELECT IFNULL(SUM(e.cantidad),0) FROM entregas_stock e
        INNER JOIN reservas_productos r ON e.id_reserva = r.id
        WHERE r.id_variedad = v.id AND r.estado >= 0) as cantidad_entregada
  FROM stock_productos s
  INNER JOIN articulospedidos ap
  ON s.id_artpedido = ap.id
  INNER JOIN variedades_producto v
  ON v.id = ap.id_variedad
  INNER JOIN tipos_producto t
  ON t.id = v.id_tipo
  WHERE ap.estado >= 8
  GROUP BY v.id;
          ";

    $val = mysqli_query($con, $query);

    if (mysqli_num_rows($val) > 0) {

        echo "<div class='box box-primary'>";
        echo "<div class='box-header with-border'>";
        echo "<h3 class='box-title'>Stock Actual</h3>";
        echo "</div>";
        echo "<div class='box-body'>";
        echo "<table id='tabla' class='table table-bordered table-responsive w-100 d-block d-md-table'>";
        echo "<thead>";
        echo "<tr>";
        echo "<th>Producto</th><th>Precio Unitario</th><th>Cant. Disponible Plantas</th><th></th>";
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
            <td>
              <button onclick='modalReservar($ww[id_variedad], \"$ww[nombre_variedad]\", $disponible)' class='btn btn-success btn-sm'><i class='fa fa-shopping-basket'></i> RESERVAR</button>
            </td>
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
} else if ($consulta == "busca_reservas") {
    $query = "SELECT
            r.id as id_reserva,
            t.nombre as nombre_tipo,
            v.nombre as nombre_variedad,
            t.id as id_tipo,
            t.codigo,
            v.id_interno,
            r.comentario,
            r.comentario_empresa,
            v.id as id_variedad,
            SUM(r.cantidad) as cantidad,
            DATE_FORMAT(r.fecha, '%d/%m/%y<br>%H:%i') as fecha,
            DATE_FORMAT(r.fecha, '%Y%m%d %H:%i') as fecha_raw,
            (SELECT IFNULL(SUM(e.cantidad),0) FROM entregas_stock e
              WHERE e.id_reserva = r.id) as cantidad_entregada,
            r.estado
            FROM
            reservas_productos r
            INNER JOIN variedades_producto v
            ON v.id = r.id_variedad
            INNER JOIN tipos_producto t
            ON t.id = v.id_tipo
            WHERE r.id_cliente = $_SESSION[id_cliente]
            GROUP BY r.id
            ;
        ";

    $val = mysqli_query($con, $query);

    if (mysqli_num_rows($val) > 0) {

        echo "<div class='box box-primary'>";
        echo "<div class='box-header with-border'>";
        echo "<h3 class='box-title'>Mis Reservas</h3>";
        echo "</div>";
        echo "<div class='box-body'>";
        echo "<table id='tabla' class='table table-bordered table-responsive w-100 d-block d-md-table'>";
        echo "<thead>";
        echo "<tr>";
        echo "<th>ID</th><th>Producto</th><th>Fecha<br>Reserva</th><th>Cant.<br>Reservada</th><th>Cant.<br>Entregada</th><th style='max-width:250px'>Comentarios</th><th>Estado</th><th></th>";
        echo "</tr>";
        echo "</thead>";
        echo "<tbody>";

        while ($ww = mysqli_fetch_array($val)) {
            $comentario_cliente = ($ww["comentario"] != null ? "<p><small>CLIENTE: $ww[comentario]</small></p>" : "");
            $comentario_empresa = ($ww["comentario_empresa"] != null ? "<p class='text-danger'><small>EMPRESA: $ww[comentario_empresa]</small></p>" : "");

            $boton = ($ww["estado"] == 0 ? "<button onclick='cancelarReserva($ww[id_reserva])' class='btn btn-danger btn-sm'><i class='fa fa-ban'></i> CANCELAR</button>" : "");
            echo "
        <tr class='text-center' style='cursor:pointer'>
          <td><small>$ww[id_reserva]</small></td>
          <td>$ww[nombre_variedad] ($ww[codigo]$ww[id_interno])</td>
          <td><span style='display:none'>$ww[fecha_raw]</span>$ww[fecha]</td>
          <td>$ww[cantidad]</td>
          <td>$ww[cantidad_entregada]</td>
          <td style='text-transform:uppercase'>$comentario_cliente $comentario_empresa</td>
          <td>" . boxEstadoReserva($ww["estado"], true) . "</td>
          <td>
            $boton
          </td>
        </tr>";

        }
        echo "</tbody>";
        echo "</table>";
        echo "</div>";
        echo "</div>";
    } else {
        echo "<div class='callout callout-danger'><b>Aún no realizaste reservas...</b></div>";
    }
} else if ($consulta == "guardar_reserva") {
    $id_variedad = $_POST["id_variedad"];
    $cantidad = mysqli_real_escape_string($con, $_POST["cantidad"]);
    $comentario = mysqli_real_escape_string($con, $_POST["comentario"]);

    try {
        $con_tienda = mysqli_connect($host, $user, $password, $dbpresta);
        if (!$con_tienda) {
            die("Connection failed: " . mysqli_connect_error());
        }
        $errors = array();

        $query = "SELECT COUNT(*) as fechita FROM reservas_productos r WHERE r.id_cliente = $_SESSION[id_cliente] AND r.id_variedad = $id_variedad AND DATE(r.fecha) = DATE(NOW()) AND r.estado = 0";
        $val = mysqli_query($con, $query);
        if (mysqli_num_rows($val) > 0) {
            $ww = mysqli_fetch_assoc($val);
            if ((int) $ww["fechita"] > 0) {
                echo "yaexiste";
            } else {

                $query = "SELECT * FROM (
              (SELECT IFNULL(SUM(s.cantidad),0) as cantidad_stock FROM stock_productos s
              INNER JOIN articulospedidos p ON s.id_artpedido = p.id
              INNER JOIN variedades_producto v ON v.id = p.id_variedad
              WHERE p.id_variedad = $id_variedad) as q1,
              (SELECT IFNULL(SUM(r.cantidad),0) as cantidad_reservada FROM reservas_productos r
              INNER JOIN variedades_producto v ON v.id = r.id_variedad
              WHERE r.id_variedad = $id_variedad AND r.estado >= 0) as q2,
              (SELECT t.codigo, v.id_interno FROM variedades_producto v INNER JOIN tipos_producto t ON t.id = v.id_tipo WHERE v.id = $id_variedad) as q4
            )";
                $val = mysqli_query($con, $query);
                //print_r(mysqli_error($con));
                if (mysqli_num_rows($val) > 0) {
                    $ww = mysqli_fetch_assoc($val);

                    mysqli_autocommit($con, false);
                    mysqli_autocommit($con_tienda, false);

                    $disponible = ((int) $ww["cantidad_stock"] - (int) $ww["cantidad_reservada"]);
                    if ((int) $disponible >= (int) $cantidad) {
                        $query = "INSERT INTO reservas_productos (
                            cantidad,
                            fecha,
                            id_variedad,
                            id_cliente,
                            comentario,
                            estado,
                            origen
                          ) VALUES (
                            $cantidad,
                            NOW(),
                            $id_variedad,
                            $_SESSION[id_cliente],
                            '$comentario',
                            0,
                            'PANEL CLIENTE'
                          )";

                        if (!mysqli_query($con, $query)) {
                            $errors[] = mysqli_error($con) . $query;
                        }

                        $id_producto = $ww["codigo"] . str_pad($ww["id_interno"], 2, '0', STR_PAD_LEFT);
                        $query = "SELECT pr.id_product, pr.reference, st.quantity, st.physical_quantity, st.reserved_quantity FROM ps_stock_available st INNER JOIN ps_product pr ON st.id_product = pr.id_product WHERE pr.reference = '$id_producto';";

                        $estaEnTienda = false;
                        $val2 = mysqli_query($con_tienda, $query);
                        if ($val2 && mysqli_num_rows($val2)) {
                            $vt = mysqli_fetch_assoc($val2);
                            mysqli_autocommit($con_tienda, false);
                            $id_product_tienda = $vt["id_product"];
                            $query = "UPDATE ps_stock_available SET reserved_quantity = reserved_quantity + $cantidad, quantity = quantity - $cantidad WHERE id_product = $id_product_tienda;";
                            $estaEnTienda = true;
                            if (!mysqli_query($con_tienda, $query)) {
                                $errors[] = mysqli_error($con_tienda).$query;
                            }
                        }

                        if (count($errors) === 0) {
                            if (!$estaEnTienda) {
                                if (mysqli_commit($con)) {
                                    echo "success";
                                } else {
                                    mysqli_rollback($con);
                                }
                            } else {
                                if (mysqli_commit($con) && mysqli_commit($con_tienda)) {
                                    echo "success";
                                } else {
                                    mysqli_rollback($con);
                                    mysqli_rollback($con_tienda);
                                }
                            }
                        } else {
                            if (!$estaEnTienda) {
                                mysqli_rollback($con);
                            } else {
                                mysqli_rollback($con);
                                mysqli_rollback($con_tienda);
                            }
                            print_r($errors);
                        }

                    } else {
                        echo "max:" . ($disponible <= 0 ? "0" : $disponible);
                    }
                } else if (!$val) {
                    print_r(mysqli_error($con) . $query);
                }
            }
        }
        mysqli_close($con);
        mysqli_close($con_tienda);
    } catch (\Throwable$th) {
        echo $th;
    }
} else if ($consulta == "cancelar_reserva") {
    $id_reserva = $_POST["id_reserva"];
    try {
        $con_tienda = mysqli_connect($host, $user, $password, $dbpresta);
        if (!$con_tienda) {
            die("Connection failed: " . mysqli_connect_error());
        }

        $query = "SELECT t.codigo, rp.id_variedad, v.id_interno, rp.cantidad FROM reservas_productos rp INNER JOIN variedades_producto v ON rp.id_variedad = v.id INNER JOIN tipos_producto t ON t.id = v.id_tipo WHERE rp.id = $id_reserva";
        $val = mysqli_query($con, $query);
        $errors = array();
        if ($val && mysqli_num_rows($val)) {
            $v = mysqli_fetch_assoc($val);

            mysqli_autocommit($con, false);

            $cantidad = $v["cantidad"];
            $id_producto = $v["codigo"] . str_pad($v["id_interno"], 2, '0', STR_PAD_LEFT);
            $query = "SELECT pr.id_product, pr.reference, st.quantity, st.physical_quantity, st.reserved_quantity FROM ps_stock_available st INNER JOIN ps_product pr ON st.id_product = pr.id_product WHERE pr.reference = '$id_producto';";

            $estaEnTienda = false;
            $val2 = mysqli_query($con_tienda, $query);
            if ($val2 && mysqli_num_rows($val2)) {
                $vt = mysqli_fetch_assoc($val2);
                mysqli_autocommit($con_tienda, false);
                $id_product_tienda = $vt["id_product"];
                $query = "UPDATE ps_stock_available SET quantity = quantity + $cantidad, reserved_quantity = reserved_quantity - $cantidad WHERE id_product = $id_product_tienda;";
                $estaEnTienda = true;
                if (!mysqli_query($con_tienda, $query)) {
                    $errors[] = mysqli_error($con_tienda);
                }
            }

            $query = "UPDATE reservas_productos SET estado = -1 WHERE id = $id_reserva";
            if (!mysqli_query($con, $query)) {
                $errors[] = mysqli_error($con);
            }

            if (count($errors) === 0) {
                if (!$estaEnTienda) {
                    if (mysqli_commit($con)) {
                        echo "success";
                    } else {
                        mysqli_rollback($con);
                    }
                } else {
                    if (mysqli_commit($con) && mysqli_commit($con_tienda)) {
                        echo "success";
                    } else {
                        mysqli_rollback($con);
                        mysqli_rollback($con_tienda);
                    }
                }
            } else {
                if (!$estaEnTienda) {
                    mysqli_rollback($con);
                } else {
                    mysqli_rollback($con);
                    mysqli_rollback($con_tienda);
                }
                print_r($errors);
            }
        }
        mysqli_close($con);
        mysqli_close($con_tienda);

    } catch (\Throwable$th) {
        //throw $th;
        echo "error: $th";
    }
}
