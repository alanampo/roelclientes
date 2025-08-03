<section class="sidebar">

          <!-- Sidebar user panel (optional) -->
          <div class="user-panel">
            <div class="pull-left image">
              <img src="dist/img/avatar.png" class="img-circle" alt="User Image">
            </div>
            <div class="pull-left info">
              <p><?php echo $_SESSION['nombre_de_usuario'] ?></p>
              <!-- Status -->
              <a href="#"><i class="fa fa-circle text-success"></i> Conectado</a>
            </div>
          </div>


          <!-- Sidebar Menu -->
          <ul class="sidebar-menu">
            <li class="treeview">
              <a href="#"><i class="fa fa-bars"></i> <span>Panel de Cliente</span> <i class="fa fa-angle-left pull-right"></i></a>
              
              <ul class="treeview-menu menu-open" style="display:block;"> 
                            <li><a href="inicio.php"><i class="fa fa-arrow-circle-right"></i> Mis Pedidos</a></li> 
                            <li><a href="ver_seguimiento.php"><i class="fa fa-arrow-circle-right"></i> Producción</a></li> 
                            <li><a href="ver_tendencias.php"><i class="fa fa-arrow-circle-right"></i> Tendencias</a></li> 
                            <li><a onClick="cerrarSesion()" style="cursor:pointer; text-decoration:none;"><i class="fa fa-arrow-circle-right"></i> Cerrar Sesión</a></li> 
                            
                          </ul>

                          <a href="#"><i class="fa fa-bars"></i> <span>Control Vivero</span> <i class="fa fa-angle-left pull-right"></i></a>
              
              <ul class="treeview-menu menu-open" style="display:block;"> 
                            <li><a onClick="abrirControlVivero()" style="cursor:pointer; text-decoration:none;"><i class="fa fa-arrow-circle-right"></i> Ingresar (Nuevo!)</a></li> 
                            
                            
                          </ul>
             
                          
            </li> 

            
          </ul><!-- /.sidebar-menu -->
        </section>
