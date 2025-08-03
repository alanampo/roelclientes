<?php
///****ARCHIVO DE FUNCIONES*****///

function generarBoxEstado($estado, $codigo, $fullWidth){
  $w100 = "";
  if ($fullWidth == true){
    $w100 = "w-100";
  }
  if ($codigo == "E" || $codigo == "HE") {
		$colores = [
		  "#D8EAD2",
		  "#B6D7A8",
		  "#A9D994",
		  "#A2D98A",
		  "#99D87D",
		  "#8AD868",
		];
	} else if ($codigo == "S" || $codigo == "HS") {
		$colores = [
		  "#FFF2CD",
		  "#FFE59A",
		  "#FED966",
		  "#F2C234",
		  "#E0B42F",
		  "#CEA62E",
		];
	}
	else{
		$colores = [
			"#ffffff",
			"#ffffff",
			"#ffffff",
			"#ffffff",
			"#ffffff",
			"#ffffff",
		  ];
	}
	if ($estado == -10){
		return "<div class='d-inline-block cajita w-100' style='background-color:#D8D8D8; padding:5px;'>PENDIENTE</div>";
	}
	else if ($estado == 0){
		return "<div class='d-inline-block cajita w-100' style='background-color:$colores[$estado]; padding:5px;'>ETAPA 0</div>";
	}
	else if ($estado == 1){
		return "<div class='d-inline-block cajita w-100' style='background-color:$colores[$estado]; padding:5px;'><span>ETAPA 1</span></div>";
	}
	else if ($estado == 2){
		return "<div class='d-inline-block cajita w-100' style='background-color:$colores[$estado]; padding:5px;'>ETAPA 2</div>";
	}
	else if ($estado == 3){
		return "<div class='d-inline-block cajita w-100' style='background-color:$colores[$estado]; padding:5px;'>ETAPA 3</div>";
	}
	else if ($estado == 4){
		return "<div class='d-inline-block cajita w-100' style='background-color:$colores[$estado]; padding:5px;'>ETAPA 4</div>";
	}
	else if ($estado == 5){
        return "<div class='d-inline-block cajita w-100' style='text-align:center;background-color:$colores[$estado]; padding:3px;'><div>ETAPA 5</div></div>";
    }
    else if ($estado == 6){
       	return "<div class='d-inline-block cajita w-100' style='text-align:center;background-color:#FFFF00; padding:3px; cursor:pointer;'><div>ENTREGA PARCIAL</div></div>";
      	}
    else if ($estado == 7){
        return "<div class='d-inline-block cajita w-100' style='text-align:center;background-color:#A9F5BC; padding:3px; cursor:pointer;'><div>ENTREGA COMPLETA</div></div>";
    }
	else if ($estado == 8){
        return "<div class='d-inline-block cajita w-100' style='text-align:center;background-color:#58D3F7; padding:3px; cursor:pointer;'><div>STOCK</div></div>";
    }
	else if ($estado == -1){
        return "<div class='d-inline-block cajita w-100' style='word-wrap:break-word;text-align:center;background-color:#FA5858; padding:3px; cursor:pointer;'>CANCELADO</div>";
    }
    else{
    	return "<div class='d-inline-block cajita w-100' style='background-color:#A4A4A4; padding:5px;'>NO DEFINIDO</div>";
		
    }
}

function boxEstadoReserva($estado,$fullWidth){
	$w100 = "";
	if ($fullWidth == true){
	  $w100 = "w-100";
	}
	
	  if ($estado == 0){
		  return "<div class='d-inline-block cajita w-100' style='background-color:#D8D8D8; padding:5px;'>PENDIENTE</div>";
	  }
	  else if ($estado == 1){
		return "<div class='d-inline-block cajita w-100' style='text-align:center;background-color:yellow; padding:3px; cursor:pointer;'><div>EN PROCESO</div></div>";
    	}
	  else if ($estado == 2){
		  return "<div class='d-inline-block cajita w-100' style='text-align:center;background-color:#A9F5BC; padding:3px; cursor:pointer;'><div>ENTREGADA</div></div>";
	  }
	  else if ($estado == -1){
		  return "<div class='d-inline-block cajita w-100' style='word-wrap:break-word;text-align:center;background-color:#FA5858; padding:3px; cursor:pointer;'>CANCELADA</div>";
	  }
	  else{
		  return "<div class='d-inline-block cajita w-100' style='background-color:#A4A4A4; padding:5px;'>NO DEFINIDO</div>";
		  
	  }
  }


function test_input($data){
  $data = trim($data);
  $data = stripslashes($data);
  $data = htmlspecialchars($data);
  return $data;
}
?>