-- Migración: Renombrar customer_id a id_cliente en tablas de producción
-- Fecha: 2026-01-11
-- Razón: La tabla customers fue reemplazada por clientes

-- Verificar si la columna customer_id existe antes de renombrar
SET @dbname = DATABASE();
SET @tablename = 'carrito_production_requests';
SET @columnname = 'customer_id';
SET @preparedStatement = (SELECT IF(
  (
    SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE
      TABLE_SCHEMA = @dbname
      AND TABLE_NAME = @tablename
      AND COLUMN_NAME = @columnname
  ) > 0,
  'ALTER TABLE carrito_production_requests CHANGE COLUMN customer_id id_cliente INT NOT NULL',
  'SELECT 1'
));

PREPARE alterIfExists FROM @preparedStatement;
EXECUTE alterIfExists;
DEALLOCATE PREPARE alterIfExists;

-- Renombrar el índice también si existe
SET @preparedStatement2 = (SELECT IF(
  (
    SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS
    WHERE
      TABLE_SCHEMA = @dbname
      AND TABLE_NAME = @tablename
      AND INDEX_NAME = 'idx_customer'
  ) > 0,
  'ALTER TABLE carrito_production_requests DROP INDEX idx_customer, ADD INDEX idx_cliente (id_cliente)',
  'SELECT 1'
));

PREPARE alterIndexIfExists FROM @preparedStatement2;
EXECUTE alterIndexIfExists;
DEALLOCATE PREPARE alterIndexIfExists;

-- Verificar resultado
SELECT 'Migración completada: carrito_production_requests customer_id → id_cliente' AS resultado;
