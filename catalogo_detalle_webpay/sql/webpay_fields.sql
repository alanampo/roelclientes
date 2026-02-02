-- Agregar campos de Webpay a tabla reservas
-- Ejecutar esto en la base de datos roel

ALTER TABLE reservas ADD COLUMN webpay_transaction_date DATETIME NULL COMMENT 'Fecha de transacción Webpay' AFTER webpay_transaction_id;
ALTER TABLE reservas ADD COLUMN webpay_card_type VARCHAR(50) NULL COMMENT 'Tipo de tarjeta: Débito, Crédito (inferido de payment_type_code)' AFTER webpay_transaction_date;
ALTER TABLE reservas ADD COLUMN webpay_installment_count INT NULL COMMENT 'Cantidad de cuotas' AFTER webpay_card_type;
ALTER TABLE reservas ADD COLUMN webpay_card_last_digits VARCHAR(10) NULL COMMENT 'Últimos dígitos de la tarjeta' AFTER webpay_installment_count;
ALTER TABLE reservas ADD COLUMN webpay_amount INT NULL COMMENT 'Total cobrado en pesos' AFTER webpay_card_last_digits;
ALTER TABLE reservas ADD COLUMN webpay_authorization_code VARCHAR(50) NULL COMMENT 'Código de autorización del banco' AFTER webpay_amount;
ALTER TABLE reservas ADD COLUMN webpay_bank_response VARCHAR(255) NULL COMMENT 'Respuesta del banco (status: AUTHORIZED, REVERSED, etc)' AFTER webpay_authorization_code;
ALTER TABLE reservas ADD COLUMN webpay_order_number VARCHAR(100) NULL COMMENT 'Orden de compra / buy_order' AFTER webpay_bank_response;
ALTER TABLE reservas ADD COLUMN webpay_response_code INT NULL COMMENT 'Código de resultado (0 = éxito)' AFTER webpay_order_number;
ALTER TABLE reservas ADD COLUMN webpay_token VARCHAR(255) NULL COMMENT 'Token de transacción Webpay (CRÍTICO para auditoría)' AFTER webpay_response_code;
