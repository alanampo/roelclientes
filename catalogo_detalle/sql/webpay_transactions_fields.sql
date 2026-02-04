-- Agregar campos faltantes a webpay_transactions
-- Ejecutar esto en la base de datos roel_carrito

ALTER TABLE webpay_transactions ADD COLUMN transaction_date VARCHAR(50) NULL COMMENT 'Fecha/hora de transacción Transbank (ISO 8601)' AFTER response_code;
ALTER TABLE webpay_transactions ADD COLUMN payment_type_code VARCHAR(10) NULL COMMENT 'Tipo de pago: VN=Crédito normal, VD=Débito, VC=Crédito cuotas, etc' AFTER transaction_date;
ALTER TABLE webpay_transactions ADD COLUMN installments_number INT DEFAULT 0 COMMENT 'Número de cuotas (0 = sin cuotas)' AFTER payment_type_code;
