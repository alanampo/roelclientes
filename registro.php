<!DOCTYPE html>
<html>

<head>
  <meta charset="UTF-8">

  <!-- Google Tag Manager -->
  <script>(function (w, d, s, l, i) {
      w[l] = w[l] || []; w[l].push({
        'gtm.start':
          new Date().getTime(), event: 'gtm.js'
      }); var f = d.getElementsByTagName(s)[0],
        j = d.createElement(s), dl = l != 'dataLayer' ? '&l=' + l : ''; j.async = true; j.src =
          'https://www.googletagmanager.com/gtm.js?id=' + i + dl; f.parentNode.insertBefore(j, f);
    })(window, document, 'script', 'dataLayer', 'GTM-PZ58VTFF');</script>
  <!-- End Google Tag Manager -->


  <title>Roelplant - Registro de Cliente</title>
  <?php include "./class_lib/scripts.php"; ?>
  <?php include "./class_lib/links.php"; ?>
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-select@1.13.14/dist/css/bootstrap-select.min.css">
  <script src="dist/js/registro.js?v=2"></script>
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
  ) {
    echo "<script>
                document.location.href = 'inicio.php';
              </script>
        ";
  }
  ?>
  <style type="text/css">
    html {
      background: #fff;
    }
  </style>
</head>

<body>


  <div class="container p-4">
    <div class="row">
      <div class="col-md-7">
        <img id="img-portada" src="dist/img/portada1.jpg" style="width:100%;max-height:93vh" />
      </div>
      <div class="col-md-5">
        <div class="h-100 d-flex align-items-center login-outer" style="justify-content: center;">
          <form class="AjaxForms MainRegister" id="registerform" data-type-form="register" method="post"
            autocomplete="off">
            <h3 class="text-center mt-4 mb-4 font-weight-bold">REGISTRO DE CLIENTE</h3>
            <div align="center" class="mb-4"><img src="dist/img/roel.jpg" style="width: 150px;height:85px;" /></div>

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
              <label class="control-label" for="PasswordConfirm">Repetir Contraseña <span
                  class="text-danger">*</span></label>
              <input class="form-control" name="password_confirm" id="PasswordConfirm" type="password" maxlength="50"
                required>
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
              <input class="form-control" name="rut" id="RUT" type="text" maxlength="12" placeholder="12.345.678-9"
                required>
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
              <select class="form-control selectpicker" name="comuna" id="Comuna" data-live-search="true" data-none-selected-text="Selecciona una comuna" required>
                <option value="">Seleccione una comuna...</option>
              </select>
            </div>

            <div class="form-group">
              <label class="control-label" for="Ciudad">Ciudad <span class="text-danger">*</span></label>
              <input class="form-control" name="ciudad" id="Ciudad" type="text" maxlength="100" required>
            </div>

            <div class="form-group">
              <label class="control-label" for="Region">Región</label>
              <select class="form-control selectpicker" name="region" id="Region" data-live-search="true" data-none-selected-text="Selecciona una región">
                <option value="">Seleccione una región...</option>
                <option value="Arica y Parinacota">Arica y Parinacota</option>
                <option value="Tarapacá">Tarapacá</option>
                <option value="Antofagasta">Antofagasta</option>
                <option value="Atacama">Atacama</option>
                <option value="Coquimbo">Coquimbo</option>
                <option value="Valparaíso">Valparaíso</option>
                <option value="Metropolitana de Santiago">Metropolitana de Santiago</option>
                <option value="O'Higgins">O'Higgins</option>
                <option value="Maule">Maule</option>
                <option value="Ñuble">Ñuble</option>
                <option value="Biobío">Biobío</option>
                <option value="La Araucanía">La Araucanía</option>
                <option value="Los Ríos">Los Ríos</option>
                <option value="Los Lagos">Los Lagos</option>
                <option value="Aysén">Aysén</option>
                <option value="Magallanes y de la Antártica Chilena">Magallanes y de la Antártica Chilena</option>
              </select>
            </div>

            <div class="form-group">
              <label class="control-label" for="Provincia">Provincia</label>
              <select class="form-control selectpicker" name="provincia" id="Provincia" data-live-search="true" data-none-selected-text="Selecciona una provincia">
                <option value="">Seleccione una provincia...</option>
                <optgroup label="Arica y Parinacota">
                  <option value="Arica" data-region="Arica y Parinacota">Arica</option>
                  <option value="Parinacota" data-region="Arica y Parinacota">Parinacota</option>
                </optgroup>
                <optgroup label="Tarapacá">
                  <option value="Iquique" data-region="Tarapacá">Iquique</option>
                  <option value="El Tamarugal" data-region="Tarapacá">El Tamarugal</option>
                </optgroup>
                <optgroup label="Antofagasta">
                  <option value="Tocopilla" data-region="Antofagasta">Tocopilla</option>
                  <option value="El Loa" data-region="Antofagasta">El Loa</option>
                  <option value="Antofagasta" data-region="Antofagasta">Antofagasta</option>
                </optgroup>
                <optgroup label="Atacama">
                  <option value="Chañaral" data-region="Atacama">Chañaral</option>
                  <option value="Copiapó" data-region="Atacama">Copiapó</option>
                  <option value="Huasco" data-region="Atacama">Huasco</option>
                </optgroup>
                <optgroup label="Coquimbo">
                  <option value="Elqui" data-region="Coquimbo">Elqui</option>
                  <option value="Limarí" data-region="Coquimbo">Limarí</option>
                  <option value="Choapa" data-region="Coquimbo">Choapa</option>
                </optgroup>
                <optgroup label="Valparaíso">
                  <option value="Petorca" data-region="Valparaíso">Petorca</option>
                  <option value="Los Andes" data-region="Valparaíso">Los Andes</option>
                  <option value="San Felipe de Aconcagua" data-region="Valparaíso">San Felipe de Aconcagua</option>
                  <option value="Quillota" data-region="Valparaíso">Quillota</option>
                  <option value="Valparaíso" data-region="Valparaíso">Valparaíso</option>
                  <option value="San Antonio" data-region="Valparaíso">San Antonio</option>
                  <option value="Isla de Pascua" data-region="Valparaíso">Isla de Pascua</option>
                  <option value="Marga Marga" data-region="Valparaíso">Marga Marga</option>
                </optgroup>
                <optgroup label="Metropolitana de Santiago">
                  <option value="Chacabuco" data-region="Metropolitana de Santiago">Chacabuco</option>
                  <option value="Santiago" data-region="Metropolitana de Santiago">Santiago</option>
                  <option value="Cordillera" data-region="Metropolitana de Santiago">Cordillera</option>
                  <option value="Maipo" data-region="Metropolitana de Santiago">Maipo</option>
                  <option value="Melipilla" data-region="Metropolitana de Santiago">Melipilla</option>
                  <option value="Talagante" data-region="Metropolitana de Santiago">Talagante</option>
                </optgroup>
                <optgroup label="O'Higgins">
                  <option value="Cachapoal" data-region="O'Higgins">Cachapoal</option>
                  <option value="Colchagua" data-region="O'Higgins">Colchagua</option>
                  <option value="Cardenal Caro" data-region="O'Higgins">Cardenal Caro</option>
                </optgroup>
                <optgroup label="Maule">
                  <option value="Curicó" data-region="Maule">Curicó</option>
                  <option value="Talca" data-region="Maule">Talca</option>
                  <option value="Linares" data-region="Maule">Linares</option>
                  <option value="Cauquenes" data-region="Maule">Cauquenes</option>
                </optgroup>
                <optgroup label="Ñuble">
                  <option value="Diguillín" data-region="Ñuble">Diguillín</option>
                  <option value="Itata" data-region="Ñuble">Itata</option>
                  <option value="Punilla" data-region="Ñuble">Punilla</option>
                </optgroup>
                <optgroup label="Biobío">
                  <option value="Bio Bío" data-region="Biobío">Bio Bío</option>
                  <option value="Concepción" data-region="Biobío">Concepción</option>
                  <option value="Arauco" data-region="Biobío">Arauco</option>
                </optgroup>
                <optgroup label="La Araucanía">
                  <option value="Malleco" data-region="La Araucanía">Malleco</option>
                  <option value="Cautín" data-region="La Araucanía">Cautín</option>
                </optgroup>
                <optgroup label="Los Ríos">
                  <option value="Valdivia" data-region="Los Ríos">Valdivia</option>
                  <option value="Ranco" data-region="Los Ríos">Ranco</option>
                </optgroup>
                <optgroup label="Los Lagos">
                  <option value="Osorno" data-region="Los Lagos">Osorno</option>
                  <option value="Llanquihue" data-region="Los Lagos">Llanquihue</option>
                  <option value="Chiloé" data-region="Los Lagos">Chiloé</option>
                  <option value="Palena" data-region="Los Lagos">Palena</option>
                </optgroup>
                <optgroup label="Aysén">
                  <option value="Coyhaique" data-region="Aysén">Coyhaique</option>
                  <option value="Aysén" data-region="Aysén">Aysén</option>
                  <option value="General Carrera" data-region="Aysén">General Carrera</option>
                  <option value="Capitán Prat" data-region="Aysén">Capitán Prat</option>
                </optgroup>
                <optgroup label="Magallanes y de la Antártica Chilena">
                  <option value="Última Esperanza" data-region="Magallanes y de la Antártica Chilena">Última Esperanza
                  </option>
                  <option value="Magallanes" data-region="Magallanes y de la Antártica Chilena">Magallanes</option>
                  <option value="Tierra del Fuego" data-region="Magallanes y de la Antártica Chilena">Tierra del Fuego
                  </option>
                  <option value="Antártica Chilena" data-region="Magallanes y de la Antártica Chilena">Antártica Chilena
                  </option>
                </optgroup>
              </select>
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
  <noscript><iframe src="https://www.googletagmanager.com/ns.html?id=GTM-PZ58VTFF" height="0" width="0"
      style="display:none;visibility:hidden"></iframe></noscript>
  <!-- End Google Tag Manager (noscript) -->

  <!-- Bootstrap Select JS -->
  <script src="https://cdn.jsdelivr.net/npm/bootstrap-select@1.13.14/dist/js/bootstrap-select.min.js"></script>

</body>

</html>