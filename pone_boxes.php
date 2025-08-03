<?php

include "./class_lib/sesionSecurity.php";

error_reporting(0);
include 'class_lib/class_conecta_mysql.php';

$con = mysqli_connect($host, $user, $password, $dbname);
// Check connection
if (!$con) {
    die("Connection failed: " . mysqli_connect_error());
}

$tipo = $_POST["tipo"];
$id_cliente = $_SESSION["id_cliente"];

if ($tipo == "seguimiento") {
    $consulta2 = "Select ap.id, p.id_pedido FROM articulospedidos ap INNER JOIN pedidos p ON ap.id_pedido = p.id_pedido WHERE p.id_cliente = $id_cliente AND ap.eliminado IS NULL AND ap.estado IN (0, 1, 2, 3, 4, 5, 6)";
    $val = mysqli_query($con, $consulta2);
    $cantidad = mysqli_num_rows($val);

    echo "
        <a href=\"ver_seguimiento.php\">
            <div class=\"small-box bg-red\">
                <div class=\"inner\">
                    <h3>$cantidad</h3>
                    <p>Pedidos en Producción</p>
                </div>
                <div class=\"icon\">
                    <i class=\"ion ion-calendar\"></i>
                </div>
                <span class=\"small-box-footer\">Ver Producción <i class=\"fa fa-arrow-circle-right\"></i></span>
            </div>
        </a>
    ";
} else if ($tipo == "pedidos") {
    $i = "0";
    $consulta = "SELECT  (SELECT COUNT(ap.id)
    FROM   articulospedidos ap INNER JOIN pedidos p ON ap.id_pedido = p.id_pedido WHERE p.id_cliente = $id_cliente AND ap.eliminado IS NULL
    ) AS todos,
    (
    SELECT COUNT(ap.id)
    FROM  articulospedidos ap INNER JOIN pedidos p ON ap.id_pedido = p.id_pedido WHERE p.id_cliente = $id_cliente AND  ap.estado = -10 AND ap.eliminado IS NULL
    ) AS pendientes";
    $val = mysqli_query($con, $consulta);

    if (mysqli_num_rows($val) > 0) {
        $r = mysqli_fetch_assoc($val);
        $i = $r['todos'];
        if ($i > 999999){
            $i = "+999999";
        }
        $pendientes = $r["pendientes"];
    }
    echo "
    <a href=\"ver_pedidos.php\">
        <div class=\"small-box bg-aqua\">
        <div class=\"inner\">
            <h3>$i</h3>
            <p class=\"titulo-seccion\">Pedidos <span class=\"text-danger\" style=\"font-size:11px;font-weight:bold;display: inline-block;\">($pendientes PENDIENTES)</span></p>
        </div>
        <div class=\"icon\">
            <i class=\"ion ion-bag\"></i>
        </div>
        <span class=\"small-box-footer\">Ver Pedidos <i class=\"fa fa-arrow-circle-right\"></i></span>
        </div>
    </a>
    ";
} 