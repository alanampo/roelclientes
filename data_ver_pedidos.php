<?php

include "./class_lib/sesionSecurity.php";
header('Content-type: text/html; charset=utf-8');

error_reporting(0);
require 'class_lib/class_conecta_mysql.php';
require 'class_lib/funciones.php';

$con = mysqli_connect($host, $user, $password, $dbname);
if (!$con) {
    die("Connection failed: " . mysqli_connect_error());
}

mysqli_query($con, "SET NAMES 'utf8'");

$consulta = $_POST["consulta"];

if ($consulta == "busca_pedidos") {
    $fechai = $_POST['fechai'];
    $fechaf = $_POST['fechaf'];

    $fechai = str_replace("/", "-", $fechai);
    $fechaf = str_replace("/", "-", $fechaf);

    if (strlen($fechai) == 0) {
        $fechai = (string) date('y-m-d', strtotime("first day of -2 years"));
    }
    if (strlen($fechaf) == 0) {
        $fechaf = "NOW()";
    }

    $filtros = json_decode($_POST['filtros'], true);

    $query = "SELECT 
        t.nombre as nombre_tipo, 
        v.nombre as nombre_variedad, 
        c.nombre as nombre_cliente,
        t.id as id_tipo,
        c.id_cliente, 
        p.fecha, 
        p.id_pedido, 
        ap.id as id_artpedido, 
        ap.cant_plantas, 
        ap.cant_bandejas,
        ap.tipo_bandeja,
        t.codigo, 
        v.id_interno, 
        ap.estado, 
        p.id_interno as id_pedido_interno, 
        DATE_FORMAT(p.fecha, '%m/%d') AS mes_dia, 
        ap.problema, 
        ap.observacionproblema, 
        ap.observacion, 
        p.id_pedido,
        u.iniciales,
        e.nombre as nombre_especie,
        ap.id_especie,
        ap.eliminado,
        DATE_FORMAT(p.fecha, '%Y%m%d') AS fecha_pedido_raw, 
        DATE_FORMAT(p.fecha, '%d/%m/%Y') as fecha_pedido,
        DATE_FORMAT(ap.fecha_ingreso, '%Y%m%d') AS fecha_ingreso_solicitada_raw, 
        DATE_FORMAT(ap.fecha_ingreso, '%d/%m/%Y') as fecha_ingreso_solicitada,
        DATE_FORMAT(ap.fecha_entrega, '%Y%m%d') AS fecha_entrega_solicitada_raw, 
        DATE_FORMAT(ap.fecha_entrega, '%d/%m/%Y') as fecha_entrega_solicitada
        
        FROM tipos_producto t
        INNER JOIN variedades_producto v ON v.id_tipo = t.id
        INNER JOIN articulospedidos ap ON ap.id_variedad = v.id
        INNER JOIN pedidos p ON p.ID_PEDIDO = ap.id_pedido
        INNER JOIN clientes c ON c.id_cliente = p.id_cliente
        LEFT JOIN usuarios u ON u.id = p.id_usuario
        LEFT JOIN especies_provistas e ON e.id = ap.id_especie
        GROUP BY ap.id 
        HAVING c.id_cliente = $_SESSION[id_cliente] AND ap.eliminado IS NULL AND p.fecha >= '$fechai'
        AND 
        ";


    if ($fechaf == "NOW()") {
        $query .= "p.fecha <= NOW() ";
    } else {
        $query .= " p.fecha <= '$fechaf' ";
    }

    if ($filtros["tipo"] != null) {
        $query .= " AND id_tipo IN " . $filtros["tipo"] . " ";
    }

    if ($filtros["variedad"] != null) {
        $query .= " AND nombre_variedad REGEXP '" . $filtros["variedad"] . "' ";
    }

    if ($filtros["tipo_busqueda"] == "todos") {
        $query .= " AND ap.estado >= -10";
    }
    else if ($filtros["tipo_busqueda"] == "pendientes"){
        $query .= " AND ap.estado = -10 ";
    }
    else if ($filtros["tipo_busqueda"] == "produccion"){
        $query .= " AND ap.estado >= 0 AND ap.estado <= 6 ";
    }
    else if ($filtros["tipo_busqueda"] == "entregados"){
        $query .= " AND ap.estado = 7 ";
    }
    else if ($filtros["tipo_busqueda"] == "cancelados"){
        $query .= " AND ap.estado = -1 ";
    }

   
   
    $val = mysqli_query($con, $query);
    if (mysqli_num_rows($val) > 0) {
        echo "<div class='box box-primary'>";
        echo "<div class='box-header with-border'>";
        echo "<h3 class='box-title'>Pedidos</h3>";
        echo "</div>";
        echo "<div class='box-body'>";
        echo "<table id='tabla' class='table table-responsive w-100 d-block d-md-table'>";
        echo "<thead>";
        echo "<tr>";
        echo "<th>Ped</th><th>Fecha</th><th>Producto</th><th>Cliente</th><th>Plantas/Bandejas</th><th>F. Ingreso</th><th>F. Entrega Aprox</th><th>Etapa</th><th>ID Prod.</th>";
        echo "</tr>";
        echo "</thead>";
        echo "<tbody>";
        $array = array();

        while ($ww = mysqli_fetch_array($val)) {
            $id_cliente = $ww['id_cliente'];
            $id_pedido = $ww['id_pedido'];
            $id_artpedido = $ww['id_artpedido'];
            $fecha = $ww['fecha_pedido_raw'];
            $tipo = "";
            $id_orden = $ww['id_orden_alternativa'];
            if ($id_orden != null) {
                $tipo = strtoupper(substr($ww["nombre_tipo"], 0, 3));
            }

            $especie = $ww["nombre_especie"] ? $ww["nombre_especie"] : "";
            $producto = "$ww[nombre_variedad] ($ww[codigo]".str_pad($ww["id_interno"], 2, '0', STR_PAD_LEFT).") <span class='text-primary'>$especie</span>";

            $id_especie = $ww["id_especie"] ? "-".str_pad($ww["id_especie"], 2, '0', STR_PAD_LEFT) : "";
            $id_producto = "$ww[iniciales]$ww[id_pedido_interno]/M$ww[mes_dia]/$ww[codigo]".str_pad($ww["id_interno"], 2, '0', STR_PAD_LEFT).$id_especie."/$ww[cant_plantas]/".str_pad($ww["id_cliente"], 2, '0', STR_PAD_LEFT);

            $cliente = $ww['nombre_cliente']." ($id_cliente)";
            
            $fecha_ingreso = $ww['fecha_ingreso_original'];

            $fecha_pedido = $ww["fecha_pedido"];
            $estado = generarBoxEstado($ww["estado"], $ww["codigo"], true);

            echo "<tr style='cursor:pointer;' onClick='MostrarModalEstado($ww[id_artpedido], \"$id_producto\", \"$ww[nombre_cliente]\")' x-codigo='$id_producto'>";

            if (in_array($ww['id_pedido'], $array)) {

                echo "<td x-id-pedido='$id_pedido' style='text-align: center;color:#1F618D;font-size:0.7em;'>$id_pedido</td>";

                echo "<td style='text-align: center'><span style='display:none;'>" . $fecha . "</span><span style='display:none'" . $fecha_pedido . "</span></td>";

                echo "<td >$producto</td>";

                echo "<td ><span style='display:none'>$cliente</span></td>";

            } else {

                echo "<td id='pedido_$id_pedido' style='text-align: center;color:#1F618D; font-weight:bold; font-size:1.0em'>$id_pedido</td>";

                echo "<td style='text-align: center'><span style='display:none;'>" . $fecha . "</span>" . $fecha_pedido . "</td>";

                echo "<td >$producto</td>";

                echo "<td >$cliente</td>";

            }

            echo "<td style='text-align: center;font-weight:bold;font-size:1.0em'>$ww[cant_plantas]<br><small>$ww[cant_bandejas] de $ww[tipo_bandeja]</small></td>";

            echo "<td style='text-align: center;'><span style='display:none'>$ww[fecha_ingreso_solicitada_raw]</span>$ww[fecha_ingreso_solicitada]</td>";

            echo "<td style='text-align: center;'><span style='display:none'>$ww[fecha_entrega_solicitada_raw]</span>$ww[fecha_entrega_solicitada]</td>";


            echo "<td ><div style='cursor:pointer'>$estado</div></td>";

            echo "<td style='text-align: center; font-size:1.0em; font-weight:bold'>
   <span style='font-size:1em;'>$id_producto</span>
   </td>";
           
            echo "</tr>";

            array_push($array, $ww['id_pedido']);

        }

        echo "</tbody>";

        echo "</table>";

        echo "</div>";

        echo "</div>";

    } else {

        echo "<div class='callout callout-danger'><b>No se encontraron pedidos en las fechas indicadas...</b></div>";

    }
}
else if ($consulta == "carga_cantidad_pedidos"){
    try {
        $arraypedidos = array();
        $val = mysqli_query($con, "SELECT  (
            SELECT COUNT(*)
            FROM   articulospedidos ap INNER JOIN pedidos p ON ap.id_pedido = p.id_pedido WHERE p.id_cliente = $_SESSION[id_cliente] AND ap.estado = -10 AND ap.eliminado IS NULL
        ) AS pendientes,
        (
            SELECT COUNT(*)
            FROM   articulospedidos ap INNER JOIN pedidos p ON ap.id_pedido = p.id_pedido WHERE p.id_cliente = $_SESSION[id_cliente] AND ap.estado >= 0 AND ap.estado <= 6 AND ap.eliminado IS NULL
        ) AS produccion,
        (
            SELECT COUNT(*)
            FROM   articulospedidos ap INNER JOIN pedidos p ON ap.id_pedido = p.id_pedido WHERE p.id_cliente = $_SESSION[id_cliente] AND ap.estado = -1 AND ap.eliminado IS NULL
        ) AS cancelados,
        (
            SELECT COUNT(*)
            FROM   articulospedidos ap INNER JOIN pedidos p ON ap.id_pedido = p.id_pedido WHERE p.id_cliente = $_SESSION[id_cliente] AND ap.estado = 7 AND ap.eliminado IS NULL
        ) AS entregados,
        (
            SELECT COUNT(*)
            FROM   articulospedidos  ap INNER JOIN pedidos p ON ap.id_pedido = p.id_pedido WHERE p.id_cliente = $_SESSION[id_cliente] AND ap.estado IN (-10, -1, 0, 1, 2, 3, 4, 5, 6, 7) AND ap.eliminado IS NULL
        ) AS todos");

        if (mysqli_num_rows($val) > 0) {
            $re = mysqli_fetch_assoc($val);
            echo json_encode(array(
                    "pendientes" => $re["pendientes"],
                    "produccion" => $re["produccion"],
                    "cancelados" => $re["cancelados"],
                    "entregados" => $re["entregados"],
                    "todos" => $re["todos"],
            ));
        }
    } catch (\Throwable $th) {
        throw $th;
    }
}
else if ($consulta == "set_click") {
 $id_usuario = $_SESSION["id_usuario"];
 $val = mysqli_query($con, "SELECT id_usuario FROM clicks WHERE id_usuario = $id_usuario;");
 if (mysqli_num_rows($val) == 0) {
    $query = "INSERT INTO clicks (fecha, id_usuario) VALUES (NOW(), $id_usuario)";
    if (mysqli_query($con, $query)){
        echo "success";
    };
 }
}