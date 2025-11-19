$(document).ready(function() {
    // Cargar comunas al iniciar
    loadComunas();

    // Validación de email en tiempo real
    $('#Email').on('blur', function() {
        const email = $(this).val().trim();
        if (email.length > 0) {
            checkEmailDuplicate(email);
        }
    });

    // Validación de contraseñas en tiempo real
    $('#PasswordConfirm').on('blur', function() {
        validatePasswordMatch();
    });

    // Validación de RUT en tiempo real
    $('#RUT').on('blur', function() {
        const rut = $(this).val().trim();
        if (rut.length > 0) {
            validateRUT(rut);
        }
    });

    // Formatear RUT mientras se escribe
    $('#RUT').on('input', function() {
        let rut = $(this).val().replace(/[^0-9kK]/g, '');
        if (rut.length > 1) {
            const dv = rut.slice(-1);
            const number = rut.slice(0, -1);
            rut = number.replace(/\B(?=(\d{3})+(?!\d))/g, '.') + '-' + dv;
        }
        $(this).val(rut);
    });

    // Manejo del formulario
    $('#registerform').on('submit', function(e) {
        e.preventDefault();

        // Limpiar errores previos
        $('.form-text.text-danger').hide();
        $('.form-control').removeClass('is-invalid');

        // Validar contraseñas
        if (!validatePasswordMatch()) {
            return false;
        }

        // Validar RUT
        const rut = $('#RUT').val().trim();
        if (!isValidRUTFormat(rut)) {
            $('#rut-error').text('Formato de RUT inválido').show();
            $('#RUT').addClass('is-invalid');
            return false;
        }

        // Validar campos requeridos
        let hasErrors = false;
        $('input[required], select[required]').each(function() {
            if (!$(this).val()) {
                $(this).addClass('is-invalid');
                hasErrors = true;
            }
        });

        if (hasErrors) {
            alert('Por favor, complete todos los campos requeridos');
            return false;
        }

        // Enviar formulario
        submitRegistration();
    });
});

// Cargar comunas desde el servidor
function loadComunas() {
    $.ajax({
        url: 'server/comunas.php',
        type: 'GET',
        dataType: 'json',
        success: function(response) {
            const comunaSelect = $('#Comuna');
            comunaSelect.empty();
            comunaSelect.append('<option value="">Seleccione una comuna...</option>');

            if (response.success && response.comunas) {
                response.comunas.forEach(function(comuna) {
                    comunaSelect.append(
                        `<option value="${comuna.id}" data-region="${comuna.region}">
                            ${comuna.nombre} (${comuna.region})
                        </option>`
                    );
                });
                // Refresh selectpicker
                comunaSelect.selectpicker('refresh');
            }
        },
        error: function(xhr, status, error) {
            console.error('Error cargando comunas:', error);
            alert('Error al cargar las comunas. Por favor, recargue la página.');
        }
    });
}

// Verificar si el email ya existe
function checkEmailDuplicate(email) {
    $.ajax({
        url: 'procesa_registro.php',
        type: 'POST',
        data: {
            consulta: 'check_email',
            email: email
        },
        dataType: 'json',
        success: function(response) {
            if (response.exists) {
                $('#email-error').text('Este email ya está registrado').show();
                $('#Email').addClass('is-invalid');
            } else {
                $('#email-error').hide();
                $('#Email').removeClass('is-invalid');
            }
        },
        error: function() {
            console.error('Error al verificar email');
        }
    });
}

// Validar que las contraseñas coincidan
function validatePasswordMatch() {
    const password = $('#Password').val();
    const passwordConfirm = $('#PasswordConfirm').val();

    if (password !== passwordConfirm) {
        $('#password-error').text('Las contraseñas no coinciden').show();
        $('#PasswordConfirm').addClass('is-invalid');
        return false;
    } else {
        $('#password-error').hide();
        $('#PasswordConfirm').removeClass('is-invalid');
        return true;
    }
}

// Validar formato de RUT chileno
function isValidRUTFormat(rut) {
    // Limpiar el RUT
    rut = rut.replace(/[^0-9kK]/g, '');

    if (rut.length < 2) {
        return false;
    }

    const dv = rut.slice(-1).toLowerCase();
    const number = parseInt(rut.slice(0, -1));

    if (isNaN(number)) {
        return false;
    }

    // Calcular dígito verificador
    let suma = 0;
    let multiplo = 2;

    for (let i = number.toString().length - 1; i >= 0; i--) {
        suma += parseInt(number.toString()[i]) * multiplo;
        multiplo = multiplo === 7 ? 2 : multiplo + 1;
    }

    const resto = suma % 11;
    const dvCalculado = 11 - resto;

    let dvEsperado;
    if (dvCalculado === 11) {
        dvEsperado = '0';
    } else if (dvCalculado === 10) {
        dvEsperado = 'k';
    } else {
        dvEsperado = dvCalculado.toString();
    }

    return dv === dvEsperado;
}

// Validar RUT completo (formato y duplicados)
function validateRUT(rut) {
    // Primero validar formato
    if (!isValidRUTFormat(rut)) {
        $('#rut-error').text('Formato de RUT inválido').show();
        $('#RUT').addClass('is-invalid');
        return;
    }

    // Luego verificar duplicados
    $.ajax({
        url: 'procesa_registro.php',
        type: 'POST',
        data: {
            consulta: 'check_rut',
            rut: rut
        },
        dataType: 'json',
        success: function(response) {
            if (response.exists) {
                $('#rut-error').text('Este RUT ya está registrado').show();
                $('#RUT').addClass('is-invalid');
            } else {
                $('#rut-error').hide();
                $('#RUT').removeClass('is-invalid');
            }
        },
        error: function() {
            console.error('Error al verificar RUT');
        }
    });
}

// Enviar el formulario de registro
function submitRegistration() {
    const formData = {
        consulta: 'register',
        email: $('#Email').val().trim(),
        password: $('#Password').val(),
        nombre: $('#Nombre').val().trim(),
        rut: $('#RUT').val().trim(),
        telefono: $('#Telefono').val().trim(),
        domicilio: $('#Domicilio').val().trim(),
        domicilio2: $('#Domicilio2').val().trim(),
        comuna: $('#Comuna').val(),
        ciudad: $('#Ciudad').val().trim(),
        provincia: $('#Provincia').val().trim(),
        region: $('#Region').val().trim()
    };

    // Mostrar indicador de carga
    const $submitBtn = $('#registerform button[type="submit"]');
    const originalText = $submitBtn.text();
    $submitBtn.prop('disabled', true).text('Registrando...');

    $.ajax({
        url: 'procesa_registro.php',
        type: 'POST',
        data: formData,
        dataType: 'json',
        success: function(response) {
            if (response.success) {
                alert('Registro exitoso. Será redirigido al inicio de sesión.');
                window.location.href = 'index.php';
            } else {
                alert('Error en el registro: ' + (response.message || 'Error desconocido'));
                $submitBtn.prop('disabled', false).text(originalText);
            }
        },
        error: function(xhr, status, error) {
            console.error('Error en registro:', error);
            alert('Error al procesar el registro. Por favor, intente nuevamente.');
            $submitBtn.prop('disabled', false).text(originalText);
        }
    });
}
