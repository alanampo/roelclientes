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


    <title>Roelplant - Iniciar Sesión</title>
    <?php include "./class_lib/scripts.php"; ?>
    <?php include "./class_lib/links.php"; ?>
    <script src="dist/js/login.js?v=5"></script>
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
  <body onLoad="document.getElementById('UserName').focus();">
    

    <div class="container w-75 p-4">
      <div class="row">
        <div class="col-md-7">
          <img id="img-portada" src="dist/img/portada1.jpg" style="width:100%;max-height:93vh"/>
        </div>
        <div class="col-md-5">
          <div class="h-100 d-flex align-items-center login-outer">
          <form class="AjaxForms MainLogin" id="loginform" data-type-form="login" method="post" autocomplete="off">
          <h3 class="text-center mt-4 mb-4 font-weight-bold">ACCESO A CLIENTES</h3>
            <div align="center" class="mb-4"><img src="dist/img/roel.jpg" style="width: 150px;height:85px;"/></div>
            
            <div class="form-group">
              <label class="control-label" for="UserName">E-Mail</label>
              <input class="form-control" name="usuario" id="UserName" type="text" maxlength="50" required="">
            </div>
            <div class="form-group">
              <label class="control-label" for="Pass">Contraseña</label>
              <input class="form-control" name="pass" id="Pass" type="password" maxlength="30" required="">
            </div>
            <p class="text-center">
                <button type="submit" class="btn btn-primary btn-block  mt-4">Ingresar</button>        
            </p>
          </form>
        
</div> <!-- cierre de login-outer -->

<div class="mt-4 text-center" style="font-size:14px; line-height:1.5; background:#f8f9fa; border:1px solid #ddd; border-radius:8px; padding:10px;">
  <p>
    Para registrarte como cliente, contacta a nuestro equipo de ventas:<br>
    Telefonos: <a href="tel:+56984226651">+56 9 8422 6651</a> /
    <a href="tel:+56933217944">+56 9 3321 7944</a><br>
    Correos: <a href="mailto:ventas@roelplant.cl">ventas@roelplant.cl</a> /
    <a href="mailto:contacto@roelplant.cl">contacto@roelplant.cl</a>
  </p>
</div>

        
        
        
        </div>
        
      </div>
    </div>


    <div class="contenedor"></div>
    
<!-- Google Tag Manager (noscript) -->
<noscript><iframe src="https://www.googletagmanager.com/ns.html?id=GTM-PZ58VTFF"
height="0" width="0" style="display:none;visibility:hidden"></iframe></noscript>
<!-- End Google Tag Manager (noscript) -->
    
  </body>

</html>
