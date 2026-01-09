# Integración Webpay (Transbank) - Guía de Configuración

## 📋 Descripción General

Se ha implementado un sistema de pagos completo usando Webpay Plus de Transbank para Chile. El flujo permite a los clientes pagar sus compras directamente con tarjeta de crédito/débito.

---

## ⚡ Quick Start (Comienza Aquí)

### Paso 1: Crear archivo .env
```bash
cp .env.example .env
```

El archivo ya tiene las credenciales **oficiales** de integración de Transbank, no necesitas cambiar nada.

### Paso 2: Probar el flujo
1. Abre el catálogo
2. Agrega productos al carrito
3. Vé a checkout
4. Clickea el botón **"Pagar"**
5. Usa esta tarjeta de prueba:
   - **Número:** 4051 8856 0044 6623
   - **CVV:** 123
   - **Fecha:** Cualquiera válida (ej: 01/25)
6. Completa el pago
7. ¡Deberías ver la página de confirmación! ✅

### Paso 3: Verificar en BD
```sql
SELECT * FROM webpay_transactions ORDER BY created_at DESC LIMIT 1;
```

Deberías ver:
- `status`: AUTHORIZED
- `authorized`: 1
- `card_number`: ...6623

---

## 🏗️ Arquitectura

### Componentes Principales

```
catalogo_detalle/
├── api/
│   ├── services/
│   │   └── WebpayService.php          # Servicio encapsulado de Webpay
│   └── payment/
│       ├── webpay_create.php          # Inicia transacción de pago
│       └── webpay_return.php          # Retorno desde Webpay (callback)
├── assets/
│   └── checkout.js                    # Frontend con función makePayment()
├── payment_success.php                # Página de confirmación
├── config/
│   └── app.php                        # Configuración (incluye Webpay)
├── .env                               # Variables de ambiente (crear desde .env.example)
└── .env.example                       # Plantilla de variables
```

### Flujo de Pago

```
1. Usuario click "Pagar" en checkout
   ↓
2. Frontend llama POST /api/payment/webpay_create.php
   ↓
3. Backend calcula total (subtotal + packing + shipping)
   ↓
4. Backend crea transacción en Webpay API
   ↓
5. Backend retorna token + URL de Webpay
   ↓
6. Frontend redirige a Webpay (usuario ingresa tarjeta)
   ↓
7. Webpay redirige a /api/payment/webpay_return.php?token_ws=...
   ↓
8. Backend confirma pago con Webpay
   ↓
9. Si AUTHORIZED: vacía carrito y redirige a payment_success.php
   Si RECHAZADO: redirige a checkout con error
```

## 🔧 Configuración

### 1. Crear archivo .env

Copia `.env.example` a `.env`:

```bash
cp .env.example .env
```

### 2. Configurar ambiente de integración (pruebas)

Las credenciales por defecto en `.env.example` son **oficiales** del ambiente de **integración** de Transbank:

```env
WEBPAY_ENVIRONMENT=integration
WEBPAY_COMMERCE_CODE=597055555532
WEBPAY_API_KEY=579B532A7440BB0C9079DED94D31EA1615BACEB56610332264630D42D0A36B1C
```

#### Tarjetas de Prueba Oficiales de Transbank

| Tipo | Número | CVV | Fecha Expiración | Resultado |
|------|--------|-----|------------------|-----------|
| **VISA** | 4051 8856 0044 6623 | 123 | Cualquiera | ✅ Aprobado |
| **AMEX** | 3700 0000 0002 032 | 1234 | Cualquiera | ✅ Aprobado |
| **MASTERCARD** | 5186 0595 5959 0568 | 123 | Cualquiera | ❌ Rechazado |
| **Prepago VISA** | 4051 8860 0005 6590 | 123 | Cualquiera | ✅ Aprobado |
| **Prepago MASTERCARD** | 5186 1741 1062 9480 | 123 | Cualquiera | ❌ Rechazado |

**Si aparece un formulario de autenticación:**
- RUT: 11.111.111-1
- Clave: 123

**Recomendación:** Usa **VISA 4051 8856 0044 6623** para probar pagos exitosos.

### 3. Cambiar a ambiente de producción

Una vez aprobado por Transbank, actualiza `.env`:

```env
WEBPAY_ENVIRONMENT=production
WEBPAY_COMMERCE_CODE=<tu_codigo_de_comercio>
WEBPAY_API_KEY=<tu_llave_secreta>
```

**Nota:** Los códigos de comercio de producción son diferentes a los de integración.

## 📂 Base de Datos

Se crea automáticamente la tabla `webpay_transactions` con la siguiente estructura:

```sql
CREATE TABLE webpay_transactions (
  id BIGINT PRIMARY KEY AUTO_INCREMENT,
  id_cliente INT NOT NULL,
  token VARCHAR(64) NOT NULL UNIQUE,
  buy_order VARCHAR(26) NOT NULL,
  amount INT NOT NULL,
  status VARCHAR(32) DEFAULT 'INITIATED',
  authorized BOOLEAN DEFAULT FALSE,
  authorization_code VARCHAR(6),
  card_number VARCHAR(19),
  vci VARCHAR(10),
  response_code INT,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  confirmed_at TIMESTAMP NULL,
  KEY idx_cliente (id_cliente),
  KEY idx_token (token),
  KEY idx_buy_order (buy_order)
);
```

## 🔌 WebpayService.php

Clase encapsulada que maneja toda la comunicación con Webpay:

### Métodos disponibles

```php
$webpay = new WebpayService($environment, $commerceCode, $apiKey);

// Crear transacción
$result = $webpay->createTransaction(
  $amount,      // int: monto en CLP
  $buyOrder,    // string: orden única (máx 26 chars)
  $sessionId,   // string: ID de sesión (máx 61 chars)
  $returnUrl    // string: URL de retorno
);
// Retorna: ['ok' => bool, 'token' => string, 'url' => string, 'error' => string]

// Confirmar transacción (después de retorno)
$result = $webpay->commitTransaction($token);
// Retorna: ['ok' => bool, 'authorized' => bool, 'status' => string, 'response_code' => int, ...]

// Obtener estado en cualquier momento
$result = $webpay->getTransactionStatus($token);
```

## 🎯 Endpoints de Pago

### POST /api/payment/webpay_create.php

Inicia un pago. Requiere:
- Usuario autenticado
- CSRF token válido
- Carrito con items

**Request:**
```json
{
  "shipping_cost": 5000
}
```

**Response (éxito):**
```json
{
  "ok": true,
  "token": "e9d555262db0f989e49d724b4db0b0af367cc415cde41f500a776550fc5fddd3",
  "redirect_url": "https://webpay3gint.transbank.cl/webpayserver/initTransaction",
  "buy_order": "RP60c4a1f5e9a1234",
  "amount": 125000
}
```

**Response (error):**
```json
{
  "ok": false,
  "error": "Tu carrito está vacío"
}
```

### GET /api/payment/webpay_return.php?token_ws=...

Callback desde Webpay después del pago (no se llama desde el cliente).

**Acciones:**
- Confirma el pago con Webpay
- Actualiza estado en BD
- Vacía carrito si es exitoso
- Redirige a `payment_success.php` o `checkout.php?payment_error=...`

## 🛒 Flujo en el Checkout

### Antes (sin Webpay)
```
[Botón "Enviar pedido por WhatsApp"]
  → Crea order en la BD
  → Abre WhatsApp
```

### Ahora (con Webpay)
```
[Botón "Pagar"]
  → Inicia transacción con Webpay
  → Redirige a formulario de Webpay
  → Usuario ingresa tarjeta
  → Webpay confirma en el backend
  → Se vacía el carrito
  → Se muestra página de confirmación

[Botón "Enviar pedido por WhatsApp"]
  → Sigue funcionando como antes
  → Para órdenes sin pago en línea
```

## 🔒 Seguridad

- Todas las credenciales van en variables de ambiente (`.env`)
- Las credenciales nunca se commitean en git
- Comunicación TLS 1.2 con Webpay
- Autenticación mediante Tbk-Api-Key-Id y Tbk-Api-Key-Secret en headers
- Validación de tokens en el backend
- CSRF protection en todos los endpoints

## 🧪 Pruebas

### Flujo Completo de Prueba

1. Agregar productos al carrito
2. Clickear "Pagar" en el checkout
3. Ser redirigido a Webpay
4. Ingresar datos de una tarjeta de prueba (ver tabla abajo)
5. Completar la transacción
6. Ser redirigido automáticamente a `payment_success.php`

### Casos de Prueba Oficiales

#### ✅ Pago Exitoso (VISA)
```
Tarjeta: 4051 8856 0044 6623
CVV: 123
Fecha expiración: 01/25 (cualquiera válida)
Resultado esperado: AUTHORIZED, carrito vacío, página de confirmación
```

#### ✅ Pago Exitoso (AMEX)
```
Tarjeta: 3700 0000 0002 032
CVV: 1234
Fecha expiración: 01/25 (cualquiera válida)
Resultado esperado: AUTHORIZED, carrito vacío, página de confirmación
```

#### ❌ Pago Rechazado (MASTERCARD)
```
Tarjeta: 5186 0595 5959 0568
CVV: 123
Fecha expiración: 01/25 (cualquiera válida)
Resultado esperado: FAILED, carrito mantiene items, error en checkout
```

#### 🔐 Si Pide Autenticación
```
RUT: 11.111.111-1
Clave: 123
```

### Validar Correctamente

Después de cada transacción, verifica:

```sql
-- Ver transacción creada
SELECT * FROM webpay_transactions ORDER BY created_at DESC LIMIT 1;

-- Verificar que status sea AUTHORIZED (éxito) o FAILED (rechazo)
-- Verificar que authorized sea 1 (éxito) o 0 (rechazo)
-- Verificar que card_number contenga últimos 4 dígitos
```

## ⚙️ Mantenimiento

### Monitorear transacciones

```sql
-- Ver todas las transacciones
SELECT * FROM webpay_transactions ORDER BY created_at DESC LIMIT 20;

-- Ver solo pagos exitosos
SELECT * FROM webpay_transactions WHERE authorized = 1 ORDER BY confirmed_at DESC;

-- Ver pagos fallidos
SELECT * FROM webpay_transactions WHERE authorized = 0 ORDER BY created_at DESC;
```

### Troubleshooting

**Error: "Extensión cURL no disponible"**
- Verifica que PHP tenga cURL habilitado
- En Linux: `php -m | grep curl`

**Error: "Respuesta vacía de Webpay"**
- Verifica que las credenciales sean correctas
- Verifica que el ambiente (integration/production) sea el correcto
- Verifica que haya conexión a internet

**Pago no confirma automáticamente**
- Revisa los logs del servidor
- Verifica que `webpay_return.php` sea accesible desde internet
- Las credenciales deben coincidir

## 📚 Referencias

- Documentación oficial: https://www.transbankdevelopers.cl/
- SDK PHP oficial: https://github.com/TransbankDevelopers/transbank-sdk-php
- Códigos de error: https://www.transbankdevelopers.cl/documentacion/webpay

## 🔌 Desconectar/Conectar

Para **desactivar** pagos con Webpay y volver a WhatsApp:

1. En `checkout.php`, ocultar el botón "Pagar":
   ```php
   <button id="btnMakeReservation" class="btn btn-success" style="display:none;" type="button">Pagar</button>
   ```

2. El botón "Enviar pedido por WhatsApp" seguirá funcionando

Para **reactivar**:
1. Mostrar el botón "Pagar"
2. Asegurar que `.env` tenga credenciales válidas

## ✅ Checklist de Configuración

- [ ] Crear `.env` desde `.env.example`
- [ ] Verificar que `WEBPAY_ENVIRONMENT=integration` para pruebas
- [ ] Verificar que las credenciales de Webpay sean correctas (ya vienen en `.env.example`)
- [ ] Ejecutar script de verificación: `GET /api/payment/webpay_check.php`
- [ ] Probar con tarjeta Visa 4051 8856 0044 6623 (CVV: 123)
- [ ] Verificar que el carrito se vacíe después del pago exitoso
- [ ] Verificar que la página de confirmación se muestre correctamente
- [ ] Probar rechazo de pago (Mastercard 5186 0595 5959 0568)
- [ ] Cambiar a `WEBPAY_ENVIRONMENT=production` antes de ir a producción
- [ ] Actualizar `WEBPAY_COMMERCE_CODE` y `WEBPAY_API_KEY` con credenciales reales

## 🔍 Script de Verificación

Para verificar que todo está configurado correctamente, abre en el navegador:

```
http://tu-dominio/catalogo_detalle/api/payment/webpay_check.php
```

Deberías ver un JSON indicando:
- ✅ `.env` archivo existe
- ✅ WEBPAY_ENVIRONMENT está configurado
- ✅ WEBPAY_COMMERCE_CODE está configurado
- ✅ WEBPAY_API_KEY está configurado
- ✅ Conexión a Webpay
- ✅ Tabla webpay_transactions
- ✅ Todos los archivos necesarios

Si algo falla, el script te indicará qué revisar.

---

## 📝 Notas Finales

### Encapsulación Completa

Este sistema está completamente encapsulado. Si necesitas:

**Desactivar Webpay temporalmente:**
```html
<!-- En checkout.php, comenta o oculta -->
<button id="btnMakeReservation" class="btn btn-success" style="display:none;" type="button">Pagar</button>
```

**Reactivar:**
- Quita `display:none` del botón
- Verifica que `.env` esté configurado

**Remover completamente:**
- Elimina la carpeta `api/payment/`
- Elimina `api/services/WebpayService.php`
- Elimina `payment_success.php`
- Elimina líneas de Webpay en `config/app.php`
- El botón "Enviar pedido por WhatsApp" seguirá funcionando
