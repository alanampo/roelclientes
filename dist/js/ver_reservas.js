let currentTab;
let max = null;
let currentReserva = null;
let productosReserva = []; // This array now acts as the shopping cart
let productosParaReserva = [];

let phpFile = "data_ver_reservas.php";

toastr.options = {
  "closeButton": true,
  "debug": false,
  "newestOnTop": false,
  "progressBar": true,
  "positionClass": "toast-top-right",
  "preventDuplicates": false,
  "onclick": null,
  "showDuration": "300",
  "hideDuration": "1000",
  "timeOut": "5000",
  "extendedTimeOut": "1000",
  "showEasing": "swing",
  "hideEasing": "linear",
  "showMethod": "fadeIn",
  "hideMethod": "fadeOut"
}

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
                    lengthMenu: `Mostrando _MENU_ registros por página`,
                    zeroRecords: `No hay registros`,
                    info: "Página _PAGE_ de _PAGES_",
                    infoEmpty: `No hay registros`,
                    infoFiltered: `(filtrado de _MAX_ registros en total)`,
                    search: "Buscar:",
                    paginate: {
                        first: "Primera",
                        last: "Última",
                        next: "Siguiente",
                        previous: "Anterior",
                    },
                },
            });
            // After table is drawn, update cart button display
            refrescarTablaProductosReserva();
        },
        error: function (jqXHR, estado, error) {
            $("#tabla_entradas").html(
                "Ocurrió un error al cargar los datos: " + estado + " " + error
            );
        },
    });
}

function agregarAlCarritoDesdeTabla(id_variedad, nombre_producto, disponible, inputId) {
    const cantidad = parseInt($(`#${inputId}`).val());

    if (isNaN(cantidad) || cantidad <= 0) {
        toastr.error("La cantidad debe ser un número mayor a cero.");
        return;
    }
    if (cantidad > disponible) {
        toastr.error("La cantidad a reservar no puede ser mayor al stock disponible.");
        return;
    }

    let producto_existente = productosReserva.find(p => p.id_variedad == id_variedad);
    if (producto_existente) {
        if (producto_existente.cantidad + cantidad > disponible) {
            toastr.error(`No puedes agregar más de ${disponible} unidades de este producto. Ya tienes ${producto_existente.cantidad} en el carrito.`);
            return;
        }
        producto_existente.cantidad += cantidad;
    } else {
        productosReserva.push({
            id_variedad: id_variedad,
            nombre: nombre_producto,
            cantidad: cantidad,
            disponible: disponible
        });
    }

    toastr.success(`Se agregaron ${cantidad} unidad(es) de ${nombre_producto} al carrito.`);
    refrescarTablaProductosReserva();
}

function cancelarReserva(id_reserva) {
    swal("Estás seguro/a de CANCELAR la Compra?", "", {
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
                    toastr.success("Cancelaste la Compra correctamente!");
                        busca_entradas(currentTab);
                    } else {
                        toastr.error("Ocurrió un error al cancelar la Reserva", data);
                    }
                },
            });
        }
    });
}

function modalReservar() {
    // This function now just opens the modal. 
    // The table inside is refreshed by refrescarTablaProductosReserva()
    pone_productos_reserva(); // We still need to populate the dropdown for adding more items
    refrescarTablaProductosReserva();
    $("#modal-reservar .box-title").html(`Mi Carrito`);
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
    let producto_maestro = productosParaReserva.find(p => p.id_variedad == id_variedad);
    
    if (!id_variedad || !producto_maestro) {
        toastr.error("Debes seleccionar un producto.");
        return;
    }
    if (isNaN(cantidad) || cantidad <= 0) {
        toastr.error("La cantidad debe ser mayor a cero.");
        return;
    }
    
    let producto_existente = productosReserva.find(p => p.id_variedad == id_variedad);
    let cantidad_ya_en_carrito = producto_existente ? producto_existente.cantidad : 0;

    if (cantidad + cantidad_ya_en_carrito > producto_maestro.disponible) {
        toastr.error(`No puedes agregar más de ${producto_maestro.disponible} unidades. Ya tienes ${cantidad_ya_en_carrito} en el carrito.`);
        return;
    }

    if (producto_existente) {
        producto_existente.cantidad += cantidad;
    } else {
        productosReserva.push({
            id_variedad: id_variedad,
            nombre: producto_maestro.nombre_variedad + " (" + producto_maestro.codigo + producto_maestro.id_interno + ")",
            cantidad: cantidad,
            disponible: producto_maestro.disponible
        });
    }
    
    refrescarTablaProductosReserva();
    
    // Resetear controles del modal
    $("#select-producto-reserva").val('').selectpicker('refresh');
    $("#input-cantidad-reserva").val('');
    $("#input-cantidad-disponible2").val('');
}

function refrescarTablaProductosReserva() {
    let tablaBody = $("#tabla-productos-reserva tbody");
    tablaBody.empty();
    let totalItems = 0;
    productosReserva.forEach((p, index) => {
        totalItems += p.cantidad;
        let row = `<tr>
            <td>${p.nombre}</td>
            <td>${p.cantidad}</td>
            <td><button class="btn btn-danger btn-sm" onclick="eliminarProductoReserva(${index})"><i class="fa fa-trash"></i></button></td>
        </tr>`;
        tablaBody.append(row);
    });

    // Update cart button
    let cartButton = $("#btn-ver-carrito");
    if (cartButton.length) {
        let count = productosReserva.length; // Count of distinct items
        if (count > 0) {
            cartButton.html(`<i class='fa fa-shopping-cart'></i> VER CARRITO (${count})`);
        } else {
            cartButton.html(`<i class='fa fa-shopping-cart'></i> VER CARRITO`);
        }
    }
}

function eliminarProductoReserva(index) {
    productosReserva.splice(index, 1);
    refrescarTablaProductosReserva();
}

function guardarReserva() {
    const observaciones = $("#input-comentario-reserva").val().trim();

    if (productosReserva.length === 0) {
        toastr.error("Debes agregar al menos un producto a la Compra.");
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
                toastr.success("La Compra se ha guardado correctamente.");
                productosReserva = []; // Clear the cart
                busca_entradas(currentTab); // Refreshes the view, which will also update the cart button
            } else {
                toastr.error("Ocurrió un error al guardar la Compra", x);
                $("#modal-reservar").modal("show");
            }
        },
        error: function(){
            toastr.error("Error de conexión", "No se pudo conectar con el servidor");
            $("#modal-reservar").modal("show");
        }
    });
}
