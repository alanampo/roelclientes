<?php

include "./class_lib/sesionSecurity.php";
header('Content-type: text/html; charset=utf-8');

error_reporting(0);
require 'class_lib/class_conecta_mysql.php';
require 'class_lib/funciones.php';

$con = mysqli_connect($host, $user, $password, $dbname);
// Check connection
if (!$con) {
    die("Connection failed: " . mysqli_connect_error());
}

$consulta = $_POST["consulta"];
mysqli_query($con, "SET NAMES 'utf8'");

if ($consulta == "busca_tipos") {
    $cadena = "SELECT * FROM tipos_producto ORDER BY nombre;";
    $val = mysqli_query($con, $cadena);

    if (mysqli_num_rows($val) > 0) {
        echo "<div class='box box-primary'>";
        echo "<div class='box-header with-border'>";
        echo "</div>";
        echo "<div class='box-body'>";
        echo "<table id='tabla' class='table table-bordered table-responsive w-100 d-block d-md-table'>";
        echo "<thead>";
        echo "<tr>";
        echo "<th>Id</th><th>Nombre</th>";
        echo "</tr>";
        echo "</thead>";
        echo "<tbody>";

        while ($ww = mysqli_fetch_array($val)) {
            $id = $ww['id'];
            $codigo = $ww["codigo"];
            $tipo = $ww['nombre'];

            echo "<tr>";
            echo "<td style='text-align: center; width:40px; cursor:pointer; color:#1F618D;font-weight:bold;' x-id='$id' x-codigo='$codigo'>
        <span class='d-none'>$id</span>
        <span style='font-size:1.4em'>$codigo</span><br>
        <span style='color:grey;font-size:0.8em;'>$id</span>
        </td>
      </td>";
            echo "<td style='text-align: center; cursor:pointer;font-size:1.4em;font-weight:bold;'>$tipo</td>";
            echo "</tr>";
        }
        echo "</tbody>";
        echo "</table>";
        echo "</div>";
        echo "</div>";
    } else {
        echo "<div class='callout callout-danger'><b>No se encontraron productos en la base de datos...</b></div>";
    }
} else if ($consulta == "agregar_tipo") {
    $nombre = $_POST['nombre'];
    $codigo = $_POST['codigo'];
    try {
        $val = mysqli_query($con, "SELECT * FROM tipos_producto WHERE nombre = UPPER('$nombre');");
        if (mysqli_num_rows($val) > 0) {
            echo "Ya existe un tipo de producto con ese nombre!";
        } else {
            $val = mysqli_query($con, "SELECT * FROM tipos_producto WHERE codigo = UPPER('$codigo');");
            if (mysqli_num_rows($val) > 0) {
                echo "Ya existe un tipo de producto con ese código!";
            } else {
                $query = "INSERT INTO tipos_producto (nombre, codigo) VALUES (UPPER('$nombre'), UPPER('$codigo'));";
                mysqli_query($con, $query);
            }
        }
    } catch (\Throwable $th) {
        echo "error: " . $th;
    }
} else if ($consulta == "editar_tipo") {
    $id_tipo = $_POST['id_tipo'];
    $nombre = $_POST["nombre"];
    $codigo = $_POST["codigo"];

    try {
        $val = mysqli_query($con, "SELECT * FROM tipos_producto WHERE nombre = UPPER('$nombre') AND id <> $id_tipo;");
        if (mysqli_num_rows($val) > 0) {
            echo "error: Ya existe un tipo de producto con ese nombre!";
        } else {
            $val = mysqli_query($con, "SELECT * FROM tipos_producto WHERE codigo = UPPER('$codigo') AND codigo <> $codigo;;");
            if (mysqli_num_rows($val) > 0) {
                echo "error: Ya existe un tipo de producto con ese código!";
            } else {
                $query = "UPDATE tipos_producto SET nombre = UPPER('$nombre'), codigo = UPPER('$codigo') WHERE id = $id_tipo";
                mysqli_query($con, $query);
            }
        }
    } catch (\Throwable $th) {
        echo "error: " . $th;
    }
} else if ($consulta == "busca_tipos_select") {
    try {
        if ($_POST["tipo"] == "HS/HE"){
            $cadena = "select id, nombre, codigo from tipos_producto WHERE codigo IN ('HS', 'HE') order by nombre ASC";
        }
        else{
            $cadena = "select id, nombre, codigo from tipos_producto order by nombre ASC";
        }
        
        $val = mysqli_query($con, $cadena);
        if (mysqli_num_rows($val) > 0) {
            while ($re = mysqli_fetch_array($val)) {
                echo "<option value='$re[id]' x-codigo='$re[codigo]' x-nombre='$re[nombre]'>$re[nombre] ($re[codigo])</option>";
            }
        }
    } catch (\Throwable $th) {
        //throw $th;
    }
}
