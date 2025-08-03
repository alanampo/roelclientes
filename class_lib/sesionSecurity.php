<?php
  
  session_start();
  $version = "14";
  date_default_timezone_set("America/Santiago");
  if(!isset($_SESSION["roel-clientes-token"]) || !isset($_COOKIE["roel-clientes-token"])){
    header("Location: index.php");
  }

  if($_SESSION["roel-clientes-token"] != $_COOKIE["roel-clientes-token"]){
    header("Location: index.php");
  }

  if(!isset($_SESSION["id_cliente"]) || !isset($_COOKIE["roel-clientes-id"])){
    header("Location: index.php");
  }

  if($_SESSION["id_cliente"] != $_COOKIE["roel-clientes-id"]){
    header("Location: index.php");
  }


  
?>