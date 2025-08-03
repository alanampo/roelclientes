var currentTab;
$(document).ready(function () {
  document.getElementById("defaultOpen").click();
  
  $("#daterange-btn").daterangepicker(
    {
      ranges: {
        Hoy: [moment(), moment()],
        Ayer: [moment().subtract(1, "days"), moment().subtract(1, "days")],
        "SEMANA PASADA": [
          moment().startOf("isoWeek").subtract(7, "days"),
          moment().startOf("isoWeek").subtract(1, "days"),
        ],
        "Los ultimos 7 dias": [moment().subtract(6, "days"), moment()],
        "Los ultimos 30 dias": [moment().subtract(29, "days"), moment()],
        "Los ultimos 6 meses": [moment().subtract(180, "days"), moment()],
        "Este mes": [moment().startOf("month"), moment().endOf("month")],
        //'El mes pasado': [moment().subtract(1, 'month').startOf('month'), moment().subtract(1, 'month').endOf('month')],
        "Todo el año": [moment().startOf("year"), moment()],
      },
      startDate: moment().subtract(2, "years"),
      endDate: moment(),
    },
    function (start, end) {
      $(".fe").html(
        start.format("DD/MM/YYYY") + " - " + end.format("DD/MM/YYYY")
      );
      var xstart = start.format("YYYY-MM-DD");
      var xend = end.format("YYYY-MM-DD");
      $("#fi").val(xstart);
      $("#ff").val(xend);
    }
  );
});


function busca_entradas(tipo_busqueda) {
  var fecha = $("#fi").val();
  var fechaf = $("#ff").val();
  var tipos = $("#select_tipo").val();
  if (tipos.length == 0) tipos = null;
  else {
    tipos = JSON.stringify(tipos).replace("[", "(").replace("]", ")");
  }

  var variedad = $("#busca_variedad").val().trim().toUpperCase();
  if (variedad.length == 0) variedad = null;
  else if (variedad.includes(",")) {
    variedad = variedad.replace(",", "|");
  }

  var filtros = {
    tipo: tipos,
    variedad: variedad ? variedad.toUpperCase() : null,
    tipo_busqueda: tipo_busqueda,
  };
  var filtros = JSON.stringify(filtros);

  loadCantidadPedidos();
  $.ajax({
    beforeSend: function () {
      $("#tabla_entradas").html(
        "<h4 class='ml-1'>Buscando pedidos, espera...</h4>"
      );
    },
    url: "data_ver_pedidos.php",
    type: "POST",
    data: {
      consulta: "busca_pedidos",
      fechai: fecha,
      fechaf: fechaf,
      filtros: filtros,
    },
    success: function (x) {
      $("#tabla_entradas").html(x);
      $("#tabla").DataTable({
        pageLength: 50,
        order: [[0, "desc"]],
        language: {
          lengthMenu: "Mostrando _MENU_ pedidos por página",
          zeroRecords: "No hay pedidos",
          info: "Página _PAGE_ de _PAGES_",
          infoEmpty: "No hay pedidos",
          infoFiltered: "(filtrado de _MAX_ pedidos en total)",
          lengthMenu: "Mostrar _MENU_ pedidos",
          loadingRecords: "Cargando...",
          processing: "Procesando...",
          search: "Buscar:",
          zeroRecords: "No se encontraron resultados",
          paginate: {
            first: "Primera",
            last: "Última",
            next: "Siguiente",
            previous: "Anterior",
          },
          aria: {
            sortAscending: ": tocá para ordenar en modo ascendente",
            sortDescending: ": tocá para ordenar en modo descendente",
          },
        },
        /*"columnDefs": [
                  { "width": "30%", "targets": [2] }
                  ]*/
      });
    },
    error: function (jqXHR, estado, error) {
      $("#tabla_entradas").html(
        "Ocurrió un error al cargar los datos: " + estado + " " + error
      );
    },
  });
}

function print_Busqueda(tipo) {
  if (tipo == 1) {
    func_printBusqueda();

    document.getElementById("ocultar").style.display = "none";

    document.getElementById("miVentana").style.display = "block";
  } else {
    document.getElementById("ocultar").style.display = "block";

    document.getElementById("miVentana").style.display = "none";

    $("#miVentana").html("");
  }
}

function func_printBusqueda() {
  var direccion = `<div align='center'><img src='${globals.logoPrintImg}' class="logo-print"></img>`;

  $("#miVentana").html(direccion);

  $("#miVentana").append(document.getElementById("tabla").outerHTML);

  $("#miVentana")
    .find("tr,td,th")
    .css({ "font-size": "9px", "word-wrap": "break-word" });

  var haymesada = false;
  $("#miVentana")
    .find("tr")
    .each(function () {
      $(this).find("td:eq(7)").css({ "font-size": "7px" });
      if ($(this).find("td:eq(9)").text().trim().length > 0) {
        haymesada = true;
      }
    });

  if (!haymesada) {
    $("#miVentana").find("th:eq(9)").remove();
    $("#miVentana")
      .find("tr")
      .each(function () {
        $(this).find("td:eq(9)").remove();
      });
  }

  setTimeout("window.print();print_Busqueda(2)", 500);
}

function expande_busqueda() {
  var contenedor = $("#contenedor_busqueda");
  if ($(contenedor).css("display") == "none")
    $(contenedor).css({ display: "block" });
  else {
    $(contenedor).css({ display: "none" });
    $("#select_tipo,#select_estado").val("default").selectpicker("refresh");
    $("#busca_subtipo,#busca_variedad,#busca_cliente").val("");
  }
  $(".box-body-buscar").toggleClass("p-0");
}

function quitar_filtros() {
  $("#select_tipo,#select_estado").val("default").selectpicker("refresh");
  $("#busca_subtipo,#busca_variedad,#busca_cliente").val("");
  busca_entradas();
}


function pone_tipos() {
  $.ajax({
    beforeSend: function () {
      $("#select_tipo").html("Cargando productos...");
    },
    url: "data_ver_tipos.php",
    type: "POST",
    data: { consulta: "busca_tipos_select" },
    success: function (x) {
      $(".selectpicker").selectpicker();
      $("#select_tipo").html(x).selectpicker("refresh");
    },
    error: function (jqXHR, estado, error) {},
  });
}


function openTab(evt, tabName) {
  var i, tabcontent, tablinks;
  // Get all elements with class="tabcontent" and hide them
  tabcontent = document.getElementsByClassName("tabcontent");
  currentTab = tabName;
  for (i = 0; i < tabcontent.length; i++) {
    tabcontent[i].style.display = "none";
  }
  tablinks = document.getElementsByClassName("tablinks");
  for (i = 0; i < tablinks.length; i++) {
    tablinks[i].className = tablinks[i].className.replace(" active", "");
  }
  //document.getElementById(tabName).style.display = "block";
  evt.currentTarget.className += " active";
  if (tabName != "cuadricula"){
    $(".listado-container,.box-busqueda").removeClass("d-none");
    $(".cuadricula-container").addClass("d-none")
    busca_entradas(tabName);    
  }
  else{
    $(".listado-container,.box-busqueda").addClass("d-none");
    $(".cuadricula-container").removeClass("d-none")
    
    //loadCuadricula()
  }
}

function loadCantidadPedidos() {
  $.ajax({
    url: "data_ver_pedidos.php",
    type: "POST",
    data: { consulta: "carga_cantidad_pedidos" },
    success: function (x) {
      if (x.length) {
        const data = JSON.parse(x);
        if (data && data.todos) {
          $(".label-todos").html(`(${data.todos})`);
          $(".label-entregados").html(`(${data.entregados})`);
          $(".label-produccion").html(`(${data.produccion})`);
          $(".label-cancelados").html(`(${data.cancelados})`);
          $(".label-pendientes").html(`(${data.pendientes})`);

          if (data.pendientes > 0) {
            $(".label-pend").addClass("text-danger");
          } else {
            $(".label-pend").removeClass("text-danger");
          }
        }
      }
    },
    error: function (jqXHR, estado, error) {},
  });
}