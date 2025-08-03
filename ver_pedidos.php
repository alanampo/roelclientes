<?php include "./class_lib/sesionSecurity.php"; ?>
<!DOCTYPE html>
<html>

<head>
  <title>Pedidos</title>
  <?php include "./class_lib/links.php"; ?>
  <?php include "./class_lib/scripts.php"; ?>
  <link rel="stylesheet" href="plugins/select2/select2.min.css">
  <link rel="stylesheet" href="plugins/daterangepicker/daterangepicker-bs3.css">

  <script src="dist/js/ver_pedidos_c.js?v=<?php echo $version ?>"></script>
  <script src="plugins/moment/moment.min.js"></script>
  <script src="plugins/daterangepicker/daterangepicker.js"></script>

  <meta name="viewport" content="width=device-width, initial-scale=1.0">
</head>

<body onload="busca_entradas();pone_tipos();">
  <div id="ocultar">
    <div class="wrapper">
      <header class="main-header">
        <?php include('class_lib/nav_header.php');?>
      </header>
      <aside class="main-sidebar">
        <?php include('class_lib/sidebar.php');?>
      </aside>
      <!-- Content Wrapper. Contains page content -->
      <div class="content-wrapper">
        <!-- Content Header (Page header) -->
        <section class="content-header">
          <h1>Mis Pedidos</h1>
          <ol class="breadcrumb">
            <li><a href="inicio.php"> Inicio</a></li>
            <li class="active">Mis Pedidos</li>
          </ol>
        </section>
        <!-- Main content -->
        <section class="content">
          <div class='row'>
            <div class='col'>
              <div class="d-flex align-items-end h-100 pb-2">
                <div class="row">
                  <div class="col">
                    <div class="tab">
                      <button id="defaultOpen" class="tablinks" onclick="openTab(event, 'todos');">TODOS <span
                          class="label-cant label-todos"></span></button>
                      <button class="tablinks" onclick="openTab(event, 'pendientes');"><span
                          class="label-pend">PENDIENTES <span
                            class="label-cant label-pendientes"></span></span></button>
                      <button class="tablinks" onclick="openTab(event, 'produccion');">EN PRODUCCIÓN <span
                          class="label-cant label-produccion"></span></button>
                      <button class="tablinks" onclick="openTab(event, 'entregados');">ENTREGADOS <span
                          class="label-cant label-entregados"></span></button>
                      <button class="tablinks" onclick="openTab(event, 'cancelados');">CANCELADOS <span
                          class="label-cant label-cancelados"></span></button>
                    </div>
                  </div>
                </div>
              </div>

            </div>
            <div class='col-md-3'>
              <div class='box box-primary box-busqueda d-none'>
                <div class='box-header with-border'>
                  <button class='btn btn-primary pull-right' onclick='expande_busqueda()' id='btn-busca'><i
                      class='fa fa-caret-down'></i> Busqueda Avanzada</button>
                </div>
                <div class='box-body p-0 box-body-buscar'>
                  <div id="contenedor_busqueda" style="display:none">
                    <div class="form-group">
                      <div class='row'>
                        <div class='col-md-3'>
                          <label>Fechas:</label>
                        </div>
                        <div class='col-md-9'>
                          <div class="input-group">
                            <button class="btn btn-default pull-left" id="daterange-btn">
                              <i class="fa fa-calendar"></i> Seleccionar...
                              <i class="fa fa-caret-down"></i>
                            </button>
                          </div>
                        </div>
                      </div>

                      <span class='fe'></span>
                      <input type='hidden' class='form-control' id='fi' value=''>
                      <input type="hidden" class='form-control' id='ff' value=''>
                    </div>
                    <div class="form-group">
                      <div class='row'>
                        <div class='col-md-3'>
                          <label>Producto:</label>
                        </div>
                        <div class='col-md-9'>
                          <select id="select_tipo" class="selectpicker mobile-device" title="Tipo" data-style="btn-info"
                            data-dropup-auto="false" data-size="5" data-width="100%" multiple></select>
                        </div>
                      </div>
                    </div>

                    <div class="form-group">
                      <div class='row'>
                        <div class='col-md-3'>
                          <label>Variedad:</label>
                        </div>
                        <div class='col-md-9'>
                          <div class="btn-group" style="width:100%">
                            <input id="busca_variedad" style="text-transform:uppercase" type="search"
                              class="form-control">
                            <span id="searchclear" onClick="$('#busca_variedad').val('');"
                              class="glyphicon glyphicon-remove-circle"></span>
                          </div>
                        </div>
                      </div>
                    </div>

                    <div class="row">
                      <div class='col-md-2'>
                        <button class='btn btn-primary' onclick='busca_entradas();' id='btn-busca'><i
                            class='fa fa-search'></i> Buscar...</button>
                      </div>
                    </div>
                  </div> <!-- CONTENEDOR BUSQUEDA -->
                </div>
              </div>
            </div>
          </div> <!-- FIN ROW -->

          <div class="row mb-5 listado-container">
            <div class='col'>
              <div id='tabla_entradas'></div>
            </div>
          </div>

          
      </div>


      </section><!-- /.content -->


    </div><!-- /.content-wrapper -->


    <!-- Main Footer -->

    <?php include('class_lib/main_footer.php');?>
    <?php include("./modal_ver_estado.php"); ?>
  </div>

  <div class="control-sidebar-bg"></div>

</body>

</html>