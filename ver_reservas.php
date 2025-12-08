<?php include "./class_lib/sesionSecurity.php"; ?>
<!DOCTYPE html>
<html>

<head>
  <title>Comprar Productos</title>
  <?php include "./class_lib/links.php"; ?>
  <?php include "./class_lib/scripts.php"; ?>
  <script src="dist/js/ver_reservas.js?v=<?php echo $version ?>"></script>
</head>

<body>
  <div id="miVentana"></div>

  <div id="ocultar">
    <div class="wrapper">
      <!-- Main Header -->
      <header class="main-header">
        <!-- Logo -->
        <?php
        include('class_lib/nav_header.php');
        ?>
      </header>
      <!-- Left side column. contains the logo and sidebar -->
      <aside class="main-sidebar">
        <!-- sidebar: style can be found in sidebar.less -->
        <?php include('class_lib/sidebar.php'); ?>
        <!-- /.sidebar -->
      </aside>

      <!-- Content Wrapper. Contains page content -->
      <div class="content-wrapper">
        <!-- Content Header (Page header) -->
        <section class="content-header">
          <h1>
            Comprar Productos
          </h1>
          <ol class="breadcrumb">
            <li><a href="inicio.php"> Inicio</a></li>
            <li class="active">Comprar Productos</li>
          </ol>
        </section>
        <!-- Main content -->
        <section class="content">
          <!-- Your Page Content Here -->

          <div class="row">
            <div class="col">
              <div class="tab">
                <button id="defaultOpen" class="tablinks" onclick="abrirTab(event, 'actual');">PRODUCTOS EN
                  STOCK</button>
                <button class="tablinks" onclick="abrirTab(event, 'reservas');">MIS RESERVAS</button>
              </div>
            </div>
          </div>

          <div class="row mt-3">
            <div class="col">
              <div id='tabla_entradas'></div>
            </div>
          </div>
      </div>

      </section><!-- /.content -->
    </div><!-- /.content-wrapper -->

    <div id="modal-reservar" class="modal">
      <div class="modal-reservar">
        <div class='box box-primary'>
          <div class='box-header with-border'>
            <h3 class='box-title'>Comprar</h3>
            <button type="button" class="close mt-2 mt-lg-0" data-dismiss="modal" aria-label="Close">
              <span aria-hidden="true">&times;</span>
            </button>
          </div>
        </div>
        <div class='box-body'>
          <div class="row">
            <div class="col-md-5 form-group">
                <label class="col-form-label" for="select-producto-reserva">Producto:</label>
                <select id="select-producto-reserva" data-size="10" data-live-search="true" title="Producto" class="selectpicker" data-style="btn-primary" data-width="100%">
                </select>
            </div>
            <div class="col-md-3 form-group">
              <label class="col-form-label" for="input-cantidad-reserva">Cantidad a Comprar:</label>
              <input type="number" placeholder="Plantas" autocomplete="off" class="form-control" name="input-cantidad"
                id="input-cantidad-reserva" maxlength="20" />
            </div>
            <div class="col-md-2 form-group">
                <label class="col-form-label text-primary" for="input-cantidad-disponible2">Disponible:</label>
                <input type="text" autocomplete="off" class="form-control font-weight-bold"
                           name="input-cantidad-disponible2" id="input-cantidad-disponible2" maxlength="20" readonly />
            </div>
            <div class="col-md-2">
                <label class="col-form-label">&nbsp;</label>
                <button onclick="agregarProductoReserva()" class="btn btn-primary btn-block"><i class="fa fa-plus"></i> AÑADIR</button>
            </div>
          </div>
          <hr>
          <div class="row">
            <div class="col-md-12">
                <table id="tabla-productos-reserva" class="table table-bordered table-hover">
                    <thead>
                        <tr>
                            <th>Producto</th>
                            <th>Cantidad</th>
                            <th>Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                    </tbody>
                </table>
            </div>
          </div>
          <div class="row">
            <div class="col-md-12 form-group">
              <label class="col-form-label" for="input-comentario-reserva">Observaciones:</label>
              <textarea class="form-control" name="input-comentario-reserva" id="input-comentario-reserva" rows="3"></textarea>
            </div>
          </div>
          <div class="row mt-2">
            <div class="col">
              <button onclick="guardarReserva()" class="btn btn-success pull-right"><i class="fa fa-save"></i> CONFIRMAR
                RESERVA</button>
            </div>
          </div>


        </div>
      </div> <!-- MODAL FIN -->
    </div>

    <!-- Main Footer -->
    <?php
    include('class_lib/main_footer.php');
    ?>


    <!-- Add the sidebar's background. This div must be placed
           immediately after the control sidebar -->
    <div class="control-sidebar-bg"></div>
  </div><!-- ./wrapper -->

  </div> <!-- ID OCULTAR-->

  <!-- REQUIRED JS SCRIPTS -->
  <script src="plugins/moment/moment.min.js"></script>


</body>

</html>
