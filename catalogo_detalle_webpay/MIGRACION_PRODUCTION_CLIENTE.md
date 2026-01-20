# Migración: Production Requests - customer_id → id_cliente

## Problema
La tabla `carrito_production_requests` estaba usando la columna `customer_id` que hacía referencia a una tabla `customers` que ya no existe. Ahora el sistema usa la tabla `clientes` con el prefijo `carrito_`.

## Solución
Se renombró la columna `customer_id` a `id_cliente` en toda la base de código.

## Archivos modificados

### Código PHP
- `api/production/request_create.php` - CREATE TABLE y INSERT
- `my_production.php` - SELECT WHERE id_cliente
- `production_detail.php` - SELECT WHERE id_cliente
- `backoffice/production_requests.php` - JOIN con clientes y búsquedas
- `backoffice/production_request_detail.php` - JOIN con clientes

### SQL
- `sql/05_production_requests.sql` - Schema actualizado
- `sql/migrate_production_customer_to_cliente.sql` - Script de migración (NUEVO)

## Cómo aplicar la migración

### Si la tabla carrito_production_requests NO existe aún
No necesitas hacer nada. La tabla se creará automáticamente con la columna correcta cuando se cree la primera solicitud de producción.

### Si la tabla carrito_production_requests YA existe con customer_id
Ejecuta el script de migración:

```bash
mysql -u root -p roel < sql/migrate_production_customer_to_cliente.sql
```

O en producción:
```bash
mysql -u roeluser1_usercli -p roeluser1_bdsys < sql/migrate_production_customer_to_cliente.sql
```

El script verifica si existe la columna `customer_id` antes de renombrarla, por lo que es seguro ejecutarlo múltiples veces.

## Verificación
Después de la migración, verifica que:
1. La columna se haya renombrado: `DESCRIBE carrito_production_requests;`
2. El índice se haya actualizado: `SHOW INDEX FROM carrito_production_requests;`
3. El sistema funciona correctamente al crear una nueva solicitud de producción

## Notas
- La migración es **retrocompatible** si se ejecuta antes de crear nuevas solicitudes
- No se pierden datos durante la migración
- El script es idempotente (se puede ejecutar múltiples veces sin problemas)
