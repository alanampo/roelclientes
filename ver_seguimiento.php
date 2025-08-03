<?php include "./class_lib/sesionSecurity.php"; ?>
<!DOCTYPE html>
<html>

<head>
  <title>Pedidos en Producción</title>
  <?php include "./class_lib/links.php"; ?>
  <?php include "./class_lib/scripts.php"; ?>
  
  <script src="dist/js/ver_seguimiento_c.js?v=<?php echo $version ?>"></script>
  <script src="plugins/moment/moment.min.js"></script>
  
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
</head>

<body>
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
          <h1>Pedidos en Producción</h1>
          <ol class="breadcrumb">
            <li><a href="inicio.php"> Inicio</a></li>
            <li class="active">Pedidos en Producción</li>
          </ol>
        </section>
        <!-- Main content -->
        <section class="content">
            <div class='row'>
            <div class="col">
              <div class="row">
                <div class="col-md-2">
                  <div class="tab2">
                    <button class="tablinks btn-esq" onclick="openCuadricula(event, 'tab-esquejes');"
                      id="default">Esquejes</button>
                    <button class="tablinks btn-sem" onclick="openCuadricula(event, 'tab-semillas');">Semillas</button>
                  </div>
                </div>
                <div class="col-md-7">
                </div>
                <div class="col-md-3">
                  <div class="d-flex flex-row align-items-center pt-2">
                    <label for="input-search">Buscar:</label>
                    <input id="input-search" oninput='buscar(this.value);' class="form-control w-75 ml-2" type="search"
                      autocomplete="off"></input>
                  </div>
                </div>
              </div>

              <div id="tab-esquejes" class="tabcontent tablin">
                <table class="table table-responsive mt-3 w-100 d-block d-md-table" id="tabla-esquejes">
                  <thead class="thead-dark">
                    <tr class="text-center">
                      <th>ETAPA 0<br><span class="header-subtitle">INICIO</span></th>
                      <th>ETAPA 1<br><span class="header-subtitle">10%</span></th>
                      <th>ETAPA 2<br><span class="header-subtitle">50%</span></th>
                      <th>ETAPA 3<br><span class="header-subtitle">100%</span></th>
                      <th>ETAPA 4<br><span class="header-subtitle">REPIQUE</span></th>
                      <th>ETAPA 5<br><span class="header-subtitle">ENTREGA</span></th>
                    </tr>
                  </thead>
                  <tbody>
                  </tbody>
                </table>
              </div>

              <div id="tab-semillas" class="tabcontent tablin">
                <table class="table table-responsive mt-3 w-100 d-block d-md-table" id="tabla-semillas">
                  <thead style="background-color: rgb(1, 84, 139);color: white;">
                    <tr class="text-center">
                      <th>ETAPA 0<br><span class="header-subtitle">SEMBRADO</span></th>
                      <th>ETAPA 1<br><span class="header-subtitle">GERMINADO</span></th>
                      <th>ETAPA 2<br><span class="header-subtitle">2 COTILEDONES</span></th>
                      <th>ETAPA 3<br><span class="header-subtitle">HOJAS VERDADERAS</span></th>
                      <th>ETAPA 4<br><span class="header-subtitle">REPIQUE</span></th>
                      <th>ETAPA 5<br><span class="header-subtitle">ENTREGA</span></th>
                    </tr>
                  </thead>
                  <tbody>
                  </tbody>
                </table>
              </div>

              <style>
                .tablin table,
                thead,
                tr,
                tbody,
                th,
                td {
                  text-align: center;
                  table-layout: fixed;
                }

                .tablin td {
                  text-align: center;
                  height: 70px;
                  background-color: #e2f8ffc2;
                  border: 1px solid rgba(87, 87, 87, 0.466);
                }
              </style>

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