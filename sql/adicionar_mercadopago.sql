-- ============================================================
-- MIGRAÇÃO: Campos de pagamento Mercado Pago
-- Execute uma única vez no banco farmavida
-- ============================================================
USE farmavida;

ALTER TABLE pedidos
    ADD COLUMN forma_pagamento   ENUM('presencial','app') DEFAULT 'presencial' AFTER tipo_retirada,
    ADD COLUMN pagamento_status  ENUM('pendente','aprovado','recusado','em_analise','cancelado') DEFAULT 'pendente' AFTER forma_pagamento,
    ADD COLUMN mp_preference_id  VARCHAR(255) DEFAULT NULL AFTER pagamento_status,
    ADD COLUMN mp_payment_id     VARCHAR(100) DEFAULT NULL AFTER mp_preference_id,
    ADD COLUMN mp_payment_type   VARCHAR(50)  DEFAULT NULL AFTER mp_payment_id,
    ADD COLUMN pago_em           TIMESTAMP NULL DEFAULT NULL AFTER mp_payment_type;

-- Índice para busca rápida por preference/payment id
CREATE INDEX idx_mp_preference ON pedidos (mp_preference_id);
CREATE INDEX idx_mp_payment    ON pedidos (mp_payment_id);
CREATE INDEX idx_pagamento_status ON pedidos (pagamento_status);

-- Verificar estrutura atualizada
DESCRIBE pedidos;
