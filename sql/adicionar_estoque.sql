



USE farmavida;


ALTER TABLE produtos
    ADD COLUMN estoque_atual  INT NOT NULL DEFAULT 0     AFTER disponivel,
    ADD COLUMN estoque_minimo INT NOT NULL DEFAULT 5     AFTER estoque_atual,
    ADD COLUMN estoque_maximo INT NOT NULL DEFAULT 999   AFTER estoque_minimo,
    ADD COLUMN unidade        VARCHAR(20) DEFAULT 'un'   AFTER estoque_maximo,
    ADD COLUMN localizacao    VARCHAR(60) DEFAULT NULL   AFTER unidade,
    ADD INDEX idx_estoque_atual (estoque_atual);


CREATE TABLE IF NOT EXISTS movimentacoes_estoque (
    id                  INT PRIMARY KEY AUTO_INCREMENT,
    id_produto          INT NOT NULL,
    tipo                ENUM('entrada','saida','ajuste','transferencia_out','transferencia_in') NOT NULL,
    quantidade          INT NOT NULL,                         
    estoque_anterior    INT NOT NULL DEFAULT 0,
    estoque_novo        INT NOT NULL DEFAULT 0,
    motivo              VARCHAR(255) DEFAULT NULL,
    id_pedido           INT DEFAULT NULL,                     
    id_usuario          INT DEFAULT NULL,                     
    localizacao_origem  VARCHAR(60) DEFAULT NULL,             
    localizacao_destino VARCHAR(60) DEFAULT NULL,
    criado_em           TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (id_produto) REFERENCES produtos(id) ON DELETE CASCADE,
    FOREIGN KEY (id_pedido)  REFERENCES pedidos(id)  ON DELETE SET NULL,
    FOREIGN KEY (id_usuario) REFERENCES usuarios(id)  ON DELETE SET NULL,
    INDEX idx_produto   (id_produto),
    INDEX idx_tipo      (tipo),
    INDEX idx_criado_em (criado_em)
) ENGINE=InnoDB;


UPDATE produtos SET estoque_atual = 10 WHERE estoque_atual = 0;


INSERT INTO movimentacoes_estoque (id_produto, tipo, quantidade, estoque_anterior, estoque_novo, motivo, criado_em)
SELECT id, 'entrada', 10, 0, 10, 'Estoque inicial cadastrado pelo sistema', NOW()
FROM produtos;


SELECT
    p.nome,
    p.categoria,
    p.estoque_atual,
    p.estoque_minimo,
    CASE
        WHEN p.estoque_atual = 0          THEN '🔴 Sem estoque'
        WHEN p.estoque_atual <= p.estoque_minimo THEN '🟡 Estoque baixo'
        ELSE '🟢 Normal'
    END AS situacao
FROM produtos p
ORDER BY p.estoque_atual ASC;
