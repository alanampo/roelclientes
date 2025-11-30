let currentTab;
let max = null;
let currentReserva = null;
let productosReserva = [];
let productosParaReserva = [];

let phpFile = "data_ver_reservas.php";

$(document).ready(function () {
    $("#input-cantidad-reserva").on("propertychange input", function (e) {
        this.value = this.value.replace(/\D/g, "");
    });
    $("#input-cantidad").on("propertychange input", function (e) {
        this.value = this.value.replace(/\D/g, "");
    });

    document.getElementById("defaultOpen").click();
    
    $('#select-producto-reserva').on('changed.bs.select', function (e, clickedIndex, isSelected, previousValue) {
        let id_variedad = $(this).val();
        let producto = productosParaReserva.find(p => p.id_variedad == id_variedad);
        if(producto){
            $("#input-cantidad-disponible2").val(producto.disponible);
            $("#input-cantidad-reserva").val(1);
        }
    });
});

function abrirTab(evt, tabName) {
    let i, tabcontent, tablinks;
    tabcontent = document.getElementsByClassName("tabcontent");
    currentTab = tabName;
    for (i = 0; i < tabcontent.length; i++) {
        tabcontent[i].style.display = "none";
    }
    tablinks = document.getElementsByClassName("tablinks");
    for (i = 0; i < tablinks.length; i++) {
        tablinks[i].className = tablinks[i].className.replace(" active", "");
    }
    evt.currentTarget.className += " active";
    busca_entradas(tabName);
}

function busca_entradas(tabName) {
    let consulta = "";
    if(tabName == "reservas"){
        consulta = "busca_reservas";
    } else if (tabName == "actual"){
        consulta = "busca_stock_actual";
    }

    $.ajax({
        beforeSend: function () {
            $("#tabla_entradas").html("Buscando, espere...");
        },
        url: phpFile,
        type: "POST",
        data: {
            consulta: consulta,
        },
        success: function (x) {
            let tipo = tabName;
            $("#tabla_entradas").html(x);
            $("#tabla-reservas, #tabla").DataTable({
                pageLength: 50,
                order: [tabName == "reservas" ? [1, "desc"] : [0, "asc"]],
                language: {
                    lengthMenu: `Mostrando _MENU_ ${tipo} por página`,
                    zeroRecords: `No hay ${tipo}`,
                    info: "Página _PAGE_ de _PAGES_",
                    infoEmpty: `No hay ${tipo}`,
                    infoFiltered: `(filtrado de _MAX_ ${tipo} en total)`,
                    search: "Buscar:",
                    paginate: {
                        first: "Primera",
                        last: "Última",
                        next: "Siguiente",
                        previous: "Anterior",
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
    swal("Estás seguro/a de CANCELAR la Reserva?", "", {
        icon: "warning",
        buttons: {
            cancel: "NO",
            catch: {
                text: "SI, CANCELAR",
                value: "catch",
            },
        },
    }).then((value) => {
        if (value === "catch") {
            $.ajax({
                type: "POST",
                url: phpFile,
                data: { consulta: "cancelar_reserva", id_reserva: id_reserva },
                success: function (data) {
                    if (data.trim() == "success") {
                        swal("Cancelaste la Reserva correctamente!", "", "success");
                        busca_entradas(currentTab);
                    } else {
                        swal("Ocurrió un error al cancelar la Reserva", data, "error");
                    }
                },
            });
        }
    });
}

function modalReservar() {
    productosReserva = [];
    refrescarTablaProductosReserva();
    pone_productos_reserva();
    $("#modal-reservar .box-title").html(`Crear Reserva`);
    $("#modal-reservar").modal("show");
    $("#input-cantidad-reserva").focus();
}

function pone_productos_reserva() {
    $.ajax({
        url: phpFile,
        type: "POST",
        data: { consulta: "get_productos_para_reserva" },
        dataType: 'json',
        success: function (data) {
            productosParaReserva = data;
            let options = '<option value="">Seleccione un producto...</option>';
            data.forEach(p => {
                options += `<option value="${p.id_variedad}" data-disponible="${p.disponible}">${p.nombre_variedad} (${p.codigo}${p.id_interno}) - Disp: ${p.disponible}</option>`;
            });
            $("#select-producto-reserva").html(options).selectpicker("refresh");
        },
    });
}

function agregarProductoReserva() {
    let id_variedad = $("#select-producto-reserva").val();
    let cantidad = parseInt($("#input-cantidad-reserva").val());
    let disponible = parseInt($("#input-cantidad-disponible2").val());

    if (!id_variedad) {
        swal("Error", "Debes seleccionar un producto.", "error");
        return;
    }
    if (isNaN(cantidad) || cantidad <= 0) {
        swal("Error", "La cantidad debe ser mayor a cero.", "error");
        return;
    }
    if (cantidad > disponible) {
        swal("Error", "La cantidad a reservar no puede ser mayor al stock disponible.", "error");
        return;
    }

    let producto_existente = productosReserva.find(p => p.id_variedad == id_variedad);
    if (producto_existente) {
        producto_existente.cantidad += cantidad;
    } else {
        let nombre_producto = $("#select-producto-reserva option:selected").text();
        productosReserva.push({
            id_variedad: id_variedad,
            nombre: nombre_producto.split('-')[0].trim(),
            cantidad: cantidad,
            disponible: disponible
        });
    }
    
    // Actualizar disponible en el selector
    let producto_maestro = productosParaReserva.find(p => p.id_variedad == id_variedad);
    producto_maestro.disponible -= cantidad;
    $('#select-producto-reserva option[value="' + id_variedad + '"]').data('disponible', producto_maestro.disponible);
    $('#select-producto-reserva option[value="' + id_variedad + '"]').text(`${producto_maestro.nombre_variedad} (${producto_maestro.codigo}${producto_maestro.id_interno}) - Disp: ${producto_maestro.disponible}`);
    $("#select-producto-reserva").selectpicker("refresh");
    refrescarTablaProductosReserva();
    
    // Resetear controles
    $("#select-producto-reserva").val('').selectpicker('refresh');
    $("#input-cantidad-reserva").val('');
    $("#input-cantidad-disponible2").val('');
}

function refrescarTablaProductosReserva() {
    let tablaBody = $("#tabla-productos-reserva tbody");
    tablaBody.empty();
    productosReserva.forEach((p, index) => {
        let row = `<tr>
            <td>${p.nombre}</td>
            <td>${p.cantidad}</td>
            <td><button class="btn btn-danger btn-sm" onclick="eliminarProductoReserva(${index})"><i class="fa fa-trash"></i></button></td>
        </tr>`;
        tablaBody.append(row);
    });
}

function eliminarProductoReserva(index) {
    let producto_eliminado = productosReserva.splice(index, 1)[0];
    
    // Devolver stock al selector
    let producto_maestro = productosParaReserva.find(p => p.id_variedad == producto_eliminado.id_variedad);
    producto_maestro.disponible = parseInt(producto_maestro.disponible) + parseInt(producto_eliminado.cantidad);
    let nombre_maestro = producto_maestro.nombre_variedad + " (" + producto_maestro.codigo + producto_maestro.id_interno + ")";
    $('#select-producto-reserva option[value="' + producto_eliminado.id_variedad + '"]').data('disponible', producto_maestro.disponible);
     $('#select-producto-reserva option[value="' + producto_eliminado.id_variedad + '"]').text(`${nombre_maestro} - Disp: ${producto_maestro.disponible}`);
    $("#select-producto-reserva").selectpicker("refresh");
    
    if($("#select-producto-reserva").val() == producto_eliminado.id_variedad){
        $("#input-cantidad-disponible2").val(producto_maestro.disponible);
    }
    
    refrescarTablaProductosReserva();
}

function guardarReserva() {
    const observaciones = $("#input-comentario-reserva").val().trim();

    if (productosReserva.length === 0) {
        swal("Error", "Debes agregar al menos un producto a la reserva.", "error");
        return;
    }

    $("#modal-reservar").modal("hide");

    $.ajax({
        type: "POST",
        url: phpFile,
        data: {
            consulta: "guardar_reserva",
            observaciones: observaciones,
            productos: JSON.stringify(productosReserva)
        },
        success: function (x) {
            console.log(x)
            if (x.trim() == "success") {
                swal("Éxito", "La reserva se ha guardado correctamente.", "success");
                busca_entradas(currentTab);
            } else {
                swal("Ocurrió un error al guardar la Reserva", x, "error");
                $("#modal-reservar").modal("show");
            }
        },
        error: function(){
            swal("Error de conexión", "No se pudo conectar con el servidor", "error");
            $("#modal-reservar").modal("show");
        }
    });
}
