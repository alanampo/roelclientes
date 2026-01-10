-- catalogo_detalle/sql/add_reserva_to_webpay_transactions.sql
-- Agrega referencia a reservas en webpay_transactions

USE roel;

ALTER TABLE webpay_transactions
  ADD COLUMN id_reserva INT DEFAULT NULL COMMENT 'ID de la reserva asociada',
  ADD INDEX idx_webpay_id_reserva(id_reserva);
