var currentTab;
$(document).ready(function () {
  document.getElementById("default").click();

  var html2 = "";
  for (var i = 0; i < 300; i++) {
    html2 += `<tr>
                  <td></td>
                  <td></td>
                  <td></td>
                  <td></td>
                  <td></td>
                  <td></td>
                  </tr>`;
  }
  $("#tabla-semillas,#tabla-esquejes").find("tbody").html(html2);

  
});




function loadEsquejes() {
  const busqueda = $("#input-search").val().trim();
  $.ajax({
    beforeSend: function () {
      $("#tabla-esquejes td").html("");
    },
    url: "data_ver_seguimiento.php",
    type: "POST",
    data: { consulta: "cargar_esquejes", busqueda: busqueda },
    success: function (x) {
      if (x.trim().length) {
        const pedidos = JSON.parse(x);
        if (pedidos.length) {
          for (var j = 0; j < 6; j++) {
            let index = 0;
            pedidos.forEach(function (e, i) {
              if (e.estado == 6) {
                e.estado = 5;
                e.es_entrega_parcial = true;
              }
              if (e.estado == j) {
                $("#tabla-esquejes > tbody")
                  .find("tr")
                  .eq(index)
                  .find(`td:eq(${j})`)
                  .html(MakeBox(e, e.es_entrega_parcial ? 6 : j, "esqueje"));
                index++;
              }
            });
          }
        }
      }
    },
    error: function (jqXHR, estado, error) {},
  });


}

function loadSemillas() {
  const busqueda = $("#input-search").val().trim();
  $.ajax({
    beforeSend: function () {
      $("#tabla-semillas td").html("");
    },
    url: "data_ver_seguimiento.php",
    type: "POST",
    data: { consulta: "cargar_semillas", busqueda: busqueda },
    success: function (x) {
      if (x.trim().length) {
        const pedidos = JSON.parse(x);
        if (pedidos.length) {
          for (var j = 0; j < 6; j++) {
            let index = 0;
            pedidos.forEach(function (e, i) {
              if (e.estado == 6) {
                e.estado = 5;
                e.es_entrega_parcial = true;
              }
              if (e.estado == j) {
                $("#tabla-semillas > tbody")
                  .find("tr")
                  .eq(index)
                  .find(`td:eq(${j})`)
                  .html(MakeBox(e, e.es_entrega_parcial ? 6 : j, "semilla"));
                index++;
              }
            });
          }
        }
      }
    },
    error: function (jqXHR, estado, error) {},
  });


}

var miTab;
function openCuadricula(evt, tabName) {
  var i, tabcontent;
  // Get all elements with class="tabcontent" and hide them
  tabcontent = document.getElementsByClassName("tabcontent");
  miTab = tabName;
  for (i = 0; i < tabcontent.length; i++) {
    tabcontent[i].style.display = "none";
  }
  document.getElementById(tabName).style.display = "block";
  
  $(".btn-esq,.btn-sem").removeClass("active2")

  $("#input-search").val("")
  if (miTab == "tab-esquejes") {
    loadEsquejes();
    $(".btn-esq").addClass("active2")
  } 
  else if (miTab == "tab-semillas") {
    loadSemillas();
    $(".btn-sem").addClass("active2")
  }
}

function MakeBox(producto, index, tipo_producto) {
  if (tipo_producto == "esqueje") {
    var colores = [
      "#D8EAD2",
      "#B6D7A8",
      "#A9D994",
      "#A2D98A",
      "#99D87D",
      "#8AD868",
      "#FBF07D",
    ];
  } else if (tipo_producto == "semilla") {
    var colores = [
      "#FFF2CD",
      "#FFE59A",
      "#FED966",
      "#F2C234",
      "#E0B42F",
      "#CEA62E",
      "#FBF07D",
    ];
  }
  const date = moment(producto.fecha);
  const fecha = date.format("DD/MM/YY HH:mm");

  var codigo =
    producto.iniciales +
    producto.id_pedido_interno +
    "/M" +
    date.format("M") +
    "/" +
    date.format("DD") +
    "/" +
    producto.codigo +
    producto.id_interno.padStart(2, "0") +
    (producto.id_especie ? "-" + producto.id_especie.padStart(2, "0") : "") +
    "/" +
    producto.cant_plantas +
    "/" +
    producto.id_cliente.padStart(2, "0"); //"T1/M3/07/S130/1000";

  var observacionproblema = "";
  var observacion = "";
  if (producto.observacionproblema && producto.problema) {
    observacionproblema = `<div  style="font-size: 0.8em; word-wrap: break-all;"  class='bg-light text-danger ml-1 mr-1 mb-1'>${
      producto.observacionproblema.length > 20
        ? producto.observacionproblema.substring(0, 17) + "..."
        : producto.observacionproblema
    }</div>`;
  }

  if (producto.observacion) {
    observacion = `<div  style="font-size: 0.8em; word-wrap: break-all;"  class='bg-light text-primary ml-1 mr-1 mb-1'>${
      producto.observacion.length > 20
        ? producto.observacion.substring(0, 17) + "..."
        : producto.observacion
    }</div>`;
  }
  var especie = "";
  if (producto.id_especie) {
    especie = `<span class='${
      producto.problema ? "text-light" : "text-primary"
    }'>${producto.nombre_especie}</span><br>`;
  }

  var html = `<div x-id-real="${
    producto.id_artpedido
  }" x-id="${codigo}" x-estado='${producto.estado}' x-parcial='${
    producto.es_entrega_parcial ? 1 : 0
  }' class='cajita' style='word-wrap: break-word;touch-action: none;cursor:pointer;background-color:${
    producto.problema ? "#DA6E6B" : colores[index]
  };font-size:1.0em;'
      >
      <span>${codigo}<br></span>
      <span style='font-weight:bold;'>${producto.nombre_variedad}<br>
      ${especie}
      ${producto.nombre_cliente}<br>Cant. Plantas: ${producto.cant_plantas}
        ${
          producto.cant_bandejas
            ? `<br>
        <small>(${producto.cant_bandejas} band. de ${producto.tipo_bandeja})</small>`
            : ""
        }
        <br>
        <span style="font-size: 0.7em">
        ${fecha}</span>
        ${observacionproblema}
        ${observacion}
        </span></div>`;

  return html;
}

function buscar(){
  const busqueda = $("#input-search").val().trim();
  if (!busqueda.length || busqueda.length >= 3){
    if (miTab == "tab-esquejes") {
      loadEsquejes();
    } 
    else if (miTab == "tab-semillas") {
      loadSemillas();
    }
  } 
}