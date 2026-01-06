# Sistema de Rutas Dinámicas - Documentación

## ✅ Implementado: Rutas Automáticas y Flexibles

El proyecto ahora detecta automáticamente su ubicación y construye rutas correctas sin necesidad de hardcodear directorios.

---

## Problema Original

El proyecto estaba montado en `/catalogo_detalle/` durante desarrollo, pero con rutas hardcodeadas que asumían ubicaciones específicas:

```javascript
// ❌ ANTES: Rutas hardcodeadas
fetch('api/auth/register.php')  // → /api/auth/register.php (INCORRECTO en /catalogo_detalle/)
fetch('api/cart/get.php')       // → /api/cart/get.php (INCORRECTO)
```

Esto generaba errores **404 Not Found** porque las rutas no consideraban el directorio actual.

---

## Solución: Rutas Dinámicas

### 1. Archivo de Configuración: `config/routes.php`

```php
<?php
// Detecta dinámicamente la ruta base del proyecto
$scriptPath = dirname($_SERVER['SCRIPT_NAME']);  // Ej: /catalogo_detalle
$baseUrl = rtrim($scriptPath, '/') . '/';         // Ej: /catalogo_detalle/
$apiBaseUrl = $baseUrl . 'api/';                  // Ej: /catalogo_detalle/api/
?>

<script>
// Variables globales en JavaScript
window.APP_BASE_URL = '<?php echo $baseUrl; ?>';
window.API_BASE_URL = '<?php echo $apiBaseUrl; ?>';

// Funciones helper
function buildUrl(path) {
    return window.APP_BASE_URL + path.replace(/^\//, '');
}

function buildApiUrl(path) {
    return window.API_BASE_URL + path.replace(/^\//, '');
}
</script>
```

### 2. Incluir en la Página Principal

**En `index.php`**, antes de cargar `app.js`:

```php
<!-- Configuración de rutas dinámicas (DEBE SER ANTES DE app.js) -->
<?php include __DIR__ . '/config/routes.php'; ?>

<script src="assets/app.js"></script>
```

### 3. Usar en JavaScript

**ANTES:**
```javascript
const response = await fetch('api/auth/register.php', {...});
const cart = await fetch('api/cart/get.php', {...});
```

**DESPUÉS:**
```javascript
const response = await fetch(buildApiUrl('auth/register.php'), {...});
const cart = await fetch(buildApiUrl('cart/get.php'), {...});
```

---

## Cómo Funciona

### Detección Automática

La ruta base se detecta en el servidor usando `$_SERVER['SCRIPT_NAME']`:

| Entorno | SCRIPT_NAME | APP_BASE_URL | API_BASE_URL |
|---------|-------------|--------------|--------------|
| Desarrollo (localhost/catalogo_detalle/) | `/catalogo_detalle/index.php` | `/catalogo_detalle/` | `/catalogo_detalle/api/` |
| Producción (roelplant.cl/) | `/index.php` | `/` | `/api/` |
| Subdominio (api.roelplant.cl/) | `/index.php` | `/` | `/api/` |

### Resolución de URLs

Cuando el navegador está en `/catalogo_detalle/index.php` y hace un AJAX call:

```javascript
// En página: /catalogo_detalle/index.php
buildApiUrl('auth/register.php')

// ↓ Se resuelve a:
// /catalogo_detalle/ + api/ + auth/register.php
// = /catalogo_detalle/api/auth/register.php ✓
```

Cuando estés en producción en `/index.php`:

```javascript
// En página: /index.php
buildApiUrl('auth/register.php')

// ↓ Se resuelve a:
// / + api/ + auth/register.php
// = /api/auth/register.php ✓
```

---

## Archivos Actualizados

### Configuración
- ✅ `config/routes.php` - NUEVO: Genera variables dinámicas

### Vistas
- ✅ `index.php` - Incluye config/routes.php

### Scripts JavaScript
- ✅ `assets/app.js` - Usa `buildApiUrl()` para todas las rutas
- ✅ `assets/production_cart.js` - Usa `buildApiUrl()` para rutas API
- ✅ `assets/production.js` - Usa `buildApiUrl()` para rutas API

### Rutas Actualizadas

#### En `app.js`
```javascript
// Auth
buildApiUrl('auth/register.php')   // /catalogo_detalle/api/auth/register.php
buildApiUrl('auth/login.php')      // /catalogo_detalle/api/auth/login.php
buildApiUrl('auth/logout.php')     // /catalogo_detalle/api/auth/logout.php

// Cart
buildApiUrl('cart/get.php')        // /catalogo_detalle/api/cart/get.php
buildApiUrl('cart/add.php')        // /catalogo_detalle/api/cart/add.php
buildApiUrl('cart/update.php')     // /catalogo_detalle/api/cart/update.php
buildApiUrl('cart/remove.php')     // /catalogo_detalle/api/cart/remove.php
```

#### En `production_cart.js`
```javascript
buildApiUrl('me.php')                      // /catalogo_detalle/api/me.php
buildApiUrl('production/list.php')         // /catalogo_detalle/api/production/list.php
buildApiUrl('production/request_create.php')  // /catalogo_detalle/api/production/request_create.php
```

#### En `production.js`
```javascript
buildApiUrl('me.php')              // /catalogo_detalle/api/me.php
buildApiUrl('production/list.php') // /catalogo_detalle/api/production/list.php
```

---

## Testing

### Verificar que las rutas se generaron correctamente

Abre el navegador y ejecuta en la consola (F12):

```javascript
// Deberías ver algo como esto:
console.log(window.APP_BASE_URL);    // "/catalogo_detalle/"
console.log(window.API_BASE_URL);    // "/catalogo_detalle/api/"
console.log(buildApiUrl('auth/register.php'));
// "/catalogo_detalle/api/auth/register.php"
```

### Test en Desarrollo

1. Navega a `http://localhost:8888/catalogo_detalle/`
2. Abre DevTools (F12)
3. Verifica que `APP_BASE_URL` contiene `/catalogo_detalle/`
4. Intenta registrarte o hacer login
5. Verifica en Network que los requests van a `/catalogo_detalle/api/...`

### Test en Producción

1. Cuando despliegues a `roelplant.cl/`
2. Las rutas se ajustarán automáticamente a `/`
3. Sin cambios de código necesarios

---

## Ventajas

✅ **Flexible**: Funciona en cualquier ubicación
✅ **Automático**: No requiere configuración manual
✅ **Escalable**: Funciona en subdominios, rutas complejas, etc.
✅ **Seguro**: Generado en el servidor, no hardcodeado
✅ **Mantenible**: Un solo lugar de configuración

---

## Debugging

### Problema: Rutas aún dan 404

**Solución:**
1. Verifica que `config/routes.php` está siendo incluido en `index.php`
2. Abre DevTools y confirma que `window.API_BASE_URL` está definido
3. Verifica que `buildApiUrl()` function existe
4. Comprueba en Network → XHR que la URL es correcta

### Problema: Variables no definidas

**Solución:**
1. Asegúrate de que `index.php` tiene:
   ```php
   <?php include __DIR__ . '/config/routes.php'; ?>
   ```
2. Debe estar ANTES de cualquier `<script>` que use `buildApiUrl()`
3. Debe estar ANTES de cargar `app.js`

### Problema: Funciona en desarrollo pero no en producción

**Posibles causas:**
1. Código hardcodeado en otro lugar (buscar `/api/` y `/catalogo_detalle/`)
2. URLs en archivos PHP que no usan variables dinámicas
3. Reescritura de URLs en `.htaccess`

**Solución:**
```bash
grep -r "catalogo_detalle" /catalogo_detalle/ --include="*.js" --include="*.php"
```

---

## Próximos Pasos Opcionales

### 1. Agregar a Otros Archivos

Si hay otros archivos HTML o PHP que hacen AJAX calls:

1. Incluir `config/routes.php` en el `<head>`
2. Usar `buildApiUrl()` en lugar de rutas hardcodeadas
3. O usar rutas relativas (`./api/...`) que funcionan automáticamente

### 2. Crear Configuración PHP Helper

**Opcional:** Crear una función PHP helper:

```php
// En config/routes.php
<?php
function api_url($path) {
    global $apiBaseUrl;
    return $apiBaseUrl . ltrim($path, '/');
}
?>
```

Uso en PHP:
```php
<a href="<?php echo api_url('auth/logout.php'); ?>">Logout</a>
```

### 3. Logging y Debugging

Para debugging en desarrollo, habilita logging:

```php
// En config/routes.php
if (getenv('DEBUG_ROUTES') === '1') {
    error_log('APP_BASE_URL: ' . $baseUrl);
    error_log('API_BASE_URL: ' . $apiBaseUrl);
}
```

---

## Resumen

El sistema ahora detecta automáticamente su ubicación y construye rutas correctas.

**No necesitas cambiar nada cuando despliegues el proyecto a un nuevo directorio.**

Las rutas se adaptan automáticamente:
- Local: `/catalogo_detalle/` → `/catalogo_detalle/api/`
- Producción: `/` → `/api/`
- Futuro: Cualquier ubicación → Funciona automáticamente

