<?php include "./class_lib/sesionSecurity.php"; ?>
<!DOCTYPE html>
<html>

<head>
  <title>Tendencias</title>
  <?php include "./class_lib/links.php"; ?>
  <?php include "./class_lib/scripts.php"; ?>
  <script src="dist/js/ver_tendencias_c.js?v=<?php echo $version ?>"></script>
  <script src="js/charts.min.js"></script>
</head>

<body>
    <div class="wrapper">
      <header class="main-header">
        <?php include('class_lib/nav_header.php');?>
      </header>
      <aside class="main-sidebar">
        <?php include('class_lib/sidebar.php');?>
      </aside>

      <div class="content-wrapper">
        <section class="content-header">
          <h1>Tendencias</h1>
          <ol class="breadcrumb">
            <li><a href="inicio.php"> Inicio</a></li>
            <li class="active">Tendencias</li>
          </ol>
        </section>
        <!-- Main content -->
        <section class="content" id="content">
          <div class="row">
            <div class="col">
              <div class="tab">
                <button id="defaultOpen"  class="tablinks" onclick="abrirTab(event, 'top10');">TOP 10</button>
                <button class="tablinks" onclick="abrirTab(event, 'top50');">TOP 50</button>
                <button class="tablinks" onclick="abrirTab(event, 'masymenos');">MÁS Y MENOS PEDIDAS</button>
                <button class="tablinks" onclick="abrirTab(event, 'general');">VISTA GENERAL</button>
                <button class="tablinks" onclick="abrirTab(event, 'producto');">DETALLE POR PRODUCTO</button>
                <button class="tablinks" onclick="abrirTab(event, 'lineal');">GRÁFICO LINEAL GENERAL</button>
              </div>
            </div>
          </div>

          <div class="row mt-3">
            <div class="col">
              <div class='box box-primary'>
                <div class='box-header with-border'>
                  <div class="row">
                    <div class="col-md-2">
                      <select id="select-tipo-top10" class="selectpicker" onchange="$('#select-variedad').val('default').selectpicker('refresh');loadVariedadesEspeciesSelect();loadData()" title="Tipo de Pedido"
                        data-style="btn-info" data-dropup-auto="false" data-width="100%">
                        <option value="semillas">Semillas (S)</option>
                        <option value="esquejes">Esquejes (E)</option>
                        <option value="hechurasemillas">Hechura Semillas (HS)</option>
                        <option value="hechuraesquejes">Hechura Esquejes (HE)</option>
                      </select>
                    </div>
                    <div class="col-md-3 col-variedad">
                      <select id="select-variedad" title="Variedad/Especie" onchange="loadData()" class="selectpicker"
                                    data-style="btn-info" data-width="100%" data-size="10" data-live-search="true">
                        </select>
                    </div>
                    <div class="col-md-2">
                      <select id="select-anio-top10" class="selectpicker" onchange="loadData()" title="Año"
                        data-style="btn-info" data-dropup-auto="false" data-width="100%">
                      </select>
                    </div>
                    <div class="col-md-2 col-mes">
                      <select id="select-mes-top10" class="selectpicker" onchange="loadData()" title="Mes"
                        data-style="btn-info" data-dropup-auto="false" data-width="100%">
                        <option value="0">Todo el año</option>
                        <option value="1">Enero</option>
                        <option value="2">Febrero</option>
                        <option value="3">Marzo</option>
                        <option value="4">Abril</option>
                        <option value="5">Mayo</option>
                        <option value="6">Junio</option>
                        <option value="7">Julio</option>
                        <option value="8">Agosto</option>
                        <option value="9">Septiembre</option>
                        <option value="10">Octubre</option>
                        <option value="11">Noviembre</option>
                        <option value="12">Diciembre</option>

                      </select>
                    </div>
                    <div class="col-md-2">
                      <select id="select-filtro-top10" onchange="loadData()" class="selectpicker"
                        title="Cantidades Según..." data-style="btn-info" data-width="100%" data-dropup-auto="false"
                        data-size="5">
                        <option value="bandejas">Bandejas</option>
                        <option value="plantas">Plantas</option>
                        <option value="pedidos">Pedidos</option>
                      </select>
                    </div>
                    
                  </div>
                </div>
                <div class='box-body chart-container mb-5' style="min-height:75vh;"></div>
                <div class='box-body calendar-container mb-5 d-none' style="min-height:75vh;">
                  <div class="row">
                    <div class="col-md-4 col-mes">
                      <div class="calendar-month-wrapper">
                        <h5 class="text-center pt-2 pb-2 calendar-month-label">ENERO</h5>
                        <div class="enero" style="height: 100%;width:100%"></div>
                      </div>
                    </div>
                    <div class="col-md-4 col-mes">
                      <div class="calendar-month-wrapper">
                        <h5 class="text-center pt-2 pb-2 calendar-month-label">FEBRERO</h5>
                        <div class="febrero" style="height: 100%;width:100%"></div>
                      </div>
                    </div>
                    <div class="col-md-4 col-mes">
                      <div class="calendar-month-wrapper">
                        <h5 class="text-center pt-2 pb-2 calendar-month-label">MARZO</h5>
                        <div class="marzo" style="height: 100%;width:100%"></div>
                      </div>
                    </div>
                    <div class="col-md-4 col-mes">
                      <div class="calendar-month-wrapper">
                        <h5 class="text-center pt-2 pb-2 calendar-month-label">ABRIL</h5>
                        <div class="abril" style="height: 100%;width:100%"></div>
                      </div>
                    </div>
                    <div class="col-md-4 col-mes">
                      <div class="calendar-month-wrapper">
                        <h5 class="text-center pt-2 pb-2 calendar-month-label">MAYO</h5>
                        <div class="mayo" style="height: 100%;width:100%"></div>
                      </div>
                    </div>
                    <div class="col-md-4 col-mes">
                      <div class="calendar-month-wrapper">
                        <h5 class="text-center pt-2 pb-2 calendar-month-label">JUNIO</h5>
                        <div class="junio" style="height: 100%;width:100%"></div>
                      </div>
                    </div>
                    <div class="col-md-4 col-mes">
                      <div class="calendar-month-wrapper">
                        <h5 class="text-center pt-2 pb-2 calendar-month-label">JULIO</h5>
                        <div class="julio" style="height: 100%;width:100%"></div>
                      </div>
                    </div>
                    <div class="col-md-4 col-mes">
                      <div class="calendar-month-wrapper">
                        <h5 class="text-center pt-2 pb-2 calendar-month-label">AGOSTO</h5>
                        <div class="agosto" style="height: 100%;width:100%"></div>
                      </div>
                    </div>
                    <div class="col-md-4 col-mes">
                      <div class="calendar-month-wrapper">
                        <h5 class="text-center pt-2 pb-2 calendar-month-label">SEPTIEMBRE</h5>
                        <div class="septiembre" style="height: 100%;width:100%"></div>
                      </div>
                    </div>
                    <div class="col-md-4 col-mes">
                      <div class="calendar-month-wrapper">
                        <h5 class="text-center pt-2 pb-2 calendar-month-label">OCTUBRE</h5>
                        <div class="octubre" style="height: 100%;width:100%"></div>
                      </div>
                    </div>
                    <div class="col-md-4 col-mes">
                      <div class="calendar-month-wrapper">
                        <h5 class="text-center pt-2 pb-2 calendar-month-label">NOVIEMBRE</h5>
                        <div class="noviembre" style="height: 100%;width:100%"></div>
                      </div>
                    </div>
                    <div class="col-md-4 col-mes">
                      <div class="calendar-month-wrapper">
                        <h5 class="text-center pt-2 pb-2 calendar-month-label">DICIEMBRE</h5>
                        <div class="diciembre" style="height: 100%;width:100%"></div>
                      </div>
                    </div>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </section><!-- /.content -->
      </div>
    </div><!-- /.content-wrapper -->

    <!-- Main Footer -->
    <?php include('class_lib/main_footer.php');?>

    <div class="control-sidebar-bg"></div>
  
 
</body>

</html>