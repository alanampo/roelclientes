let currentTab;
let productosReserva = []; // This array now acts as the shopping cart
let productosParaReserva = [];
let phpFile = "data_ver_reservas.php";

toastr.options = {
  "closeButton": true, "debug": false, "newestOnTop": false, "progressBar": true,
  "positionClass": "toast-top-right", "preventDuplicates": false, "onclick": null,
  "showDuration": "300", "hideDuration": "1000", "timeOut": "5000", "extendedTimeOut": "1000",
  "showEasing": "swing", "hideEasing": "linear", "showMethod": "fadeIn", "hideMethod": "fadeOut"
};

$(document).ready(function () {
    $("#input-cantidad-reserva").on("propertychange input", function (e) { this.value = this.value.replace(/\D/g, ""); });
    $("#input-cantidad").on("propertychange input", function (e) { this.value = this.value.replace(/\D/g, ""); });
    document.getElementById("defaultOpen").click();
    $('#select-producto-reserva').on('changed.bs.select', function (e) {
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
    for (i = 0; i < tabcontent.length; i++) tabcontent[i].style.display = "none";
    tablinks = document.getElementsByClassName("tablinks");
    for (i = 0; i < tablinks.length; i++) tablinks[i].className = tablinks[i].className.replace(" active", "");
    evt.currentTarget.className += " active";
    busca_entradas(tabName);
}

function busca_entradas(tabName) {
    let consulta = (tabName == "reservas") ? "busca_reservas" : "busca_stock_actual";
    $.ajax({
        beforeSend: () => $("#tabla_entradas").html("Buscando, espere..."),
        url: phpFile, type: "POST", data: { consulta: consulta },
        success: function (x) {
            $("#tabla_entradas").html(x);
            $("#tabla-reservas, #tabla").DataTable({
                pageLength: 50,
                order: [tabName == "reservas" ? [1, "desc"] : [0, "asc"]],
                language: { lengthMenu: `Mostrando _MENU_ registros por página`, zeroRecords: `No hay registros`, info: "Página _PAGE_ de _PAGES_", infoEmpty: `No hay registros`, infoFiltered: `(filtrado de _MAX_ registros en total)`, search: "Buscar:", paginate: { first: "Primera", last: "Última", next: "Siguiente", previous: "Anterior" } }
            });
            updatePackingAndTotal();
        },
        error: (jqXHR, st, err) => $("#tabla_entradas").html(`Ocurrió un error: ${st} ${err}`)
    });
}

function agregarAlCarritoDesdeTabla(id_variedad, nombre_producto, disponible, inputId, precio, precio_detalle) {
    const cantidad = parseInt($(`#${inputId}`).val());
    if (isNaN(cantidad) || cantidad <= 0) return toastr.error("La cantidad debe ser un número mayor a cero.");
    if (cantidad > disponible) return toastr.error("La cantidad a reservar no puede ser mayor al stock disponible.");

    let producto_existente = productosReserva.find(p => p.id_variedad == id_variedad);
    if (producto_existente) {
        if (producto_existente.cantidad + cantidad > disponible) return toastr.error(`No puedes agregar más de ${disponible} unidades. Ya tienes ${producto_existente.cantidad} en el carrito.`);
        producto_existente.cantidad += cantidad;
    } else {
        productosReserva.push({ id_variedad, nombre: nombre_producto, cantidad, disponible, precio: parseFloat(precio) || 0, precio_detalle: parseFloat(precio_detalle) || 0 });
    }
    toastr.success(`Se agregaron ${cantidad} unidad(es) de ${nombre_producto} al carrito.`);
    updatePackingAndTotal();
}

function modalReservar() {
    pone_productos_reserva();
    updatePackingAndTotal();
    $("#modal-reservar .box-title").html(`Mi Carrito`);
    $("#modal-reservar").modal("show");
    $("#input-cantidad-reserva").focus();
}

function pone_productos_reserva() {
    $.ajax({
        url: phpFile, type: "POST", data: { consulta: "get_productos_para_reserva" }, dataType: 'json',
        success: function (data) {
            productosParaReserva = data;
            let options = '<option value="">Seleccione un producto...</option>';
            data.forEach(p => {
                 options += `<option value="${p.id_variedad}" data-disponible="${p.disponible}" data-precio="${p.precio}" data-precio_detalle="${p.precio_detalle}">${p.nombre_variedad} (${p.codigo}${p.id_interno}) - Disp: ${p.disponible}</option>`;
            });
            $("#select-producto-reserva").html(options).selectpicker("refresh");
        }
    });
}

function agregarProductoReserva() {
    let select = $("#select-producto-reserva");
    let id_variedad = select.val();
    let cantidad = parseInt($("#input-cantidad-reserva").val());
    let selectedOption = select.find('option:selected');
    if (!id_variedad) return toastr.error("Debes seleccionar un producto.");
    if (isNaN(cantidad) || cantidad <= 0) return toastr.error("La cantidad debe ser mayor a cero.");

    let disponible = parseInt(selectedOption.data('disponible'));
    let precio = parseFloat(selectedOption.data('precio')) || 0;
    let precio_detalle = parseFloat(selectedOption.data('precio_detalle')) || 0;
    let nombre = selectedOption.text().split(' - ')[0];
    
    let producto_existente = productosReserva.find(p => p.id_variedad == id_variedad);
    if (producto_existente && (producto_existente.cantidad + cantidad > disponible)) {
        return toastr.error(`No puedes agregar más de ${disponible} unidades. Ya tienes ${producto_existente.cantidad} en el carrito.`);
    }

    if (producto_existente) {
        producto_existente.cantidad += cantidad;
    } else {
        productosReserva.push({ id_variedad, nombre, cantidad, disponible, precio, precio_detalle });
    }
    
    updatePackingAndTotal();
    $("#select-producto-reserva").val('').selectpicker('refresh');
    $("#input-cantidad-reserva").val('');
    $("#input-cantidad-disponible2").val('');
}

function eliminarProductoReserva(index) {
    productosReserva.splice(index, 1);
    updatePackingAndTotal();
}

function updatePackingAndTotal() {
    let cartButton = $("#btn-ver-carrito");
    if (cartButton.length) {
        cartButton.html(productosReserva.length > 0 ? `<i class='fa fa-shopping-cart'></i>CARRITO (${productosReserva.length})` : `<i class='fa fa-shopping-cart'></i>CARRITO`);
    }

    const tablaBody = $("#tabla-productos-reserva tbody");
    const tablaFoot = $("#tabla-productos-reserva tfoot");
    tablaBody.empty();
    tablaFoot.empty();

    if (productosReserva.length === 0) {
        $("#grand-total-display").text("Total a Pagar: $0.00");
        return;
    }

    const ids_variedad = productosReserva.map(p => p.id_variedad);
    $.ajax({
        url: phpFile, type: "POST", data: { consulta: "get_product_details_for_packing", ids_variedad: JSON.stringify(ids_variedad) }, dataType: 'json',
        success: function(productDetails) {
            const result = calculatePackingAndPricing(productosReserva, productDetails);
            
            result.lineItems.forEach((item, idx) => { // Use idx for the loop index
                const row = `
                    <tr>
                        <td>${item.name}</td>
                        <td class="text-center">${item.quantity}</td>
                        <td class="text-right">$${item.unitPrice.toFixed(2)}</td>
                        <td class="text-right">$${item.subtotal.toFixed(2)}</td>
                        <td class="text-center">${item.originalIndex !== undefined ? '<button class="btn btn-danger btn-sm" onclick="eliminarProductoReserva(' + item.originalIndex + ')"><i class="fa fa-trash"></i></button>' : ''}</td>
                    </tr>`;
                
                if (item.name === 'PACKING') {
                    tablaFoot.append(row);
                } else {
                    tablaBody.append(row);
                }
            });
            $("#grand-total-display").text(`Total a Pagar: $${result.grandTotal.toFixed(2)}`);
        },
        error: () => toastr.error("Error al obtener detalles para el cálculo de packing.")
    });
}


function guardarReserva() {
    const observaciones = $("#input-comentario-reserva").val().trim();
    if (productosReserva.length === 0) return toastr.error("Debes agregar al menos un producto a la Compra.");
    $("#modal-reservar").modal("hide");
    $.ajax({
        type: "POST", url: phpFile, data: { consulta: "guardar_reserva", observaciones: observaciones, productos: JSON.stringify(productosReserva) },
        success: function (x) {
            if (x.trim() == "success") {
                toastr.success("La Compra se ha guardado correctamente.");
                productosReserva = [];
                busca_entradas(currentTab);
            } else {
                toastr.error("Ocurrió un error al guardar la Compra", x);
                $("#modal-reservar").modal("show");
            }
        },
        error: () => {
            toastr.error("Error de conexión", "No se pudo conectar con el servidor");
            $("#modal-reservar").modal("show");
        }
    });
}

function cancelarReserva(id_reserva) {
    swal("Estás seguro/a de CANCELAR la Compra?", "", {
        icon: "warning", buttons: { cancel: "NO", catch: { text: "SI, CANCELAR", value: "catch", } },
    }).then((value) => {
        if (value === "catch") {
            $.ajax({
                type: "POST", url: phpFile, data: { consulta: "cancelar_reserva", id_reserva: id_reserva },
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