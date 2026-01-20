# Catálogo Mayorista (carrito + checkout) – Roelplant

Esta carpeta es una copia del sistema **catalogo_detalle v10**, adaptada para operar como **catálogo mayorista**.

## Cambios principales

1) **Precios**
- El catálogo usa **precio mayorista** (`precio`) como base.
- El frontend muestra precio con **IVA incluido** (x1.19), igual que el sistema anterior.

2) **Reglas de armado de pedido (mayorista)**
- **Cada especie mínimo 50 unidades**.
- **Total del pedido ≥ 200 unidades**.
- Se valida en:
  - UI del catálogo (carrito + checkout)
  - Backend (API `cart/add`, `cart/update`, `order/create`) para que no se pueda saltar por Postman, etc.

3) **BD separada**
- La BD del carrito/pedidos mayorista se separa en:
  - `roeluser1_carrito_mayorista`

## 1) Crear la base de datos

Importa el archivo SQL:

- `roeluser1_carrito_mayorista.sql`

Ejemplo (CLI):

```bash
mysql -u TU_USER -p < roeluser1_carrito_mayorista.sql
```

Si usas phpMyAdmin:
- Crea la BD `roeluser1_carrito_mayorista`
- Importa el archivo.

## 2) Configuración de conexión

Editar:
- `config/cart_db.php`

Verifica:
- `CART_DB_HOST`
- `CART_DB_USER`
- `CART_DB_PASS`
- `CART_DB_NAME = 'roeluser1_carrito_mayorista'`

## 3) Despliegue

1. Subir esta carpeta al servidor, por ejemplo:

```
/public_html/clientes/catalogo_mayorista/
```

2. Asegurar permisos de lectura.

3. Probar:
- Registro/login
- Agregar productos (debe forzar mínimo 50)
- Ir a checkout (no deja crear pedido si total < 200)
- Crear pedido (genera link de WhatsApp)

## 4) Notas

- El **envío** queda como **por pagar** (igual que el detalle).
- El **packing** se calcula automáticamente por cantidad total.
- El prefijo de código de pedido se cambió a `RPM` en `config/app.php`.
