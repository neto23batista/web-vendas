-- ============================================================
-- MIGRAÇÃO: NF-e + ERP + CPF no usuário
-- Execute uma única vez no banco farmavida
-- ============================================================
USE farmavida;

-- 1. Campos NF-e na tabela de pedidos
ALTER TABLE pedidos
    ADD COLUMN nfe_numero      VARCHAR(9)   DEFAULT NULL AFTER pago_em,
    ADD COLUMN nfe_serie       VARCHAR(3)   DEFAULT '001' AFTER nfe_numero,
    ADD COLUMN nfe_chave       VARCHAR(45)  DEFAULT NULL AFTER nfe_serie,
    ADD COLUMN nfe_status      ENUM('pendente','emitida','cancelada') DEFAULT 'pendente' AFTER nfe_chave,
    ADD COLUMN nfe_emitida_em  TIMESTAMP    NULL AFTER nfe_status,
    ADD COLUMN nfe_cancelada_em TIMESTAMP   NULL AFTER nfe_emitida_em,
    ADD COLUMN nfe_justificativa TEXT        DEFAULT NULL AFTER nfe_cancelada_em,
    ADD INDEX idx_nfe_status (nfe_status);

-- 2. CPF e NCM nos usuários e produtos
ALTER TABLE usuarios ADD COLUMN cpf VARCHAR(14) DEFAULT NULL AFTER telefone;
ALTER TABLE produtos
    ADD COLUMN ncm  VARCHAR(8)  DEFAULT '30049099' AFTER localizacao,
    ADD COLUMN cfop VARCHAR(4)  DEFAULT '5102'     AFTER ncm,
    ADD COLUMN cst  VARCHAR(3)  DEFAULT '500'      AFTER cfop;

-- 3. Tabelas ERP (criadas automaticamente pelo erp.php, mas aqui por completude)
CREATE TABLE IF NOT EXISTS erp_api_keys (
    id            INT PRIMARY KEY AUTO_INCREMENT,
    nome          VARCHAR(100) NOT NULL,
    api_key       VARCHAR(100) UNIQUE NOT NULL,
    ativa         TINYINT(1) DEFAULT 1,
    permissoes    TEXT,
    ultimo_acesso TIMESTAMP NULL,
    criado_em     TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS erp_webhooks (
    id             INT PRIMARY KEY AUTO_INCREMENT,
    evento         VARCHAR(60) NOT NULL,
    url_destino    VARCHAR(500) NOT NULL,
    ativa          TINYINT(1) DEFAULT 1,
    tentativas     INT DEFAULT 0,
    ultimo_disparo TIMESTAMP NULL,
    criado_em      TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- Verificar
DESCRIBE pedidos;
DESCRIBE produtos;
