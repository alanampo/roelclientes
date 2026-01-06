# Migración: roel_carrito → roel

## ✅ Estado: Estructura Creada

Esta es una guía completa de la migración de la BD `roel_carrito` a la BD unificada `roel`.

---

## 📋 Resumen de Cambios

### 1. **Nuevas Tablas Creadas en `roel`**

| Tabla Original | Nueva Tabla | Propósito |
|---|---|---|
| `carts` | `carrito_carts` | Carritos de compra |
| `cart_items` | `carrito_cart_items` | Items en carritos |
| `orders` | `carrito_orders` | Órdenes de venta |
| `order_items` | `carrito_order_items` | Items en órdenes |
| `production_requests` | `carrito_production_requests` | Solicitudes de producción |
| `production_request_items` | `carrito_production_request_items` | Items en solicitudes |
| `backoffice_admins` | `backoffice_admins` | Administradores (mismo nombre) |
| `backoffice_audit` | `backoffice_audit` | Auditoría (mismo nombre) |

### 2. **Cambios de Arquitectura**

**Antes (Dos BDs separadas):**
```
┌─────────────────────────┐       ┌──────────────────────────┐
│    roel (principal)     │       │  roel_carrito (aislada)  │
│ - usuarios              │       │ - customers              │
│ - clientes              │       │ - carts                  │
│ - reservas_productos    │       │ - orders                 │
│ - etc...                │       │ - backoffice_admins      │
└─────────────────────────┘       └──────────────────────────┘
```

**Ahora (BD unificada):**
```
┌────────────────────────────────────┐
│     roel (unificada)               │
│ ├─ usuarios (auth)                 │
│ ├─ clientes (datos cliente)        │
│ ├─ carrito_carts                   │
│ ├─ carrito_cart_items              │
│ ├─ carrito_orders                  │
│ ├─ carrito_order_items             │
│ ├─ carrito_production_requests     │
│ ├─ carrito_production_request_items│
│ ├─ backoffice_admins               │
│ ├─ backoffice_audit                │
│ ├─ reservas_productos              │
│ └─ ... (resto de tablas)           │
└────────────────────────────────────┘
```

---

## 🔄 Mapeo de Datos: customers → clientes

**La tabla `customers` de `roel_carrito` ahora se mapea a `clientes` en `roel`:**

| Campo | customers | clientes |
|---|---|---|
| ID | `customers.id` | `clientes.id_cliente` |
| RUT | `customers.rut` | `clientes.rut` |
| Nombre | `customers.nombre` | `clientes.nombre` |
| Email | `customers.email` | `clientes.mail` + `usuarios.nombre` |
| Teléfono | `customers.telefono` | `clientes.telefono` |
| Región | `customers.region` | `clientes.region` |
| Comuna | `customers.comuna` (texto) | `clientes.comuna` (FK a comunas.id) |
| Password | `customers.password_hash` | `usuarios.password` |
| Estado | `customers.is_active` | `clientes.activo` |

---

## 🔧 Configuración: Local vs Hosting

### Archivo: `catalogo_detalle/config/cart_db.php`

```php
define('ENVIRONMENT', 'local'); // Cambiar a 'hosting' en producción

// ENVIRONMENT='local' → BD roel (127.0.0.1, root, sin password)
// ENVIRONMENT='hosting' → BD roeluser1_carrito (con credenciales de hosting)

// Tablas siempre con prefijo "carrito_" en ambos entornos
define('CART_TABLE', 'carrito_carts');
define('CART_ITEMS_TABLE', 'carrito_cart_items');
define('ORDERS_TABLE', 'carrito_orders');
define('ORDER_ITEMS_TABLE', 'carrito_order_items');
define('PROD_REQUESTS_TABLE', 'carrito_production_requests');
define('PROD_REQUEST_ITEMS_TABLE', 'carrito_production_request_items');
```

---

## 📝 Funcionalidades Actualizadas

### ✅ Autenticación (`/api/auth/`)

**register.php**
- Local: Crea registros en `usuarios` (tipo_usuario=0) + `clientes`
- Hosting: Crea registros en `customers` (legacy)
- Mapea nombre de comuna a ID en local
- Mantiene sesión compatible en ambos casos

**login.php**
- Local: Autentica contra `usuarios.nombre` (email) con tipo_usuario=0
- Hosting: Autentica contra `customers.email` (legacy)
- Retorna `$_SESSION['customer_id']` = `id_cliente` (local) o `customers.id` (hosting)

### ✅ Perfil de Cliente (`/api/customer/`)

**profile.php**
- Local: Lee desde `clientes` usando `id_cliente`
- Hosting: Lee desde `customers` usando `id`
- Convierte `clientes.comuna` (FK int) a nombre de comuna en local

**update.php**
- Local: Actualiza `clientes` + `usuarios`
- Hosting: Actualiza `customers`
- Convierte nombre de comuna a ID en local
- Soporta cambio de contraseña

### ✅ Carrito y Órdenes (`/_bootstrap.php`)

**cart_get_or_create()**
- Local: Usa campo `id_cliente`
- Hosting: Usa campo `customer_id`
- Usa constante `CART_TABLE` dinámicamente

**cart_snapshot()**
- Lee de `CART_ITEMS_TABLE` dinámicamente
- Compatible con ambos entornos

---

## 📊 Datos: Cómo Migrar

El archivo `catalogo_detalle/sql/migrate_roel_carrito_to_roel.sql` contiene:

1. **Creación de tablas** (EJECUTADO ✅)
2. **Scripts de migración de datos** (COMENTADO - ejecutar manualmente)

### Paso a Paso para Migrar Datos:

```bash
# 1. Hacer backup de roel_carrito
mysqldump -u roeluser1_cart_user -p'g]3,+[-*NneM@sA{' roeluser1_carrito > backup_roel_carrito.sql

# 2. En roel, descomenta y ejecuta los INSERT de migración en:
catalogo_detalle/sql/migrate_roel_carrito_to_roel.sql

# 3. Verificar que los datos migraron correctamente:
mysql -u root roel -e "
  SELECT COUNT(*) as total_carros FROM carrito_carts;
  SELECT COUNT(*) as total_ordenes FROM carrito_orders;
  SELECT COUNT(*) as total_prod_requests FROM carrito_production_requests;
"
```

---

## ⚠️ Importante: Pasos Pendientes

### 1. **Crear tabla `carrito_` en hosting (roeluser1_carrito)**

Si cambias a `ENVIRONMENT='hosting'`, debes asegurarte de que la BD `roeluser1_carrito` también tenga las tablas con prefijo. Opcionalmente:

```bash
mysql -u roeluser1_cart_user -p'g]3,+[-*NneM@sA{' roeluser1_carrito < catalogo_detalle/sql/migrate_roel_carrito_to_roel.sql
```

### 2. **Revisar y actualizar todos los archivos que consulten carrito/orders**

Busca referencias a tablas sin prefijo:
```bash
grep -r "FROM carts\|FROM orders\|FROM cart_items\|FROM order_items" catalogo_detalle/
```

Reemplaza con los constantes:
- `carts` → `' . CART_TABLE . '`
- `cart_items` → `' . CART_ITEMS_TABLE . '`
- `orders` → `' . ORDERS_TABLE . '`
- `order_items` → `' . ORDER_ITEMS_TABLE . '`

### 3. **Actualizar todas las Foreign Keys**

En local, todas las FK deben apuntar a:
- `carrito_carts.id_cliente` → `clientes.id_cliente`
- `carrito_production_requests.id_cliente` → `clientes.id_cliente`
- (No `customers.id`, eso es solo para hosting)

### 4. **Migrar datos de roel_carrito a roel** (cuando esté listo)

Descomenta y ejecuta los INSERT en `migrate_roel_carrito_to_roel.sql`

### 5. **Cambiar ENVIRONMENT a 'local'** (si no lo has hecho)

Asegúrate de que `catalogo_detalle/config/cart_db.php` tenga:
```php
define('ENVIRONMENT', 'local');
```

---

## 🎯 Eliminación de roel_carrito (Cuando esté completamente migrado)

Una vez que hayas confirmado que TODO funciona correctamente en `roel` con `ENVIRONMENT='local'`:

```bash
# ⚠️ SOLO después de confirmar la migración completa:
mysql -u root -e "DROP DATABASE roel_carrito;"

# Actualizar documentación y confirmar que nadie más usa roel_carrito
```

---

## 📝 Checklist de Verificación

- [ ] Tablas creadas en `roel` (carrito_casts, carrito_cart_items, etc.)
- [ ] ENVIRONMENT = 'local' en `catalogo_detalle/config/cart_db.php`
- [ ] Autenticación funcionando (register + login)
- [ ] Perfil de cliente funcionando (profile + update)
- [ ] Carrito creándose correctamente
- [ ] Órdenes creándose correctamente
- [ ] Datos migrando sin errores de FK
- [ ] Backoffice admins y audit funcionando
- [ ] Todos los archivos usando constantes de tabla

---

## 📞 Soporte

Si encuentras problemas:

1. Verifica que `ENVIRONMENT` es 'local'
2. Comprueba que los constantes de tabla están siendo usados
3. Revisa los logs de MySQL por errores de FK
4. Asegúrate de que `usuarios` y `clientes` existen y están sincronizadas

