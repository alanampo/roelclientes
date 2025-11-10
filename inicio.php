<?php include "./class_lib/sesionSecurity.php"; ?>
<!DOCTYPE html>
<html>
  <head>
    <title>Panel Clientes - Roelplant</title>
    <?php include "./class_lib/links.php"; ?>
    <?php include "./class_lib/scripts.php"; ?>
    
    <script>

       $(document).ready(function(){
        pone_pedidos();
          pone_planificacionpedidos();
          pone_tendencias();
          pone_reservas();
          pone_agente();
       })


    </script>
  </head>
  <body>

    <div class="wrapper">
      <header class="main-header">
        <?php
        include('class_lib/nav_header.php');
        ?>
      </header>
      <!-- Left side column. contains the logo and sidebar -->
      <aside class="main-sidebar">
        <!-- sidebar: style can be found in sidebar.less -->
        <?php
        include('class_lib/sidebar.php');
        include('class_lib/class_conecta_mysql.php');
        $dias = array("Domingo","Lunes","Martes","Miércoles","Jueves","Viernes","Sábado");
        $meses = array("Enero","Febrero","Marzo","Abril","Mayo","Junio","Julio","Agosto","Septiembre","Octubre","Noviembre","Diciembre");
        $fecha=$dias[date('w')]." ".date('d')." de ".$meses[date('n')-1]. " del ".date('Y') ;
        ?>
        <!-- /.sidebar -->
      </aside>

      <!-- Content Wrapper. Contains page content -->
      <div class="content-wrapper">
        <!-- Content Header (Page header) -->
        <section class="content-header">
          <h1>
            <small><?php echo $fecha; ?></small>
          </h1>
          
        </section>
        <!-- Main content -->
        <section class="content">
          <div class='row row-modulos'>
            <div class="col-6 col-md-3 col-pedidos d-none">
            </div>

            <div class="col-6 col-md-3 col-planificacion d-none">
            </div>

            <div class="col-6 col-md-3 col-tendencias d-none">
            </div>

            <div class="col-6 col-md-3 col-reservas d-none">
            </div>

            <?php
                if (str_contains($_SESSION['nombre_de_usuario'], "alanampo")){
                  echo '<div class="col-6 col-md-3 col-agente d-none">
                  </div>';
                }
            ?>
          </div>
          
          </div>
        </section>
      </div><!-- /.content-wrapper -->
      <!-- Main Footer -->
      <?php include('./class_lib/main_footer.php'); ?>
      <!-- Add the sidebar's background. This div must be placed
           immediately after the control sidebar -->
      <div class="control-sidebar-bg"></div>
    </div><!-- ./wrapper -->
    
  </body>
</html>

