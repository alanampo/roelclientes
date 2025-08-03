<?php
include "./class_lib/sesionSecurity.php";
error_reporting(0);
require 'class_lib/class_conecta_mysql.php';
require 'class_lib/funciones.php';

header('Content-type: text/html; charset=utf-8');

$con = mysqli_connect($host, $user, $password, $dbname);
if (!$con) {
    die("Connection failed: " . mysqli_connect_error());
}

mysqli_query($con, "SET NAMES 'utf8'");

$consulta = $_POST["consulta"];
if ($consulta == "cargar_esquejes" || $consulta == "cargar_semillas"){
    try {
        $busqueda = mysqli_escape_string($con, $_POST["busqueda"]);
        $strbusqueda = strlen($busqueda) >= 3 ? " AND (v.nombre REGEXP '$busqueda' OR e.nombre REGEXP '$busqueda' OR c.nombre REGEXP '$busqueda')" : "";
        $arraypedidos = array();
        if ($consulta == "cargar_esquejes") $tipo_producto = "('E','HE')";
        else if ($consulta == "cargar_semillas") $tipo_producto = "('S','HS')";

        $cadenaselect = "SELECT t.nombre as nombre_tipo, v.nombre as nombre_variedad, c.nombre as nombre_cliente, p.fecha, p.id_pedido, ap.id as id_artpedido, ap.cant_plantas, ap.cant_bandejas, ap.tipo_bandeja, t.codigo, v.id_interno, ap.estado, p.id_interno as id_pedido_interno,
        ap.problema, ap.observacionproblema, c.id_cliente, ap.observacion, u.iniciales, ap.id_especie, e.nombre as nombre_especie
        FROM tipos_producto t
        INNER JOIN variedades_producto v ON v.id_tipo = t.id
        INNER JOIN articulospedidos ap ON ap.id_variedad = v.id
        INNER JOIN pedidos p ON p.ID_PEDIDO = ap.id_pedido
        INNER JOIN clientes c ON c.id_cliente = p.id_cliente
        LEFT JOIN usuarios u ON u.id = p.id_usuario
        LEFT JOIN especies_provistas e ON e.id = ap.id_especie
        WHERE c.id_cliente = $_SESSION[id_cliente] AND ap.eliminado IS NULL AND ap.estado >= 0 AND ap.estado <= 6 AND t.codigo IN $tipo_producto
        $strbusqueda
        ORDER BY p.fecha ASC;
        ";

        $val = mysqli_query($con, $cadenaselect);

        if (mysqli_num_rows($val) > 0) {
            while ($re = mysqli_fetch_array($val)) {
                array_push($arraypedidos, array(
                    "nombre_tipo" => $re["nombre_tipo"],
                    "nombre_variedad" => $re["nombre_variedad"],
                    "nombre_cliente" => $re["nombre_cliente"],
                    "nombre_especie" => $re["nombre_especie"],
                    "fecha" => $re["fecha"],
                    "cant_plantas" => $re["cant_plantas"],
                    "cant_bandejas" => $re["cant_bandejas"],
                    "tipo_bandeja" => $re["tipo_bandeja"],
                    "codigo" => $re["codigo"],
                    "id_interno" => $re["id_interno"],
                    "estado" => $re["estado"],
                    "id_pedido" => $re["id_pedido"],
                    "id_artpedido" => $re["id_artpedido"],
                    "id_pedido_interno" => $re["id_pedido_interno"],
                    "problema" => $re["problema"],
                    "observacionproblema" => $re["observacionproblema"],
                    "observacion" => $re["observacion"],
                    "iniciales" => $re["iniciales"],
                    "id_especie" => $re["id_especie"],
                    "id_cliente" => $re["id_cliente"]
                ));
                
            }
            $mijson = json_encode($arraypedidos);
            echo $mijson;
        }
    } catch (\Throwable $th) {
    throw $th;
    }
}
