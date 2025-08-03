<?php
error_reporting(0);

session_start();

include './class_lib/class_conecta_mysql.php';
include './class_lib/funciones.php';

$con = mysqli_connect($host, $user, $password, $dbname);
// Check connection
if (!$con) {
    die("Connection failed: " . mysqli_connect_error());
}

$usuario = test_input($_POST['user']);
$password = test_input($_POST['pass']);

mysqli_query($con, "SET NAMES 'utf8'");

$val = mysqli_query($con, "SELECT u.nombre, u.password, u.id, u.id_cliente, u.inhabilitado FROM usuarios u WHERE u.nombre='$usuario' AND BINARY u.password='$password' AND u.tipo_usuario = 0");
if (mysqli_num_rows($val) > 0) {
    $r = mysqli_fetch_assoc($val);
    if ($r["nombre"] != null) {
        if ($r["inhabilitado"] != 1) {
            $_SESSION['nombre_de_usuario'] = $r['nombre'];
            $_SESSION['clave'] = $r['password'];
            $_SESSION['id_usuario'] = $r["id"];
            $_SESSION['id_cliente'] = $r["id_cliente"];
            $token = sha1(uniqid("roel", TRUE));
            $_SESSION["roel-clientes-token"] = $token;
            setcookie("roel-clientes-usuario", $r['nombre'], time()+(60*60*24*30), '/');
            setcookie("roel-clientes-token", $token, time()+(60*60*24*30), '/');
            setcookie("roel-clientes-id", $r['id_cliente'], time()+(60*60*24*30), '/');
            
            echo "
        <script>
          document.location.href = 'inicio.php';
        </script>
        ";
        } else {
            echo "
            <script>
            swal(
              'Usuario Inhabilitado',
              'Contacta al Administrador para solucionar el problema.',
              'error'
            );
            </script>";
            setcookie("roel-clientes-usuario", NULL, time()-3600, '/');
            setcookie("roel-clientes-token", NULL, time()-3600, '/');
            setcookie("roel-clientes-id", NULL, time()-3600, '/');
        }

    } else {
        echo "<script>
        swal(
          'Nombre o contraseña inválidos',
          'Por favor verifique sus datos e intente nuevamente',
          'error'
        );
        </script>";
        setcookie("roel-clientes-usuario", NULL, time()-3600, '/');
            setcookie("roel-clientes-token", NULL, time()-3600, '/');
            setcookie("roel-clientes-id", NULL, time()-3600, '/');
    }

} else {
    echo "<script>
        swal(
          'Nombre o contraseña inválidos',
          'Por favor verifique sus datos e intente nuevamente',
          'error'
        );
      </script>";
      setcookie("roel-clientes-usuario", NULL, time()-3600, '/');
            setcookie("roel-clientes-token", NULL, time()-3600, '/');
            setcookie("roel-clientes-id", NULL, time()-3600, '/');
}
