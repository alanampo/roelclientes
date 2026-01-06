BACKOFFICE (Roelplant) — instalación rápida

1) Copiar la carpeta:
   catalogo_detalle/backoffice/

2) Configurar credenciales admin:
   editar: catalogo_detalle/backoffice/config_admin.php
   - ADMIN_USER
   - ADMIN_PASS

3) Acceso:
   https://TU-DOMINIO/cartXX/catalogo_detalle/backoffice/login.php

Notas:
- Este backoffice usa la MISMA BD del carrito (config/cart_db.php).
- Todas las acciones POST usan CSRF (mismo helper del proyecto).
