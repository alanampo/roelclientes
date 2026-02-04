-- catalogo_detalle/sql/add_payment_fields_to_reservas.sql
-- Agrega campos de pago y envío a la tabla reservas

USE roel;

ALTER TABLE reservas
  ADD COLUMN IF NOT EXISTS subtotal_clp INT DEFAULT 0 COMMENT 'Subtotal de productos sin envío ni packing',
  ADD COLUMN IF NOT EXISTS packing_cost_clp INT DEFAULT 0 COMMENT 'Costo de empaque/packing',
  ADD COLUMN IF NOT EXISTS shipping_cost_clp INT DEFAULT 0 COMMENT 'Costo de envío',
  ADD COLUMN IF NOT EXISTS total_clp INT DEFAULT 0 COMMENT 'Total de la compra (subtotal + packing + shipping)',
  ADD COLUMN IF NOT EXISTS paid_clp INT DEFAULT 0 COMMENT 'Monto pagado',
  ADD COLUMN IF NOT EXISTS payment_status VARCHAR(20) DEFAULT 'pending' COMMENT 'Estado del pago: pending, paid, failed, refunded',
  ADD COLUMN IF NOT EXISTS payment_method VARCHAR(50) DEFAULT NULL COMMENT 'Método de pago: webpay, manual, etc',
  ADD COLUMN IF NOT EXISTS webpay_transaction_id INT DEFAULT NULL COMMENT 'ID de transacción de Webpay',
  ADD COLUMN IF NOT EXISTS shipping_method VARCHAR(20) DEFAULT NULL COMMENT 'Método de envío: domicilio, agencia, vivero',
  ADD COLUMN IF NOT EXISTS shipping_address TEXT DEFAULT NULL COMMENT 'Dirección de envío',
  ADD COLUMN IF NOT EXISTS shipping_commune VARCHAR(100) DEFAULT NULL COMMENT 'Comuna de envío',
  ADD COLUMN IF NOT EXISTS shipping_agency_code_dls INT DEFAULT NULL COMMENT 'Código de agencia Starken (code_dls)',
  ADD COLUMN IF NOT EXISTS shipping_agency_name VARCHAR(255) DEFAULT NULL COMMENT 'Nombre de la agencia Starken',
  ADD COLUMN IF NOT EXISTS shipping_agency_address TEXT DEFAULT NULL COMMENT 'Dirección de la agencia',
  ADD COLUMN IF NOT EXISTS cart_id INT DEFAULT NULL COMMENT 'ID del carrito que originó esta reserva (trazabilidad)',
  ADD COLUMN IF NOT EXISTS created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP COMMENT 'Fecha de creación',
  ADD COLUMN IF NOT EXISTS updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT 'Fecha de última actualización';

-- Agregar índices para mejorar búsquedas
CREATE INDEX IF NOT EXISTS idx_reservas_payment_status ON reservas(payment_status);
CREATE INDEX IF NOT EXISTS idx_reservas_webpay_transaction ON reservas(webpay_transaction_id);
CREATE INDEX IF NOT EXISTS idx_reservas_cart_id ON reservas(cart_id);
CREATE INDEX IF NOT EXISTS idx_reservas_created_at ON reservas(created_at);
