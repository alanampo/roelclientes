let currentTab;
let max = null;
let currentVariedad = null;
$(document).ready(function () {
  
  $("#input-cantidad").on(
    "propertychange input",
    function (e) {
      this.value = this.value.replace(/\D/g, "");
    }
  );

  document.getElementById("defaultOpen").click();
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
  $.ajax({
    beforeSend: function () {
      $("#tabla_entradas").html("Buscando, espere...");
    },
    url: "data_ver_reservas.php",
    type: "POST",
    data: {
      consulta: tabName == "reservas" ? "busca_reservas" : "busca_stock_actual"
    },
    success: function (x) {
      $("#tabla_entradas").html(x);
      $("#tabla").DataTable({
        pageLength: 50,
        order: [tabName == "reservas" ? [0, "desc"] : [0, "asc"]],
        language: {
          lengthMenu: "Mostrando _MENU_ productos por página",
          zeroRecords: "No hay productos",
          info: "Página _PAGE_ de _PAGES_",
          infoEmpty: "No hay productos",
          infoFiltered: "(filtrado de _MAX_ productos en total)",
          lengthMenu: "Mostrar _MENU_ productos",
          loadingRecords: "Cargando...",
          processing: "Procesando...",
          search: "Buscar:",
          zeroRecords: "No se encontraron productos",
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
      });
    },
    error: function (jqXHR, estado, error) {
      $("#tabla_entradas").html(
        "Ocurrió un error al cargar los datos: " + estado + " " + error
      );
    },
  });
}


function cancelarReserva(id_reserva) {
  swal(
    "Estás seguro/a de CANCELAR la Reserva?",
    "",
    {
      icon: "warning",
      buttons: {
        cancel: "NO",
        catch: {
          text: "SI, CANCELAR",
          value: "catch",
        },
      },
    }
  ).then((value) => {
    switch (value) {
      case "catch":
        $.ajax({
          type: "POST",
          url: "data_ver_reservas.php",
          data: { consulta: "cancelar_reserva", id_reserva: id_reserva },
          success: function (data) {
            if (data.trim() == "success") {
              swal("Cancelaste la Reserva correctamente!", "", "success");
              busca_entradas(currentTab);
            } else {
              swal(
                "Ocurrió un error al cancelar la Reserva",
                data,
                "error"
              );
            }
          },
        });

        break;

      default:
        break;
    }
  });
}

function modalReservar(id_variedad, nombre_producto, cantidad){
  max = cantidad;
  currentVariedad = id_variedad;
  $("#modal-reservar input").val("")
  $("#modal-reservar .box-title").html(`Reservar Producto (${nombre_producto})`)
  $("#input-cantidad-disponible").val(cantidad)
  $("#modal-reservar").modal("show")
  $("#input-cantidad").focus();
}

function guardarReserva(){
  const cantidad = $("#input-cantidad").val().trim();
  const comentario = $("#input-comentario").val().trim();

  if (!cantidad || !cantidad.length || isNaN(cantidad) || parseInt(cantidad) <= 0){
    swal("Ingresa la cantidad que quieres Reservar", "", "error")
    return;
  }

  if (parseInt(cantidad) > max){
    swal("Ingresaste una cantidad superior a la disponible!", "", "error");
    return;
  }

  $("#modal-reservar").modal("hide");

  $.ajax({
    type: "POST",
    url: "data_ver_reservas.php",
    data: { 
      consulta: "guardar_reserva", 
      comentario: comentario,
      cantidad: parseInt(cantidad),
      id_variedad: currentVariedad
    },
    success: function (x) {
      console.log(x)
      if (x.trim() == "success") {
        swal("Realizaste la Reserva correctamente!", "Te contactaremos para acordar los detalles de la Entrega.", "success");
        busca_entradas(currentTab);
      }
      else if (x.trim().includes("yaexiste")){
        swal("No puedes reservar otra vez el mismo producto!", "Debes cancelar la reserva anterior.", "error")
      } 
      else if (x.trim().includes("max:")){
        swal("La cantidad ingresada ya no está disponible", "", "error")
        $("#input-cantidad-disponible").val(x.trim().replace("max:",""));
        $("#modal-reservar").modal("show");
      }
      else {
        swal(
          "Ocurrió un error al enviar la Reserva",
          "",
          "error"
        );
        $("#modal-reservar").modal("show");
      }
    },
  });


}