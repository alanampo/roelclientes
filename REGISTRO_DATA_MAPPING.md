# Data Mapping Validation: Registration System → Database

## Status: ✅ Implemented & Validated

This document explains how the registration form data maps to the `roel.clientes` database structure.

---

## Form Fields → Database Fields Mapping

### 1. **Email → mail + usuarios.nombre**
- **Form:** Email input
- **Database:**
  - `clientes.mail` (VARCHAR 100)
  - `usuarios.nombre` (VARCHAR - stores normalized email)
- **Validation:** `filter_var($email, FILTER_VALIDATE_EMAIL)`
- **Uniqueness:** Checked against `usuarios.nombre`

### 2. **Nombre → clientes.nombre + razon_social**
- **Form:** "Nombre / Razón Social" text input
- **Database:**
  - `clientes.nombre` (VARCHAR 60) - Client name
  - `clientes.razon_social` (VARCHAR 120) - Uses same value as nombre
  - `usuarios.nombre_real` (VARCHAR - stores real name)
- **Validation:** Required, max 100 chars

### 3. **RUT → clientes.rut**
- **Form:** RUT input with auto-formatting (12.345.678-9)
- **Database:** `clientes.rut` (VARCHAR 25)
- **Validation:**
  - Format validation: Chilean RUT check digit validation (modulo 11)
  - Uniqueness: Checked against `clientes.rut`
  - Implemented in both frontend (dist/js/registro.js) and backend (procesa_registro.php)

### 4. **Teléfono → clientes.telefono**
- **Form:** Telephone input
- **Database:** `clientes.telefono` (VARCHAR 45)
- **Validation:** Required, max 20 chars

### 5. **Domicilio → clientes.domicilio**
- **Form:** Primary address input
- **Database:** `clientes.domicilio` (VARCHAR 100)
- **Validation:** Required, max 200 chars in form but 100 in DB
- **Note:** Database limit is VARCHAR 100, form allows up to 200

### 6. **Domicilio2 → clientes.domicilio2**
- **Form:** Secondary/Delivery address input (optional)
- **Database:** `clientes.domicilio2` (VARCHAR 255)
- **Validation:** Optional, max 200 chars

### 7. **Comuna → clientes.comuna**
- **Form:** Bootstrap selectpicker with livesearch
  - Loads from `server/comunas.php`
  - Shows format: "QUILPUÉ (Valparaíso)"
  - Value sent: `comunas.id` (INTEGER)
- **Database:** `clientes.comuna` (INT 11) - **FK to comunas.id**
- **Validation:**
  - Must exist in comunas table
  - Region must match the commune's region (see below)
- **Critical:** This is a **Foreign Key** linking to the comunas table

### 8. **Región → clientes.region** (AUTO-POPULATED)
- **Form:** Bootstrap selectpicker (disabled/read-only)
  - Initially disabled with placeholder "Se completará automáticamente"
  - Auto-populated when user selects a comuna
  - Extracted from `comunas.ciudad` field
- **Database:** `clientes.region` (VARCHAR 100)
- **Auto-population Logic:**
  - Frontend extracts region name from selectpicker option text
  - Example: "QUILPUÉ (Valparaíso)" → extract "Valparaíso"
  - Sets Region field to this value
  - Triggers province filter
- **Validation (Backend):**
  - **NEW:** Region value is validated against selected commune's region
  - Query: `SELECT ciudad FROM comunas WHERE id = ?`
  - Ensures consistency: if user somehow tampers with data, mismatch is detected
  - Error: "La región seleccionada no coincide con la comuna"

### 9. **Provincia → clientes.provincia**
- **Form:** Bootstrap selectpicker filtered by region
  - All 56 Chilean provinces organized by region
  - Only shows provinces matching the selected region
  - Provinces reset when region changes
- **Database:** `clientes.provincia` (VARCHAR 100)
- **Validation:** Optional (no required attribute), max 100 chars
- **Note:** Database doesn't have province normalization, stores as text

### 10. **Ciudad → clientes.ciudad**
- **Form:** City/town text input
- **Database:** `clientes.ciudad` (VARCHAR 40)
- **Validation:** Required, max 100 chars in form (but 100 in DB is enough)
- **Usage:** Stores city/town name, NOT region

### 11. **Password → usuarios.password**
- **Form:** Password input with confirmation
- **Database:** `usuarios.password` (VARCHAR 255) - hashed with `PASSWORD_DEFAULT`
- **Hashing:** `password_hash($password, PASSWORD_DEFAULT)`
- **Validation:**
  - Confirmed match with second password field
  - No specific complexity requirements (should be enhanced)

---

## Database Relationships

### Foreign Key: clientes.comuna → comunas.id

```sql
-- clientes table structure for commune relationship
ALTER TABLE clientes ADD CONSTRAINT fk_clientes_comuna
  FOREIGN KEY (comuna) REFERENCES comunas(id);

-- comunas table provides:
-- - id: Unique commune identifier
-- - nombre: Commune name (e.g., "Quilpué")
-- - ciudad: Region name (e.g., "Valparaíso")
```

### Example Data Flow

```
User Selects:
  Comuna: "Quilpué" → comunas.id = 45

Backend Processing:
  1. Query: SELECT ciudad FROM comunas WHERE id = 45
  2. Result: ciudad = "Valparaíso"
  3. Validate: user_region ("Valparaíso") == db_region ("Valparaíso") ✓

Database Storage:
  clientes.comuna = 45 (FK)
  clientes.region = "Valparaíso"
  clientes.provincia = "Valparaíso" (user selected)
  clientes.ciudad = "Quillota" (user entered)
```

---

## Validation Flow

### Frontend Validation (dist/js/registro.js)

1. **Email validation:**
   - AJAX check for duplicate in real-time
   - Filter_var EMAIL validation

2. **RUT validation:**
   - Format validation: Chilean RUT check digit calculation
   - AJAX check for duplicate
   - Auto-formatting: "12345678-9" → "12.345.678-9"

3. **Password validation:**
   - Match confirmation field
   - Display error if they don't match

4. **Form submission:**
   - Temporarily enable Region field to send value
   - Collect all form data
   - Send to procesa_registro.php

### Backend Validation (procesa_registro.php)

1. **Required fields check:**
   - email, password, nombre, rut, telefono, domicilio, comuna, ciudad

2. **Email validation:**
   - filter_var EMAIL format
   - Uniqueness check against usuarios.nombre

3. **RUT validation:**
   - Format validation (Chilean RUT check digit)
   - Uniqueness check against clientes.rut

4. **Region consistency validation:** (NEW)
   - Query: `SELECT ciudad FROM comunas WHERE id = ?`
   - Compare: user_region == database_region
   - Prevent data inconsistency

5. **Database transaction:**
   - Insert into clientes (11 fields)
   - Insert into usuarios (4 fields)
   - Atomic: both succeed or both rollback

---

## Data Integrity Safeguards

### 1. Foreign Key on commune
- `clientes.comuna` cannot be NULL or invalid
- Must reference valid `comunas.id`

### 2. Region consistency validation
- Backend checks selected region matches commune's region
- Prevents mismatched data entry (intentional or via tampering)

### 3. Duplicate prevention
- Email uniqueness on usuarios.nombre
- RUT uniqueness on clientes.rut

### 4. Transaction atomicity
- Both clientes and usuarios inserts succeed or both rollback
- No orphaned records

---

## Field Type Verification

| Field | Form Type | DB Type | DB Size | Notes |
|-------|-----------|---------|---------|-------|
| email | text | VARCHAR | 100 | FK to usuarios.nombre |
| password | password | VARCHAR | 255 | hashed with PASSWORD_DEFAULT |
| nombre | text | VARCHAR | 60 | + razon_social uses same value |
| rut | text | VARCHAR | 25 | Chilean format |
| telefono | text | VARCHAR | 45 | |
| domicilio | text | VARCHAR | 100 | Form allows 200, but DB is 100 |
| domicilio2 | text | VARCHAR | 255 | Optional |
| **comuna** | **selectpicker** | **INT** | **11** | **FK to comunas.id** |
| ciudad | text | VARCHAR | 40 | Manual entry |
| **region** | **selectpicker** | **VARCHAR** | **100** | **Auto-populated** |
| provincia | selectpicker | VARCHAR | 100 | Optional |

---

## Testing Checklist

- [x] Communes load from server/comunas.php with correct structure
- [x] Frontend regex extraction of region from option text
- [x] Region field auto-populates when commune selected
- [x] Province list filters by selected region
- [x] Backend validates region matches commune region
- [x] Database constraints prevent orphaned communes
- [x] RUT validation (check digit calculation)
- [x] Email duplicate detection
- [x] Transaction atomicity (users + clientes)

---

## Future Improvements

1. **Password complexity:** Add minimum length/complexity requirements
2. **Province normalization:** Create provinces table for FK relationship
3. **Address validation:** Validate against postal code / real addresses
4. **Phone format:** Validate Chilean phone number format
5. **Email confirmation:** Require email verification before activation

---

## Related Files

- **Form:** `/catalogo_detalle/registro.php` - Registration page HTML
- **Frontend Logic:** `/dist/js/registro.js` - Client-side validation and population
- **Backend Logic:** `/procesa_registro.php` - Server-side validation and insertion
- **Data Source:** `/server/comunas.php` - Commune data endpoint
- **Database:** `roel.clientes`, `roel.usuarios`, `roel.comunas`

