$(document).ready(function () {
  $(".btn-exit-system").on("click", function (e) {
    e.preventDefault();
    cerrarSesion();
  });
});

function cerrarSesion() {
  swal("¿Estás seguro/a de Cerrar Sesión?", "", {
    icon: "warning",
    buttons: {
      cancel: "Cancelar",
      catch: {
        text: "Cerrar Sesión",
        value: "catch",
      },
    },
  }).then((value) => {
    switch (value) {
      case "catch":
        window.location.href = "endsession.php";
      default:
        break;
    }
  });
}

function abrirControlVivero() {
  setClickList();
  swal(
    "Esta funcionalidad estará disponible Proximamente!",
    "Para más información escríbenos al +56 9 7291 2979",
	"info"
  );
}

function setClickList() {
  $.ajax({
    beforeSend: function () {},
    url: "data_ver_pedidos.php",
    type: "POST",
    data: {
      consulta: "set_click",
    },
    success: function (x) {},
    error: function (jqXHR, estado, error) {
      console.log("ERROR")
    },
  });
}

function pone_tendencias() {
	$(".col-tendencias")
	  .html(
		`
		  <a href="ver_tendencias.php">
			<div class="small-box" style="background-color:#F781D8"> 
			  <div class="inner"  style="height:7.3em;">    
				<p style='color:black'>Tendencias</p>
			  </div>
			  <div class="icon">
				<i style="color:rgba(0, 0, 0, 0.15);" class="fa fa-area-chart"></i>
			  </div>
			  <span class="small-box-footer" style="background-color:rgba(0, 0, 0, 0.1);">Ver Tendencias <i class="fa fa-arrow-circle-right"></i></span>
			</div>
		  </a>
		`
	  )
	  .removeClass("d-none");
  }

  function pone_reservas() {
    $(".col-reservas")
      .html(
      `
        <a href="ver_reservas.php">
        <div class="small-box" style="background-color:#01DF3A">
          <div class="inner"  style="height:7.3em;">
          <p style='color:black'>Comprar Productos</p>
          </div>
          <div class="icon">
          <i style="color:rgba(0, 0, 0, 0.15);" class="fa fa-shopping-basket"></i>
          </div>
          <span class="small-box-footer" style="background-color:rgba(0, 0, 0, 0.1);">Comprar <i class="fa fa-arrow-circle-right"></i></span>
        </div>
        </a>
      `
      )
      .removeClass("d-none");
    }

  function pone_agente() {
    $(".col-agente")
      .html(
      `
        <a href="agente-ai.php">
        <div class="small-box" style="background-color:#9333EA">
          <div class="inner"  style="height:7.3em;">
          <p style='color:white'>Agente de Ventas</p>
          </div>
          <div class="icon">
          <i style="color:rgba(255, 255, 255, 0.15);" class="fa fa-comments"></i>
          </div>
          <span class="small-box-footer" style="background-color:rgba(0, 0, 0, 0.1); color:white;">Abrir Agente <i class="fa fa-arrow-circle-right"></i></span>
        </div>
        </a>
      `
      )
      .removeClass("d-none");
    }

  function pone_planificacionpedidos() {
	$.ajax({
	  url: "pone_boxes.php",
	  type: "POST",
	  data: {tipo: "seguimiento"},
	  success: function (x) {
		$(".col-planificacion").html(x).removeClass("d-none");
	  },
	});
  }

  function pone_pedidos() {
	$.ajax({
	  url: "pone_boxes.php",
	  type: "POST",
	  data: {tipo: "pedidos"},
	  success: function (x) {
		$(".col-pedidos").html(x).removeClass("d-none");
	  },
	});
  }