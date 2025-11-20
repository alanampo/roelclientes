<!DOCTYPE html>
<html>
  <head>
<meta charset="UTF-8">

<!-- Google Tag Manager -->
<script>(function(w,d,s,l,i){w[l]=w[l]||[];w[l].push({'gtm.start':
new Date().getTime(),event:'gtm.js'});var f=d.getElementsByTagName(s)[0],
j=d.createElement(s),dl=l!='dataLayer'?'&l='+l:'';j.async=true;j.src=
'https://www.googletagmanager.com/gtm.js?id='+i+dl;f.parentNode.insertBefore(j,f);
})(window,document,'script','dataLayer','GTM-PZ58VTFF');</script>
<!-- End Google Tag Manager -->


    <title>Roelplant - Registro de Cliente</title>
    <?php include "./class_lib/scripts.php"; ?>
    <?php include "./class_lib/links.php"; ?>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-select@1.13.14/dist/css/bootstrap-select.min.css">
    <script src="dist/js/registro.js?v=1"></script>
    <?php
      session_start();
      if (
        isset($_SESSION) &&
        isset($_SESSION["roel-clientes-token"]) &&
        isset($_COOKIE["roel-clientes-token"]) &&
        ($_SESSION["roel-clientes-token"] == $_COOKIE["roel-clientes-token"]) &&
        isset($_SESSION["id_cliente"]) &&
        isset($_COOKIE["roel-clientes-id"]) &&
        ($_SESSION["id_cliente"] == $_COOKIE["roel-clientes-id"])
      ){
        echo "<script>
                document.location.href = 'inicio.php';
              </script>
        ";
      }
    ?>
  </head>
  <body>


    <div class="container w-75 p-4">
      <div class="row">
        <div class="col-md-7">
          <img id="img-portada" src="dist/img/portada1.jpg" style="width:100%;max-height:93vh"/>
        </div>
        <div class="col-md-5">
          <div class="h-100 d-flex align-items-center login-outer" style="justify-content: center;">
          <form class="AjaxForms MainRegister" id="registerform" data-type-form="register" method="post" autocomplete="off">
          <h3 class="text-center mt-4 mb-4 font-weight-bold">REGISTRO DE CLIENTE</h3>
            <div align="center" class="mb-4"><img src="dist/img/roel.jpg" style="width: 150px;height:85px;"/></div>

            <div class="form-group">
              <label class="control-label" for="Email">E-Mail <span class="text-danger">*</span></label>
              <input class="form-control" name="email" id="Email" type="email" maxlength="100" required>
              <small class="form-text text-danger" id="email-error" style="display:none;"></small>
            </div>

            <div class="form-group">
              <label class="control-label" for="Password">Contraseña <span class="text-danger">*</span></label>
              <input class="form-control" name="password" id="Password" type="password" maxlength="50" required>
            </div>

            <div class="form-group">
              <label class="control-label" for="PasswordConfirm">Repetir Contraseña <span class="text-danger">*</span></label>
              <input class="form-control" name="password_confirm" id="PasswordConfirm" type="password" maxlength="50" required>
              <small class="form-text text-danger" id="password-error" style="display:none;"></small>
            </div>

            <hr class="my-4">

            <h5 class="mb-3">Datos del Cliente</h5>

            <div class="form-group">
              <label class="control-label" for="Nombre">Nombre / Razón Social <span class="text-danger">*</span></label>
              <input class="form-control" name="nombre" id="Nombre" type="text" maxlength="100" required>
            </div>

            <div class="form-group">
              <label class="control-label" for="RUT">RUT <span class="text-danger">*</span></label>
              <input class="form-control" name="rut" id="RUT" type="text" maxlength="12" placeholder="12.345.678-9" required>
              <small class="form-text text-danger" id="rut-error" style="display:none;"></small>
            </div>

            <div class="form-group">
              <label class="control-label" for="Telefono">Teléfono <span class="text-danger">*</span></label>
              <input class="form-control" name="telefono" id="Telefono" type="text" maxlength="20" required>
            </div>

            <div class="form-group">
              <label class="control-label" for="Domicilio">Domicilio <span class="text-danger">*</span></label>
              <input class="form-control" name="domicilio" id="Domicilio" type="text" maxlength="200" required>
            </div>

            <div class="form-group">
              <label class="control-label" for="Domicilio2">Domicilio de Entrega</label>
              <input class="form-control" name="domicilio2" id="Domicilio2" type="text" maxlength="200">
            </div>

            <div class="form-group">
              <label class="control-label" for="Comuna">Comuna <span class="text-danger">*</span></label>
              <select class="form-control selectpicker" name="comuna" id="Comuna" data-live-search="true" required>
                <option value="">Seleccione una comuna...</option>
              </select>
            </div>

            <div class="form-group">
              <label class="control-label" for="Ciudad">Ciudad <span class="text-danger">*</span></label>
              <input class="form-control" name="ciudad" id="Ciudad" type="text" maxlength="100" required>
            </div>

            <div class="form-group">
              <label class="control-label" for="Provincia">Provincia</label>
              <input class="form-control" name="provincia" id="Provincia" type="text" maxlength="100">
            </div>

            <div class="form-group">
              <label class="control-label" for="Region">Región</label>
              <input class="form-control" name="region" id="Region" type="text" maxlength="100">
            </div>

            <p class="text-center">
                <button type="submit" class="btn btn-primary btn-block mt-4">Registrarse</button>
                <a href="index.php" class="btn btn-link">Ya tengo cuenta, iniciar sesión</a>
            </p>
          </form>

</div> <!-- cierre de login-outer -->





        </div>

      </div>
    </div>


    <div class="contenedor"></div>

<!-- Google Tag Manager (noscript) -->
<noscript><iframe src="https://www.googletagmanager.com/ns.html?id=GTM-PZ58VTFF"
height="0" width="0" style="display:none;visibility:hidden"></iframe></noscript>
<!-- End Google Tag Manager (noscript) -->

<!-- Bootstrap Select JS -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap-select@1.13.14/dist/js/bootstrap-select.min.js"></script>

  </body>

</html>
