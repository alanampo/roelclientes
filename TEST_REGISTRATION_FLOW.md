# Test Plan: Registration System Data Mapping

## Overview
This document provides step-by-step testing procedures to verify the registration system correctly maps form data to the roel.clientes database structure.

---

## Pre-Test Setup

### 1. Sample Data Verification

**Communes exist in database:**
```bash
mysql -u root roel -e "SELECT COUNT(*) as total FROM comunas;"
# Expected: 344 communes
```

**Region consistency:**
```bash
mysql -u root roel -e "
SELECT ciudad as region, COUNT(*) as comuna_count
FROM comunas
GROUP BY ciudad
ORDER BY region
LIMIT 5;"
```

Expected output:
```
region              comuna_count
ARICA               4
ANTOFAGASTA         3
ATACAMA             5
COQUIMBO            4
...
```

### 2. Initial Database State

Before testing, verify no test data exists:
```bash
mysql -u root roel -e "
SELECT COUNT(*) as existing_clientes FROM clientes;
SELECT COUNT(*) as existing_usuarios FROM usuarios WHERE tipo_usuario = 0;
"
```

---

## Test Cases

### Test Case 1: Basic Registration Flow

**Objective:** Verify successful registration with all fields mapped correctly

**Test Data:**
```
Email: testuser@example.com
Password: TestPassword123
Nombre: Juan García López
RUT: 12345678-9 (needs valid Chilean RUT check digit)
Teléfono: +56987654321
Domicilio: Calle Principal 123, Apt 4
Domicilio2: (leave empty)
Comuna: VALPARAÍSO (id: 45)
Ciudad: Valparaíso
Región: Valparaíso (auto-populated)
Provincia: Valparaíso
```

**Steps:**
1. Navigate to `/registro.php`
2. Fill in email: testuser@example.com
3. Fill password and confirmation
4. Fill nombre: Juan García López
5. Fill RUT: 12345678-9 (wait for auto-format)
6. Fill teléfono: +56987654321
7. Fill domicilio: Calle Principal 123, Apt 4
8. Leave domicilio2 empty
9. Select Comuna: VALPARAÍSO
10. Verify Región auto-fills with "Valparaíso"
11. Verify Provincia dropdown shows only Valparaíso region provinces
12. Select Provincia: Valparaíso
13. Fill ciudad: Valparaíso
14. Click Registrarse

**Expected Results:**
- No validation errors during form entry
- RUT auto-formats correctly
- Region auto-populates when commune selected
- Province filter works correctly
- Success message: "Registro exitoso. Será redirigido al inicio de sesión."
- Redirect to index.php

**Database Verification:**
```bash
mysql -u root roel << 'EOF'
-- Check clientes record
SELECT id_cliente, nombre, rut, mail, comuna, region, provincia, ciudad
FROM clientes
WHERE rut = '12345678-9';

-- Check usuarios record
SELECT id, nombre, nombre_real, tipo_usuario, id_cliente
FROM usuarios
WHERE nombre = 'testuser@example.com';

-- Verify Foreign Key
SELECT c.id_cliente, c.nombre, c.comuna, co.nombre as comuna_nombre
FROM clientes c
JOIN comunas co ON co.id = c.comuna
WHERE c.id_cliente = (SELECT id_cliente FROM clientes WHERE rut = '12345678-9');
EOF
```

**Expected Database Output:**
```
clientes record:
id_cliente: (auto-increment, e.g., 1001)
nombre: Juan García López
rut: 12345678-9
mail: testuser@example.com
comuna: 45
region: Valparaíso
provincia: Valparaíso
ciudad: Valparaíso

usuarios record:
id: (auto-increment)
nombre: testuser@example.com
nombre_real: Juan García López
tipo_usuario: 0
id_cliente: 1001 (matches clientes.id_cliente)

FK verification:
id_cliente: 1001
nombre: Juan García López
comuna: 45
comuna_nombre: VALPARAÍSO
```

---

### Test Case 2: Region Mismatch Validation

**Objective:** Verify backend rejects mismatched region-commune data

**Approach:**
- Intercept the AJAX request using browser DevTools
- Manually modify the region value before submission
- Verify backend validation rejects it

**Steps:**
1. Open browser Developer Tools (F12)
2. Go to Network tab
3. Navigate to `/registro.php`
4. Select Comuna: VALPARAÍSO (region should auto-populate to "Valparaíso")
5. Manually open DevTools Console and execute:
   ```javascript
   $('#Region').prop('disabled', false);
   $('#Region').val('Atacama');
   ```
6. Complete registration form
7. Submit

**Expected Result:**
- Error message: "La región seleccionada no coincide con la comuna"
- No record created in database
- Form remains visible for correction

---

### Test Case 3: Different Region Test

**Objective:** Verify registration works with different regions

**Test Data Variations:**

Option A - Metropolitan Region:
```
Comuna: SANTIAGO (region should auto-populate to "Metropolitana de Santiago")
Expected region: Metropolitana de Santiago
```

Option B - Southern Region:
```
Comuna: PUERTO MONTT (region should auto-populate to "Los Lagos")
Expected region: Los Lagos
```

**Verification:**
```bash
-- For Metropolitan
SELECT id, nombre, ciudad FROM comunas WHERE nombre = 'SANTIAGO';

-- For Southern
SELECT id, nombre, ciudad FROM comunas WHERE nombre = 'PUERTO MONTT';
```

---

### Test Case 4: Email Duplicate Detection

**Objective:** Verify email uniqueness validation

**Steps:**
1. Register first user with email: duplicatetest@example.com
2. Complete registration successfully
3. Navigate back to registro.php
4. Try to register second user with same email
5. When email field loses focus, should show error

**Expected Result:**
- Error message: "Este email ya está registrado"
- Email field marked as invalid (red border)
- Submit button should be disabled or show validation message

---

### Test Case 5: RUT Duplicate Detection

**Objective:** Verify RUT uniqueness validation

**Steps:**
1. Register first user with RUT: 15.999.999-8
2. Complete registration successfully
3. Navigate back to registro.php
4. Try to register second user with same RUT
5. When RUT field loses focus, should show error

**Expected Result:**
- Error message: "Este RUT ya está registrado"
- RUT field marked as invalid

---

### Test Case 6: RUT Validation Check Digit

**Objective:** Verify Chilean RUT check digit calculation

**Test Data:**
```
Valid RUT:    18.235.321-K  (check digit: K)
Invalid RUT:  18.235.321-1  (wrong check digit)
```

**Steps:**
1. Try registering with invalid RUT (wrong check digit)
2. When RUT field loses focus, should show error

**Expected Result:**
- Error message: "Formato de RUT inválido"
- RUT field marked as invalid

**Valid RUT Sources:** (for testing)
- 8.372.277-3 (fictional but valid format)
- 6.500.000-0 (fictional but valid format)
- 9.999.999-3 (fictional but valid format)

---

### Test Case 7: Required Fields Validation

**Objective:** Verify all required fields are enforced

**Required Fields:**
- Email (*)
- Contraseña (*)
- Repetir Contraseña (*)
- Nombre / Razón Social (*)
- RUT (*)
- Teléfono (*)
- Domicilio (*)
- Comuna (*)
- Ciudad (*)

**Optional Fields:**
- Domicilio2
- Región (read-only, auto-populated)
- Provincia

**Steps:**
1. Load registro.php
2. Try to submit form without filling any fields
3. Verify each required field is highlighted

**Expected Result:**
- Alert: "Por favor, complete todos los campos requeridos"
- All required fields with empty values are marked invalid

---

### Test Case 8: Field Length Limits

**Objective:** Verify form field length constraints match database

**Test Lengths:**
```
Email: 100 chars (exceeded → trimmed by browser)
Nombre: 60 chars
RUT: 12 chars (with format)
Teléfono: 20 chars
Domicilio: 100 chars
Domicilio2: 255 chars
Ciudad: 40 chars
Provincia: 100 chars
Región: 100 chars
```

**Steps:**
1. Try entering maximum length data in each field
2. Verify form accepts without truncation on frontend
3. Submit and verify database stores correctly

---

### Test Case 9: Password Requirements

**Objective:** Verify password handling

**Steps:**
1. Enter password: TestPass123
2. Enter different confirmation: TestPass456
3. Click outside password confirmation field
4. Verify error shows

**Expected Result:**
- Error message: "Las contraseñas no coinciden"
5. Match both passwords and verify error disappears
6. Register successfully
7. Log in with registered credentials

---

### Test Case 10: Complete User Journey

**Objective:** Verify end-to-end registration and login

**Steps:**
1. Register new user: newuser@example.com / Password123
2. Complete all fields correctly
3. Submit registration
4. Receive success message
5. Redirect to index.php
6. Should show login form
7. Log in with credentials: newuser@example.com / Password123
8. Verify session is established
9. Verify user data is loaded correctly

---

## Automated Test Script

Use this MySQL query to verify all aspects of a registration:

```sql
-- After registering test user, run this to verify complete mapping

SET @test_email = 'testuser@example.com';
SET @test_rut = '12345678-9';

SELECT
    'CLIENTES TABLE' as verification_type,
    c.id_cliente,
    c.nombre,
    c.rut,
    c.mail,
    c.telefono,
    c.domicilio,
    c.domicilio2,
    c.comuna,
    c.ciudad,
    c.region,
    c.provincia,
    c.activo
FROM clientes c
WHERE c.mail = @test_email OR c.rut = @test_rut

UNION ALL

SELECT
    'USUARIOS TABLE' as verification_type,
    u.id,
    u.nombre,
    u.nombre_real,
    u.tipo_usuario,
    u.id_cliente,
    NULL, NULL, NULL, NULL, NULL, NULL, NULL
FROM usuarios u
WHERE u.nombre = @test_email

UNION ALL

SELECT
    'FK VALIDATION' as verification_type,
    co.id,
    co.nombre,
    co.ciudad,
    NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL
FROM comunas co
WHERE co.id = (SELECT c.comuna FROM clientes c WHERE c.mail = @test_email LIMIT 1);
```

---

## Regression Testing

After any code changes, run:

1. **Frontend Changes:**
   - Test region auto-population
   - Test province filtering
   - Test field validation
   - Test form submission

2. **Backend Changes:**
   - Test region consistency validation
   - Test database inserts
   - Test transaction rollback on error
   - Test duplicate email/RUT detection

3. **Database Changes:**
   - Verify communes data integrity
   - Verify foreign key constraints
   - Test backup/restore procedures

---

## Known Limitations & TODOs

- [ ] Password complexity requirements not enforced (min length, chars required)
- [ ] Email confirmation flow not implemented
- [ ] Phone number format validation not implemented
- [ ] Address validation against postal codes not implemented
- [ ] Province normalization (no FK, stored as text)
- [ ] Account activation workflow not implemented
- [ ] Rate limiting on duplicate registrations not implemented

---

## Troubleshooting

### Issue: "La región seleccionada no coincide con la comuna"

**Cause:** Frontend sent region that doesn't match commune's region in DB

**Solution:**
1. Check that comunas.php returns correct region in 'region' field
2. Check that JS regex extraction works: `\(([^)]+)\)$`
3. Check that commune exists in database
4. Check database values: `SELECT id, nombre, ciudad FROM comunas WHERE id = ?`

### Issue: Region field not auto-populating

**Cause:** JavaScript error or event not firing

**Solution:**
1. Open browser console (F12)
2. Check for JavaScript errors
3. Verify selectpicker library loaded
4. Check that selectpicker refresh() is called
5. Manually test: `$('#Region').val('Valparaíso'); $('#Region').selectpicker('refresh');`

### Issue: Province dropdown not showing filtered options

**Cause:** Filtering logic not working

**Solution:**
1. Check optgroup labels match region names exactly
2. Verify data attributes: `data-region="Valparaíso"`
3. Check that region change event fires
4. Verify selectpicker refresh() is called after filtering

### Issue: Data not saving to database

**Cause:** Transaction rollback or insert error

**Solution:**
1. Check MySQL error log
2. Verify foreign key constraints
3. Run: `SHOW ENGINE INNODB STATUS;`
4. Check procesa_registro.php error response
5. Enable error reporting to see actual error

---

## Performance Notes

- Communes loaded once on page load (344 records)
- No pagination needed (fits in memory)
- Region validation query is indexed on comunas.id
- Transaction ensures data consistency

---

## Security Considerations

✅ **Implemented:**
- SQL injection prevention (prepared statements)
- CSRF tokens (if implemented on login)
- Email normalization and validation
- RUT check digit validation
- Password hashing with PASSWORD_DEFAULT

⚠️ **Should be added:**
- Rate limiting on registration attempts
- Email verification before account activation
- Password complexity requirements
- CSRF token on registration form
- Account lockout after failed attempts
- Honeypot field to detect bots

