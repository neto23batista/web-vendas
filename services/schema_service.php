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
        ],
        [
            'id' => 'nfe_col_ncm',
            'component' => 'nfe',
            'type' => 'column',
            'table' => 'produtos',
            'column' => 'ncm',
            'sql' => "ALTER TABLE produtos ADD COLUMN ncm VARCHAR(8) DEFAULT '30049099'",
        ],
        [
            'id' => 'nfe_col_cfop',
            'component' => 'nfe',
            'type' => 'column',
            'table' => 'produtos',
            'column' => 'cfop',
            'sql' => "ALTER TABLE produtos ADD COLUMN cfop VARCHAR(4) DEFAULT '5102'",
        ],
        [
            'id' => 'nfe_col_cst',
            'component' => 'nfe',
            'type' => 'column',
            'table' => 'produtos',
            'column' => 'cst',
            'sql' => "ALTER TABLE produtos ADD COLUMN cst VARCHAR(3) DEFAULT '500'",
        ],
        [
            'id' => 'nfe_col_usuario_cpf',
            'component' => 'nfe',
            'type' => 'column',
            'table' => 'usuarios',
            'column' => 'cpf',
            'sql' => "ALTER TABLE usuarios ADD COLUMN cpf VARCHAR(14) DEFAULT NULL AFTER telefone",
        ],
        [
            'id' => 'nfe_idx_status',
            'component' => 'nfe',
            'type' => 'index',
            'table' => 'pedidos',
            'index' => 'idx_nfe_status',
            'sql' => "CREATE INDEX idx_nfe_status ON pedidos (nfe_status)",
        ],
        [
            'id' => 'erp_tbl_api_keys',
            'component' => 'erp',
            'type' => 'table',
            'table' => 'erp_api_keys',
            'sql' => "CREATE TABLE IF NOT EXISTS erp_api_keys (
                id INT PRIMARY KEY AUTO_INCREMENT,
                nome VARCHAR(100) NOT NULL,
                api_key VARCHAR(100) UNIQUE NOT NULL,
                ativa TINYINT(1) DEFAULT 1,
                permissoes TEXT,
                ultimo_acesso TIMESTAMP NULL,
                criado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            ) ENGINE=InnoDB",
        ],
        [
            'id' => 'erp_tbl_webhooks',
            'component' => 'erp',
            'type' => 'table',
            'table' => 'erp_webhooks',
            'sql' => "CREATE TABLE IF NOT EXISTS erp_webhooks (
                id INT PRIMARY KEY AUTO_INCREMENT,
                evento VARCHAR(60) NOT NULL,
                url_destino VARCHAR(500) NOT NULL,
                ativa TINYINT(1) DEFAULT 1,
                tentativas INT DEFAULT 0,
                ultimo_disparo TIMESTAMP NULL,
                criado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            ) ENGINE=InnoDB",
        ],
        [
            'id' => 'erp_tbl_webhook_logs',
            'component' => 'erp',
            'type' => 'table',
            'table' => 'erp_webhook_logs',
            'sql' => "CREATE TABLE IF NOT EXISTS erp_webhook_logs (
                id INT PRIMARY KEY AUTO_INCREMENT,
                id_webhook INT,
                evento VARCHAR(60),
                payload TEXT,
                resposta TEXT,
                http_status INT,
                criado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            ) ENGINE=InnoDB",
        ],
        [
            'id' => 'auth_tbl_password_resets',
            'component' => 'auth',
            'type' => 'table',
            'table' => 'password_resets',
            'sql' => "CREATE TABLE IF NOT EXISTS password_resets (
                id INT PRIMARY KEY AUTO_INCREMENT,
                id_usuario INT NOT NULL,
                token VARCHAR(64) UNIQUE NOT NULL,
                expira_em DATETIME NOT NULL,
                usado TINYINT(1) DEFAULT 0,
                criado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (id_usuario) REFERENCES usuarios(id) ON DELETE CASCADE,
                INDEX idx_token (token),
                INDEX idx_usuario (id_usuario)
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
