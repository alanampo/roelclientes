<?php
///CLASE PARA CONECTAR CON MYSQL.....////
error_reporting(0);

if (strpos($_SERVER['HTTP_HOST'], 'roelplant') !== false) {
  $host = "127.0.0.1"; /* Host name */
  $user = "roeluser1_usercli"; /* User */
  $password = "SergioVM2022!!"; /* Password */
  $dbname = "roeluser1_bdsys"; /* Database name */
  $dbpresta = "roeluser1_shops";
}
else{
  $host = "127.0.0.1"; /* Host name */
  $user = "root"; /* User */
  $password = ""; /* Password */
  $dbname = "roel"; /* Database name */
  $dbpresta = "prestashop";
}

?>