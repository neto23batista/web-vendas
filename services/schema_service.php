<?php
if (!defined('FARMAVIDA_ROOT')) {
    define('FARMAVIDA_ROOT', dirname(__DIR__));
}
require_once FARMAVIDA_ROOT . '/app/core/helpers.php';

function schema_definicoes_migracao(): array {
    return [
        [
            'id' => 'estoque_col_estoque_atual',
            'component' => 'estoque',
            'type' => 'column',
            'table' => 'produtos',
            'column' => 'estoque_atual',
            'sql' => "ALTER TABLE produtos ADD COLUMN estoque_atual INT NOT NULL DEFAULT 0 AFTER disponivel",
        ],
        [
            'id' => 'estoque_col_estoque_minimo',
            'component' => 'estoque',
            'type' => 'column',
            'table' => 'produtos',
            'column' => 'estoque_minimo',
            'sql' => "ALTER TABLE produtos ADD COLUMN estoque_minimo INT NOT NULL DEFAULT 5",
        ],
        [
            'id' => 'estoque_col_estoque_maximo',
            'component' => 'estoque',
            'type' => 'column',
            'table' => 'produtos',
            'column' => 'estoque_maximo',
            'sql' => "ALTER TABLE produtos ADD COLUMN estoque_maximo INT NOT NULL DEFAULT 999",
        ],
        [
            'id' => 'estoque_col_unidade',
            'component' => 'estoque',
            'type' => 'column',
            'table' => 'produtos',
            'column' => 'unidade',
            'sql' => "ALTER TABLE produtos ADD COLUMN unidade VARCHAR(20) DEFAULT 'un'",
        ],
        [
            'id' => 'estoque_col_localizacao',
            'component' => 'estoque',
            'type' => 'column',
            'table' => 'produtos',
            'column' => 'localizacao',
            'sql' => "ALTER TABLE produtos ADD COLUMN localizacao VARCHAR(60) DEFAULT NULL",
        ],
        [
            'id' => 'estoque_tbl_movimentacoes',
            'component' => 'estoque',
            'type' => 'table',
            'table' => 'movimentacoes_estoque',
            'sql' => "CREATE TABLE IF NOT EXISTS movimentacoes_estoque (
                id INT PRIMARY KEY AUTO_INCREMENT,
                id_produto INT NOT NULL,
                tipo ENUM('entrada','saida','ajuste','transferencia_out','transferencia_in') NOT NULL,
                quantidade INT NOT NULL,
                estoque_anterior INT NOT NULL DEFAULT 0,
                estoque_novo INT NOT NULL DEFAULT 0,
                motivo VARCHAR(255) DEFAULT NULL,
                id_pedido INT DEFAULT NULL,
                id_usuario INT DEFAULT NULL,
                localizacao_origem VARCHAR(60) DEFAULT NULL,
                localizacao_destino VARCHAR(60) DEFAULT NULL,
                criado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (id_produto) REFERENCES produtos(id) ON DELETE CASCADE
            ) ENGINE=InnoDB",
        ],
        [
            'id' => 'pagamentos_col_forma_pagamento',
            'component' => 'pagamentos',
            'type' => 'column',
            'table' => 'pedidos',
            'column' => 'forma_pagamento',
            'sql' => "ALTER TABLE pedidos ADD COLUMN forma_pagamento ENUM('presencial','app') DEFAULT 'presencial' AFTER tipo_retirada",
        ],
        [
            'id' => 'pagamentos_col_pagamento_status',
            'component' => 'pagamentos',
            'type' => 'column',
            'table' => 'pedidos',
            'column' => 'pagamento_status',
            'sql' => "ALTER TABLE pedidos ADD COLUMN pagamento_status ENUM('pendente','aprovado','recusado','em_analise','cancelado') DEFAULT 'pendente' AFTER forma_pagamento",
        ],
        [
            'id' => 'pagamentos_col_mp_preference_id',
            'component' => 'pagamentos',
            'type' => 'column',
            'table' => 'pedidos',
            'column' => 'mp_preference_id',
            'sql' => "ALTER TABLE pedidos ADD COLUMN mp_preference_id VARCHAR(255) DEFAULT NULL AFTER pagamento_status",
        ],
        [
            'id' => 'pagamentos_col_mp_payment_id',
            'component' => 'pagamentos',
            'type' => 'column',
            'table' => 'pedidos',
            'column' => 'mp_payment_id',
            'sql' => "ALTER TABLE pedidos ADD COLUMN mp_payment_id VARCHAR(100) DEFAULT NULL AFTER mp_preference_id",
        ],
        [
            'id' => 'pagamentos_col_mp_payment_type',
            'component' => 'pagamentos',
            'type' => 'column',
            'table' => 'pedidos',
            'column' => 'mp_payment_type',
            'sql' => "ALTER TABLE pedidos ADD COLUMN mp_payment_type VARCHAR(50) DEFAULT NULL AFTER mp_payment_id",
        ],
        [
            'id' => 'pagamentos_col_pago_em',
            'component' => 'pagamentos',
            'type' => 'column',
            'table' => 'pedidos',
            'column' => 'pago_em',
            'sql' => "ALTER TABLE pedidos ADD COLUMN pago_em TIMESTAMP NULL DEFAULT NULL AFTER mp_payment_type",
        ],
        [
            'id' => 'pagamentos_idx_mp_preference',
            'component' => 'pagamentos',
            'type' => 'index',
            'table' => 'pedidos',
            'index' => 'idx_mp_preference',
            'sql' => "CREATE INDEX idx_mp_preference ON pedidos (mp_preference_id)",
        ],
        [
            'id' => 'pagamentos_idx_mp_payment',
            'component' => 'pagamentos',
            'type' => 'index',
            'table' => 'pedidos',
            'index' => 'idx_mp_payment',
            'sql' => "CREATE INDEX idx_mp_payment ON pedidos (mp_payment_id)",
        ],
        [
            'id' => 'pagamentos_idx_pagamento_status',
            'component' => 'pagamentos',
            'type' => 'index',
            'table' => 'pedidos',
            'index' => 'idx_pagamento_status',
            'sql' => "CREATE INDEX idx_pagamento_status ON pedidos (pagamento_status)",
        ],
        [
            'id' => 'nfe_col_nfe_numero',
            'component' => 'nfe',
            'type' => 'column',
            'table' => 'pedidos',
            'column' => 'nfe_numero',
            'sql' => "ALTER TABLE pedidos ADD COLUMN nfe_numero VARCHAR(9) DEFAULT NULL",
        ],
        [
            'id' => 'nfe_col_nfe_serie',
            'component' => 'nfe',
            'type' => 'column',
            'table' => 'pedidos',
            'column' => 'nfe_serie',
            'sql' => "ALTER TABLE pedidos ADD COLUMN nfe_serie VARCHAR(3) DEFAULT '001'",
        ],
        [
            'id' => 'nfe_col_nfe_chave',
            'component' => 'nfe',
            'type' => 'column',
            'table' => 'pedidos',
            'column' => 'nfe_chave',
            'sql' => "ALTER TABLE pedidos ADD COLUMN nfe_chave VARCHAR(45) DEFAULT NULL",
        ],
        [
            'id' => 'nfe_col_nfe_status',
            'component' => 'nfe',
            'type' => 'column',
            'table' => 'pedidos',
            'column' => 'nfe_status',
            'sql' => "ALTER TABLE pedidos ADD COLUMN nfe_status ENUM('pendente','emitida','cancelada') DEFAULT 'pendente'",
        ],
        [
            'id' => 'nfe_col_nfe_emitida_em',
            'component' => 'nfe',
            'type' => 'column',
            'table' => 'pedidos',
            'column' => 'nfe_emitida_em',
            'sql' => "ALTER TABLE pedidos ADD COLUMN nfe_emitida_em TIMESTAMP NULL",
        ],
        [
            'id' => 'nfe_col_nfe_cancelada_em',
            'component' => 'nfe',
            'type' => 'column',
            'table' => 'pedidos',
            'column' => 'nfe_cancelada_em',
            'sql' => "ALTER TABLE pedidos ADD COLUMN nfe_cancelada_em TIMESTAMP NULL",
        ],
        [
            'id' => 'nfe_col_nfe_justificativa',
            'component' => 'nfe',
            'type' => 'column',
            'table' => 'pedidos',
            'column' => 'nfe_justificativa',
            'sql' => "ALTER TABLE pedidos ADD COLUMN nfe_justificativa TEXT DEFAULT NULL",
        ],        [
            'id' => 'entrega_tipo_retirada_ext',
            'component' => 'pagamentos',
            'type' => 'column',
            'table' => 'pedidos',
            'column' => 'tipo_retirada',
            'sql' => "ALTER TABLE pedidos MODIFY COLUMN tipo_retirada ENUM('mesa','balcao','delivery','retirada_1h','delivery_expressa') DEFAULT 'balcao'",
        ],
        [
            'id' => 'entrega_reserva_expira_em',
            'component' => 'pagamentos',
            'type' => 'column',
            'table' => 'pedidos',
            'column' => 'reserva_expira_em',
            'sql' => "ALTER TABLE pedidos ADD COLUMN reserva_expira_em DATETIME NULL AFTER pago_em",
        ],
        [
            'id' => 'entrega_janela_inicio',
            'component' => 'pagamentos',
            'type' => 'column',
            'table' => 'pedidos',
            'column' => 'janela_inicio',
            'sql' => "ALTER TABLE pedidos ADD COLUMN janela_inicio DATETIME NULL AFTER reserva_expira_em",
        ],
        [
            'id' => 'entrega_janela_fim',
            'component' => 'pagamentos',
            'type' => 'column',
            'table' => 'pedidos',
            'column' => 'janela_fim',
            'sql' => "ALTER TABLE pedidos ADD COLUMN janela_fim DATETIME NULL AFTER janela_inicio",
        ],
        [
            'id' => 'fid_tbl_saldos',
            'component' => 'fidelidade',
            'type' => 'table',
            'table' => 'fidelidade_saldos',
            'sql' => "CREATE TABLE IF NOT EXISTS fidelidade_saldos (
                id_cliente INT NOT NULL PRIMARY KEY,
                saldo_pontos INT NOT NULL DEFAULT 0,
                atualizado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                FOREIGN KEY (id_cliente) REFERENCES usuarios(id) ON DELETE CASCADE
            ) ENGINE=InnoDB",
        ],
        [
            'id' => 'fid_tbl_movimentos',
            'component' => 'fidelidade',
            'type' => 'table',
            'table' => 'fidelidade_movimentos',
            'sql' => "CREATE TABLE IF NOT EXISTS fidelidade_movimentos (
                id INT PRIMARY KEY AUTO_INCREMENT,
                id_cliente INT NOT NULL,
                tipo ENUM('acumulo','resgate','ajuste') NOT NULL,
                pontos INT NOT NULL,
                descricao VARCHAR(255) DEFAULT NULL,
                id_pedido INT DEFAULT NULL,
                criado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (id_cliente) REFERENCES usuarios(id) ON DELETE CASCADE,
                FOREIGN KEY (id_pedido) REFERENCES pedidos(id) ON DELETE SET NULL
            ) ENGINE=InnoDB",
        ],
        [
            'id' => 'fid_tbl_lembretes_reposicao',
            'component' => 'fidelidade',
            'type' => 'table',
            'table' => 'reposicao_lembretes',
            'sql' => "CREATE TABLE IF NOT EXISTS reposicao_lembretes (
                id INT PRIMARY KEY AUTO_INCREMENT,
                id_cliente INT NOT NULL,
                id_produto INT NOT NULL,
                intervalo_dias INT NOT NULL,
                proxima_reposicao_em DATE NOT NULL,
                status ENUM('pendente','enviado','ignorado') DEFAULT 'pendente',
                criado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                UNIQUE KEY uq_lembrete_produto_data (id_cliente, id_produto, proxima_reposicao_em),
                FOREIGN KEY (id_cliente) REFERENCES usuarios(id) ON DELETE CASCADE,
                FOREIGN KEY (id_produto) REFERENCES produtos(id) ON DELETE CASCADE
            ) ENGINE=InnoDB",
        ],
        [
            'id' => 'cart_tbl_tokens',
            'component' => 'marketing',
            'type' => 'table',
            'table' => 'carrinho_tokens',
            'sql' => "CREATE TABLE IF NOT EXISTS carrinho_tokens (
                id INT PRIMARY KEY AUTO_INCREMENT,
                id_cliente INT NOT NULL,
                token VARCHAR(64) NOT NULL UNIQUE,
                payload LONGTEXT NOT NULL,
                expiracao DATETIME NOT NULL,
                criado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (id_cliente) REFERENCES usuarios(id) ON DELETE CASCADE
            ) ENGINE=InnoDB",
        ],
        [
            'id' => 'rast_col_url',
            'component' => 'pagamentos',
            'type' => 'column',
            'table' => 'pedidos',
            'column' => 'rastreio_url',
            'sql' => "ALTER TABLE pedidos ADD COLUMN rastreio_url VARCHAR(255) DEFAULT NULL AFTER janela_fim",
        ],
        [
            'id' => 'produto_exige_receita',
            'component' => 'seguranca',
            'type' => 'column',
            'table' => 'produtos',
            'column' => 'exige_receita',
            'sql' => "ALTER TABLE produtos ADD COLUMN exige_receita TINYINT(1) NOT NULL DEFAULT 0 AFTER disponivel",
        ],
        [
            'id' => 'produto_classe_medicamento',
            'component' => 'seguranca',
            'type' => 'column',
            'table' => 'produtos',
            'column' => 'classe_medicamento',
            'sql' => "ALTER TABLE produtos ADD COLUMN classe_medicamento ENUM('livre','otc','antibiotico','controlado','psicotropico') DEFAULT 'livre' AFTER exige_receita",
        ],
        [
            'id' => 'pedido_status_clinico',
            'component' => 'seguranca',
            'type' => 'column',
            'table' => 'pedidos',
            'column' => 'status_clinico',
            'sql' => "ALTER TABLE pedidos ADD COLUMN status_clinico ENUM('nao_exige','aguardando_receita','em_validacao','liberado','rejeitado') DEFAULT 'nao_exige' AFTER estoque_reservado",
        ],
        [
            'id' => 'pedido_separacao_iniciada',
            'component' => 'operacao',
            'type' => 'column',
            'table' => 'pedidos',
            'column' => 'separacao_iniciada_em',
            'sql' => "ALTER TABLE pedidos ADD COLUMN separacao_iniciada_em DATETIME NULL AFTER status_clinico",
        ],
        [
            'id' => 'pedido_separacao_finalizada',
            'component' => 'operacao',
            'type' => 'column',
            'table' => 'pedidos',
            'column' => 'separacao_finalizada_em',
            'sql' => "ALTER TABLE pedidos ADD COLUMN separacao_finalizada_em DATETIME NULL AFTER separacao_iniciada_em",
        ],
        [
            'id' => 'pedido_cancel_reason',
            'component' => 'operacao',
            'type' => 'column',
            'table' => 'pedidos',
            'column' => 'cancel_reason',
            'sql' => "ALTER TABLE pedidos ADD COLUMN cancel_reason VARCHAR(255) DEFAULT NULL AFTER separacao_finalizada_em",
        ],
        [
            'id' => 'tbl_receitas',
            'component' => 'seguranca',
            'type' => 'table',
            'table' => 'receitas_uploads',
            'sql' => "CREATE TABLE IF NOT EXISTS receitas_uploads (
                id INT PRIMARY KEY AUTO_INCREMENT,
                id_pedido INT NOT NULL,
                id_cliente INT NOT NULL,
                caminho_arquivo VARCHAR(255) NOT NULL,
                status ENUM('pendente','aprovada','rejeitada') DEFAULT 'pendente',
                observacao VARCHAR(255) DEFAULT NULL,
                criado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                validado_em TIMESTAMP NULL,
                validado_por INT NULL,
                FOREIGN KEY (id_pedido) REFERENCES pedidos(id) ON DELETE CASCADE,
                FOREIGN KEY (id_cliente) REFERENCES usuarios(id) ON DELETE CASCADE,
                FOREIGN KEY (validado_por) REFERENCES usuarios(id) ON DELETE SET NULL
            ) ENGINE=InnoDB",
        ],
        [
            'id' => 'tbl_receitas_auditoria',
            'component' => 'seguranca',
            'type' => 'table',
            'table' => 'receitas_auditoria',
            'sql' => "CREATE TABLE IF NOT EXISTS receitas_auditoria (
                id INT PRIMARY KEY AUTO_INCREMENT,
                id_receita INT NOT NULL,
                acao VARCHAR(50) NOT NULL,
                detalhe VARCHAR(255) DEFAULT NULL,
                id_usuario INT NULL,
                criado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (id_receita) REFERENCES receitas_uploads(id) ON DELETE CASCADE,
                FOREIGN KEY (id_usuario) REFERENCES usuarios(id) ON DELETE SET NULL
            ) ENGINE=InnoDB",
        ],
        [
            'id' => 'tbl_estoque_alertas',
            'component' => 'operacao',
            'type' => 'table',
            'table' => 'estoque_alertas',
            'sql' => "CREATE TABLE IF NOT EXISTS estoque_alertas (
                id INT PRIMARY KEY AUTO_INCREMENT,
                id_produto INT NOT NULL,
                tipo ENUM('baixo','ruptura_imminente') NOT NULL,
                estoque_atual INT NOT NULL,
                estoque_minimo INT NOT NULL,
                criado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (id_produto) REFERENCES produtos(id) ON DELETE CASCADE
            ) ENGINE=InnoDB",
        ],
        [
            'id' => 'tbl_integracoes_webhooks',
            'component' => 'integracoes',
            'type' => 'table',
            'table' => 'integracoes_webhooks',
            'sql' => "CREATE TABLE IF NOT EXISTS integracoes_webhooks (
                id INT PRIMARY KEY AUTO_INCREMENT,
                nome VARCHAR(80) NOT NULL,
                destino VARCHAR(255) NOT NULL,
                segredo VARCHAR(64) NOT NULL,
                ativo TINYINT(1) NOT NULL DEFAULT 1,
                criado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            ) ENGINE=InnoDB",
        ],
        [
            'id' => 'tbl_integracoes_eventos',
            'component' => 'integracoes',
            'type' => 'table',
            'table' => 'integracoes_eventos',
            'sql' => "CREATE TABLE IF NOT EXISTS integracoes_eventos (
                id INT PRIMARY KEY AUTO_INCREMENT,
                id_webhook INT NOT NULL,
                payload LONGTEXT NOT NULL,
                recebido_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (id_webhook) REFERENCES integracoes_webhooks(id) ON DELETE CASCADE
            ) ENGINE=InnoDB",
        ],
        [
            'id' => 'tbl_exportacoes_fiscais',
            'component' => 'integracoes',
            'type' => 'table',
            'table' => 'exportacoes_fiscais',
            'sql' => "CREATE TABLE IF NOT EXISTS exportacoes_fiscais (
                id INT PRIMARY KEY AUTO_INCREMENT,
                tipo ENUM('nfe','sefaz_csv','contabil') NOT NULL,
                arquivo_path VARCHAR(255) NOT NULL,
                gerado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            ) ENGINE=InnoDB",
        ],
];
}

function schema_filtrar_migracoes(array $migracoes, ?array $componentes = null): array {
    if (!$componentes) {
        return $migracoes;
    }

    return array_values(array_filter(
        $migracoes,
        static fn(array $migracao): bool => in_array($migracao['component'], $componentes, true)
    ));
}

function schema_tabela_existe(mysqli $conn, string $tabela): bool {
    $tabela = $conn->real_escape_string($tabela);
    $sql = "SELECT COUNT(*) AS total
            FROM information_schema.TABLES
            WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = '{$tabela}'";
    $row = $conn->query($sql)->fetch_assoc();
    return (int)($row['total'] ?? 0) > 0;
}

function schema_coluna_existe(mysqli $conn, string $tabela, string $coluna): bool {
    if (!schema_tabela_existe($conn, $tabela)) {
        return false;
    }

    $tabela = $conn->real_escape_string($tabela);
    $coluna = $conn->real_escape_string($coluna);
    $sql = "SELECT COUNT(*) AS total
            FROM information_schema.COLUMNS
            WHERE TABLE_SCHEMA = DATABASE()
              AND TABLE_NAME = '{$tabela}'
              AND COLUMN_NAME = '{$coluna}'";
    $row = $conn->query($sql)->fetch_assoc();
    return (int)($row['total'] ?? 0) > 0;
}

function schema_indice_existe(mysqli $conn, string $tabela, string $indice): bool {
    if (!schema_tabela_existe($conn, $tabela)) {
        return false;
    }

    $tabela = $conn->real_escape_string($tabela);
    $indice = $conn->real_escape_string($indice);
    $sql = "SELECT COUNT(*) AS total
            FROM information_schema.STATISTICS
            WHERE TABLE_SCHEMA = DATABASE()
              AND TABLE_NAME = '{$tabela}'
              AND INDEX_NAME = '{$indice}'";
    $row = $conn->query($sql)->fetch_assoc();
    return (int)($row['total'] ?? 0) > 0;
}

function schema_migracao_aplicada(mysqli $conn, array $migracao): bool {
    return match ($migracao['type']) {
        'table' => schema_tabela_existe($conn, $migracao['table']),
        'column' => schema_coluna_existe($conn, $migracao['table'], $migracao['column']),
        'index' => schema_indice_existe($conn, $migracao['table'], $migracao['index']),
        default => false,
    };
}

function schema_listar_migracoes_pendentes(mysqli $conn, ?array $componentes = null): array {
    $migracoes = schema_filtrar_migracoes(schema_definicoes_migracao(), $componentes);
    return array_values(array_filter(
        $migracoes,
        static fn(array $migracao): bool => !schema_migracao_aplicada($conn, $migracao)
    ));
}

function schema_componentes_pendentes(mysqli $conn, array $componentes): bool {
    return count(schema_listar_migracoes_pendentes($conn, $componentes)) > 0;
}

function schema_executar_migracoes(mysqli $conn, ?array $componentes = null): array {
    $pendentes = schema_listar_migracoes_pendentes($conn, $componentes);
    $executadas = [];
    $falhas = [];

    foreach ($pendentes as $migracao) {
        if ($conn->query($migracao['sql'])) {
            $executadas[] = $migracao['id'];
        } else {
            $falhas[] = [
                'id' => $migracao['id'],
                'erro' => $conn->error,
            ];
        }
    }

    return [
        'executadas' => $executadas,
        'falhas' => $falhas,
    ];
}

function schema_componentes_disponiveis(): array {
    $componentes = array_map(
        static fn(array $migracao): string => $migracao['component'],
        schema_definicoes_migracao()
    );
    $componentes = array_values(array_unique($componentes));
    sort($componentes);
    return $componentes;
}
