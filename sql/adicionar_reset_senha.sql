-- ============================================================
-- MIGRAÇÃO: Recuperação de senha – FarmaVida
-- Execute uma única vez no banco farmavida
-- ============================================================
USE farmavida;

CREATE TABLE IF NOT EXISTS password_resets (
    id         INT PRIMARY KEY AUTO_INCREMENT,
    id_usuario INT NOT NULL,
    token      VARCHAR(64) UNIQUE NOT NULL,
    expira_em  TIMESTAMP NOT NULL,
    usado      TINYINT(1) DEFAULT 0,
    criado_em  TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (id_usuario) REFERENCES usuarios(id) ON DELETE CASCADE,
    INDEX idx_token    (token),
    INDEX idx_usuario  (id_usuario)
) ENGINE=InnoDB;
