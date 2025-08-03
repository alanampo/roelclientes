let currentTab;
let editMode = false;
$(document).ready(function () {
  $(".selectpicker").selectpicker();
  //swal("Módulo aún no disponible","", "info")

  $(".selectpicker").selectpicker();
  $("#select-anio-top10").html("");
  const anio = new Date().getFullYear();
  for (let i = 2022; i <= anio; i++) {
    $("#select-anio-top10").append(`<option value="${i}">${i}</option>`);
  }
  $(".selectpicker").selectpicker("refresh");
  $("#select-anio-top10").val(["2022"]);
  $("#select-tipo-top10").val(["semillas"]);
  loadVariedadesEspeciesSelect();
  $("#select-filtro-top10").val(["bandejas"]);
  
  //$("#select-mes-top10").val((new Date().getMonth() + 1).toString());
  $("#select-mes-top10").val("0");
  $(".selectpicker").selectpicker("refresh");
  if (document.location.href.includes("ver_tendencias")) {
    document.getElementById("defaultOpen").click();
  }
});

function abrirTab(evt, tabName) {
  let i, tabcontent, tablinks;
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
  busca_entradas(tabName);
}

function busca_entradas(tabName) {
  $(".calendar-container,.col-variedad").addClass("d-none");
  

  if (tabName == "top10" || tabName == "top50") {
    if ($(".chart-container").hasClass("d-none"))
      $(".chart-container,.col-mes").removeClass("d-none");
    loadTop();
  } else if (tabName == "masymenos") {
    if ($(".chart-container").hasClass("d-none"))
      $(".chart-container,.col-mes").removeClass("d-none");
    loadTop();
  } else if (tabName == "general") {
    $(".calendar-container").removeClass("d-none");
    $(".chart-container,.col-mes").addClass("d-none");
    loadGeneral();
  } else if (tabName == "producto") {
    $(".col-variedad").removeClass("d-none");
    $(".chart-container,.col-mes").addClass("d-none");
  } else if (tabName == "lineal") {
    $(".chart-container,.col-mes").addClass("d-none");
    loadGraficoLineal();
  }
}

function loadTop(id_cliente) {
  const tipoPedido = $("#select-tipo-top10 option:selected").val(); //SEMILLAS - ESQUEJES
  const anio = $("#select-anio-top10 option:selected").val();
  const mes = $("#select-mes-top10 option:selected").val();
  const tipoFiltro = $("#select-filtro-top10 option:selected").val(); //PLANTAS - BAND - PEDIDOS
  $.ajax({
    beforeSend: function () {
      $(".chart-container").html("<h4 class='ml-1'>Buscando, espere...</h4>");
    },
    url: "data_ver_tendencias.php",
    type: "POST",
    data: {
      consulta: "busca_top",
      tab_name: currentTab,
      tipo_pedido: tipoPedido,
      anio: anio,
      mes: mes,
      tipo_filtro: tipoFiltro,
      id_cliente: id_cliente ? id_cliente : "",
    },
    success: function (datos) {
      
      if (datos.length) {
        $(".label-estadisticas").html("TOP 10");
        try {
          const data = JSON.parse(datos);
          console.log(data)
          generarChart(tipoPedido, tipoFiltro, anio, mes, data);
        } catch (error) {
          console.log(error);
          console.log(datos)
          $(".chart-container").html(
            `<div class='callout callout-danger'><b>No se encontraron datos en las fechas indicadas...</b></div>`
          );
        }
      } else {
        $(".chart-container").html(
          `<div class='callout callout-danger'><b>No se encontraron datos en las fechas indicadas...</b></div>`
        );
        $(".label-estadisticas").html("");
      }
    },
    error: function (jqXHR, estado, error) {
      $(".chart-container").html(
        `<div class='callout callout-danger'><b>Ocurrió un error... ${error}</b></div>`
      );
    },
  });
}

function generarChart(tipoPedido, tipoFiltro, anio, mes, json) {
  let variedades = [];
  let cantidades = [];
  if (currentTab == "masymenos") {
    $(".chart-container").css({ height: `${20 * json.length}px` });
  } else {
    $(".chart-container").css({ height: `` });
  }

  var data = json.slice();
  if (tipoFiltro == "bandejas") {
    data.sort((a, b) => b.cant_bandejas - a.cant_bandejas);
  } else if (tipoFiltro == "plantas") {
    data.sort((a, b) => b.cant_plantas - a.cant_plantas);
  } else if (tipoFiltro == "pedidos") {
    data.sort((a, b) => b.cant_pedidos - a.cant_pedidos);
  }

  data.forEach((e, i) => {
    variedades.push(`${e.nombre_variedad} (${e.tipo})`);
    cantidades.push(
      tipoFiltro == "plantas"
        ? e.cant_plantas
        : tipoFiltro == "bandejas"
        ? e.cant_bandejas
        : tipoFiltro == "pedidos"
        ? e.cant_pedidos
        : ""
    );
  });

  $(".chart-container").html(`<canvas id="myChart"></canvas>`);

  var ctx = document.getElementById("myChart").getContext("2d");
  var myChart = new Chart(ctx, {
    type: "bar",
    data: {
      labels: variedades,
      datasets: [
        {
          label: " " + tipoFiltro.charAt(0).toUpperCase() + tipoFiltro.slice(1),
          data: cantidades,
          maxBarThickness: 80,
          minBarThickness: 30,
          backgroundColor: [
            tipoPedido == "esquejes"
              ? "rgba(0, 255, 100, 0.5)"
              : tipoPedido == "hechuraesquejes"
              ? "rgba(0, 200, 80, 0.4)"
              : tipoPedido == "semillas"
              ? "rgba(225, 179, 35, 0.5)"
              : tipoPedido == "hechurasemillas"
              ? "rgba(225, 179, 35, 0.4)"
              : "",
          ],
          borderColor: [
            tipoPedido == "esquejes"
              ? "rgba(0, 255, 100, 1)"
              : tipoPedido == "hechuraesquejes"
              ? "rgba(0, 200, 80, 1)"
              : tipoPedido == "semillas"
              ? "rgba(225, 179, 35, 1)"
              : tipoPedido == "hechurasemillas"
              ? "rgba(225, 179, 35, 1)"
              : "",
          ],
          borderWidth: 2,
        },
      ],
    },
    options: {
      indexAxis: currentTab == "masymenos" ? "y" : "x",
      responsive: true,
      maintainAspectRatio: false,
      // Elements options apply to all of the options unless overridden in a dataset
      // In this case, we are setting the border of each horizontal bar to be 2px wide
      elements: {
        bar: {
          borderWidth: 2,
        },
      },
      scales: {
        y: {
          beginAtZero: true,
        },
        xAxes: [
          {
            maxBarThickness: 10,
          },
        ],
      },
    },
  });
}


function loadData(val) {
  if (
    currentTab == "top10" ||
    currentTab == "top50" ||
    currentTab == "masymenos"
  ) {
    loadTop(val);
  } else if (currentTab == "general") {
    loadGeneral(val);
  } else if (currentTab == "producto") {
    loadStatsProducto();
  } else if (currentTab == "lineal") {
    loadGraficoLineal();
  }
}

function loadGeneral(id_cliente) {
  const tipoPedido = $("#select-tipo-top10 option:selected").val(); //SEMILLAS - ESQUEJES
  const anio = $("#select-anio-top10 option:selected").val();
  const tipoFiltro = $("#select-filtro-top10 option:selected").val(); //PLANTAS - BAND - PEDIDOS
  $.ajax({
    beforeSend: function () {
      //$(".chart-container").html("<h4 class='ml-1'>Buscando, espere...</h4>");
    },
    url: "data_ver_tendencias.php",
    type: "POST",
    data: {
      consulta: "busca_general",
      tipo_pedido: tipoPedido,
      anio: anio,
      id_cliente: id_cliente ? id_cliente : "",
    },
    success: function (datos) {
      console.log(datos);
      if (datos.length) {
        $(".label-estadisticas").html("TOP 10");
        try {
          const data = JSON.parse(datos);
          for (var i = 1; i <= 12; i++) {
            generarChartGeneral(tipoPedido, tipoFiltro, data[`${i}`], i);
          }
        } catch (error) {
          console.log(error);
        }
      } else {
      }
    },
    error: function (jqXHR, estado, error) {},
  });
}

function generarChartGeneral(tipoPedido, tipoFiltro, json, mes) {
  let variedades = [];
  let cantidades = [];
  const meses = [
    "enero",
    "febrero",
    "marzo",
    "abril",
    "mayo",
    "junio",
    "julio",
    "agosto",
    "septiembre",
    "octubre",
    "noviembre",
    "diciembre",
  ];

  var data = json.slice();

  if (!data.length) {
    $("." + meses[mes - 1]).html(`
      <h5 class='text-muted'>SIN DATOS</h5>
    `);
    $("." + meses[mes - 1])
      .closest(".calendar-month-wrapper")
      .css({ background: "" });
    $("." + meses[mes - 1])
      .closest(".col-mes")
      .addClass("d-none");
    return;
  } else {
    $("." + meses[mes - 1])
      .closest(".calendar-month-wrapper")
      .css({ background: "white" });
    $("." + meses[mes - 1])
      .closest(".col-mes")
      .removeClass("d-none");
  }

  if (tipoFiltro == "bandejas") {
    data.sort((a, b) => b.cant_bandejas - a.cant_bandejas);
  } else if (tipoFiltro == "plantas") {
    data.sort((a, b) => b.cant_plantas - a.cant_plantas);
  } else if (tipoFiltro == "pedidos") {
    data.sort((a, b) => b.cant_pedidos - a.cant_pedidos);
  }

  data.forEach((e, i) => {
    variedades.push(`${e.nombre_variedad} (${e.tipo})`);
    cantidades.push(
      tipoFiltro == "plantas"
        ? e.cant_plantas
        : tipoFiltro == "bandejas"
        ? e.cant_bandejas
        : tipoFiltro == "pedidos"
        ? e.cant_pedidos
        : ""
    );
  });

  $("." + meses[mes - 1]).html(
    `<canvas id="chart-${meses[mes - 1]}"></canvas>`
  );

  var ctx = document.getElementById(`chart-${meses[mes - 1]}`).getContext("2d");
  var myChart = new Chart(ctx, {
    type: "bar",
    data: {
      labels: variedades,
      datasets: [
        {
          label: " " + tipoFiltro.charAt(0).toUpperCase() + tipoFiltro.slice(1),
          data: cantidades,
          maxBarThickness: 80,
          minBarThickness: 30,
          backgroundColor: [
            tipoPedido == "esquejes"
              ? "rgba(0, 255, 100, 0.5)"
              : tipoPedido == "hechuraesquejes"
              ? "rgba(0, 200, 80, 0.4)"
              : tipoPedido == "semillas"
              ? "rgba(225, 179, 35, 0.5)"
              : tipoPedido == "hechurasemillas"
              ? "rgba(225, 179, 35, 0.4)"
              : "",
          ],
          borderColor: [
            tipoPedido == "esquejes"
              ? "rgba(0, 255, 100, 1)"
              : tipoPedido == "hechuraesquejes"
              ? "rgba(0, 200, 80, 1)"
              : tipoPedido == "semillas"
              ? "rgba(225, 179, 35, 1)"
              : tipoPedido == "hechurasemillas"
              ? "rgba(225, 179, 35, 1)"
              : "",
          ],
          borderWidth: 2,
        },
      ],
    },
    options: {
      indexAxis: "x",
      responsive: true,
      maintainAspectRatio: false,
      // Elements options apply to all of the options unless overridden in a dataset
      // In this case, we are setting the border of each horizontal bar to be 2px wide
      elements: {
        bar: {
          borderWidth: 2,
        },
      },
      scales: {
        y: {
          beginAtZero: true,
        },
        xAxes: [
          {
            maxBarThickness: 10,
          },
        ],
      },
    },
  });
}

function loadVariedadesEspeciesSelect() {
  const val = $("#select-tipo-top10").find("option:selected").val();
  const tipo =
    val == "esquejes"
      ? "E"
      : val == "semillas"
      ? "S"
      : val == "hechuraesquejes"
      ? "HE"
      : "HS";
  $.ajax({
    beforeSend: function () {
      $("#select-variedad").html("Cargando variedades...");
    },
    url: "data_ver_tendencias.php",
    type: "POST",
    data: { consulta: "busca_variedades_especies_select", tipo: tipo },
    success: function (x) {
      $("#select-variedad").val("default").selectpicker("refresh");
      $("#select-variedad").html(x).selectpicker("refresh");
      $("#select-variedad").on(
        "changed.bs.select",
        function (e, clickedIndex, newValue, oldValue) {}
      );
    },
    error: function (jqXHR, estado, error) {},
  });
}

//////////

function loadStatsProducto() {
  const tipoPedido = $("#select-tipo-top10 option:selected").val(); //SEMILLAS - ESQUEJES
  const anio = $("#select-anio-top10 option:selected").val();
  const tipoFiltro = $("#select-filtro-top10 option:selected").val(); //PLANTAS - BAND - PEDIDOS
  const id_producto = $("#select-variedad option:selected").val(); //VARIEDAD - ESPECIE
  $.ajax({
    beforeSend: function () {
      $(".chart-container").html("<h4 class='ml-1'>Buscando, espere...</h4>");
    },
    url: "data_ver_tendencias.php",
    type: "POST",
    data: {
      consulta: "busca_stats_producto",
      tab_name: currentTab,
      tipo_pedido: tipoPedido,
      anio: anio,
      id_producto: id_producto,
      tipo_filtro: tipoFiltro,
    },
    success: function (datos) {
      if (datos.length) {
        $(".label-estadisticas").html("PRODUCTOS");
        try {
          const data = JSON.parse(datos);
          console.log(data);
          generarChartProducto(tipoPedido, tipoFiltro, data);
        } catch (error) {
          console.log(error);
          $(".chart-container").html(
            `<div class='callout callout-danger'><b>No se encontraron datos en las fechas indicadas...</b></div>`
          );
        }
      } else {
        $(".chart-container").html(
          `<div class='callout callout-danger'><b>No se encontraron datos en las fechas indicadas...</b></div>`
        );
        $(".label-estadisticas").html("");
      }
    },
    error: function (jqXHR, estado, error) {
      $(".chart-container").html(
        `<div class='callout callout-danger'><b>Ocurrió un error... ${error}</b></div>`
      );
    },
  });
}

function generarChartProducto(tipoPedido, tipoFiltro, json) {
  let variedades = [];
  let cantidades = [];

  $(".chart-container").css({ height: `` });

  const meses = [
    "ENERO",
    "FEBRERO",
    "MARZO",
    "ABRIL",
    "MAYO",
    "JUNIO",
    "JULIO",
    "AGOSTO",
    "SEPTIEMBRE",
    "OCTUBRE",
    "NOVIEMBRE",
    "DICIEMBRE",
  ];

  var i = 0;
  Object.values(json).forEach((e) => {
    variedades.push(meses[i]);
    cantidades.push(
      tipoFiltro == "plantas"
        ? e.cant_plantas
        : tipoFiltro == "bandejas"
        ? e.cant_bandejas
        : tipoFiltro == "pedidos"
        ? e.cant_pedidos
        : ""
    );
    i++;
  });
  console.log(cantidades);

  $(".chart-container").html(`<canvas id="myChart"></canvas>`);

  var ctx = document.getElementById("myChart").getContext("2d");
  var myChart = new Chart(ctx, {
    type: "bar",
    data: {
      labels: variedades,
      datasets: [
        {
          label: " " + tipoFiltro.charAt(0).toUpperCase() + tipoFiltro.slice(1),
          data: cantidades,
          maxBarThickness: 80,
          minBarThickness: 30,
          backgroundColor: [
            tipoPedido == "esquejes"
              ? "rgba(0, 255, 100, 0.5)"
              : tipoPedido == "hechuraesquejes"
              ? "rgba(0, 200, 80, 0.4)"
              : tipoPedido == "semillas"
              ? "rgba(225, 179, 35, 0.5)"
              : tipoPedido == "hechurasemillas"
              ? "rgba(225, 179, 35, 0.4)"
              : "",
          ],
          borderColor: [
            tipoPedido == "esquejes"
              ? "rgba(0, 255, 100, 1)"
              : tipoPedido == "hechuraesquejes"
              ? "rgba(0, 200, 80, 1)"
              : tipoPedido == "semillas"
              ? "rgba(225, 179, 35, 1)"
              : tipoPedido == "hechurasemillas"
              ? "rgba(225, 179, 35, 1)"
              : "",
          ],
          borderWidth: 2,
        },
      ],
    },
    options: {
      indexAxis: "x",
      responsive: true,
      maintainAspectRatio: false,
      // Elements options apply to all of the options unless overridden in a dataset
      // In this case, we are setting the border of each horizontal bar to be 2px wide
      elements: {
        bar: {
          borderWidth: 2,
        },
      },
      scales: {
        y: {
          beginAtZero: true,
        },
        xAxes: [
          {
            maxBarThickness: 10,
          },
        ],
      },
    },
  });
  $(".chart-container").removeClass("d-none");
}
// FIN VISTA PRODUCTO

//GRAFICO LINEAL
function loadGraficoLineal() {
  const tipoPedido = $("#select-tipo-top10 option:selected").val(); //SEMILLAS - ESQUEJES
  const anio = $("#select-anio-top10 option:selected").val();
  const tipoFiltro = $("#select-filtro-top10 option:selected").val(); //PLANTAS - BAND - PEDIDOS
  $.ajax({
    beforeSend: function () {
      $(".chart-container").html("<h4 class='ml-1'>Buscando, espere...</h4>");
    },
    url: "data_ver_tendencias.php",
    type: "POST",
    data: {
      consulta: "busca_stats_lineal",
      tipo_pedido: tipoPedido,
      anio: anio,
      tipo_filtro: tipoFiltro,
    },
    success: function (datos) {
      //console.log(datos);
      if (datos.length) {
        try {
          const data = JSON.parse(datos);
          //console.log(data);
          generarChartLineal(tipoPedido, tipoFiltro, data);
        } catch (error) {
          console.log(error);
          $(".chart-container").html(
            `<div class='callout callout-danger'><b>No se encontraron datos en las fechas indicadas...</b></div>`
          );
        }
      } else {
        $(".chart-container").html(
          `<div class='callout callout-danger'><b>No se encontraron datos en las fechas indicadas...</b></div>`
        );
        $(".label-estadisticas").html("");
      }
    },
    error: function (jqXHR, estado, error) {
      $(".chart-container").html(
        `<div class='callout callout-danger'><b>Ocurrió un error... ${error}</b></div>`
      );
    },
  });
}

function generarChartLineal(tipoPedido, tipoFiltro, json) {
  $(".chart-container").css({ height: `` });

  const meses = [
    "ENERO",
    "FEBRERO",
    "MARZO",
    "ABRIL",
    "MAYO",
    "JUNIO",
    "JULIO",
    "AGOSTO",
    "SEPTIEMBRE",
    "OCTUBRE",
    "NOVIEMBRE",
    "DICIEMBRE",
  ];

  $(".chart-container").html(`<canvas id="myChart"></canvas>`);

  var ctx = document.getElementById("myChart").getContext("2d");

  lineChartData = {}; //declare an object
  lineChartData.labels = []; //add 'labels' element to object (X axis)
  lineChartData.datasets = []; //add 'datasets' array element to object

  console.log(json)

  let max = json.length > 10 ? 10 : json.length;
  
  for (line = 0; line < max; line++) {
    y = [];
    lineChartData.datasets.push({}); //create a new line dataset
    dataset = lineChartData.datasets[line];
    const randomBetween = (min, max) =>
      min + Math.floor(Math.random() * (max - min + 1));
    const r = randomBetween(0, 255);
    const g = randomBetween(0, 255);
    const b = randomBetween(0, 255);
    dataset.backgroundColor = `rgba(${r},${g},${b},1)`;
    dataset.borderColor = `rgba(${r},${g},${b},1)`;
    dataset.strokeColor = `rgba(${r},${g},${b},1)`;
    dataset.data = []; //contains the 'Y; axis data
    
    for (x = 0; x < 12; x++) {
      if (json[line])
        y.push(json[line]["cantidades"][x]); //push some data aka generate 4 distinct separate lines

      if (line === 0) lineChartData.labels.push(meses[x]); //adds x axis labels
    } //for x
    if (json[line]){
      lineChartData.datasets[line].label = `(${json[line]["tipo"]}) ${json[line]["nombre_variedad"]}`;
      lineChartData.datasets[line].data = y; //send new line data to dataset
    }
    
  } //for line

  var myChart = new Chart(ctx, {
    type: "line",
    data: lineChartData,
    options: {
      indexAxis: "x",
      responsive: true,
      maintainAspectRatio: false,
      // Elements options apply to all of the options unless overridden in a dataset
      // In this case, we are setting the border of each horizontal bar to be 2px wide
      elements: {
        bar: {
          borderWidth: 2,
        },
      },
      scales: {
        y: {
          beginAtZero: true,
        },
        xAxes: [
          {
            maxBarThickness: 10,
          },
        ],
      },
    },
  });

  $(".chart-container").removeClass("d-none");
}
//FIN GRAFICO LINEAL
