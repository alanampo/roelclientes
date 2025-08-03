<?php

session_destroy();
$parametros_cookies = session_get_cookie_params();
setcookie(session_name(),0,1,$parametros_cookies["path"]);
setcookie('roel-clientes-id', '', time() - 3600, '/');
setcookie('roel-clientes-token', '', time() - 3600, '/');
header("Location: index.php");

?>