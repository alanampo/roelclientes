var intentos = 0;
$(document).ready(function () {
  let form = document.getElementById("loginform");
  form.addEventListener("submit", (event) => {
    // handle the form data
    event.preventDefault();
    const user = $("#UserName").val().trim();
    const pass = $("#Pass").val().trim();
    login(user, pass);
  });
});

function login(user, pass) {
    if (intentos >= 4){
        swal("Demasiados intentos. Espera unos minutos.", "", "error")
        setTimeout(()=>{ 
            intentos = 0;
        },60000)
        return;
    }

    $.ajax({
        url: "valida_usr.php",
        type: "POST",
        data: { user: user, pass: pass },
        success: function (x) {
          console.log(x)
        $(".contenedor").html(x);
        intentos++;
        },
        error: function (jqXHR, estado, error) {
        swal("Error al Iniciar Sesión", error, "error");
        },
    });
}
