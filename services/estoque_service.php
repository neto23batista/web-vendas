<?php
if (!defined('FARMAVIDA_ROOT')) {
    define('FARMAVIDA_ROOT', dirname(__DIR__));
}
require_once FARMAVIDA_ROOT . '/app/core/helpers.php';

function estoque_buscar_produto_para_update(mysqli $conn, int $idProduto): ?array {
    $stmt = $conn->prepare("SELECT id, estoque_atual, disponivel, localizacao FROM produtos WHERE id = ? FOR UPDATE");
    $stmt->bind_param("i", $idProduto);
    $stmt->execute();
    $produto = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    return $produto ?: null;
}

function estoque_inserir_movimentacao(
    mysqli $conn,
    int $idProduto,
    string $tipo,
    int $quantidade,
    int $estoqueAnterior,
    int $estoqueNovo,
    string $motivo,
    ?int $idUsuario = null,
    ?int $idPedido = null,
    ?string $localizacaoOrigem = null,
    ?string $localizacaoDestino = null
): void {
    $stmt = $conn->prepare(
        "INSERT INTO movimentacoes_estoque
         (id_produto, tipo, quantidade, estoque_anterior, estoque_novo, motivo, id_pedido, id_usuario, localizacao_origem, localizacao_destino)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)"
    );
    $stmt->bind_param(
        "isiiisiiss",
        $idProduto,
        $tipo,
        $quantidade,
        $estoqueAnterior,
        $estoqueNovo,
        $motivo,
        $idPedido,
        $idUsuario,
        $localizacaoOrigem,
        $localizacaoDestino
    );
    if (!$stmt->execute()) {
        $erro = $stmt->error;
        $stmt->close();
        throw new RuntimeException($erro ?: 'Falha ao registrar movimentaÃ§Ã£o de estoque.');
    }
    $stmt->close();
}

function estoque_atualizar_quantidade_produto(mysqli $conn, int $idProduto, int $novoEstoque, ?string $localizacao = null): void {
    $disponivel = $novoEstoque > 0 ? 1 : 0;
    if ($localizacao !== null) {
        $stmt = $conn->prepare("UPDATE produtos SET estoque_atual = ?, disponivel = ?, localizacao = ? WHERE id = ?");
        $stmt->bind_param("iisi", $novoEstoque, $disponivel, $localizacao, $idProduto);
    } else {
        $stmt = $conn->prepare("UPDATE produtos SET estoque_atual = ?, disponivel = ? WHERE id = ?");
        $stmt->bind_param("iii", $novoEstoque, $disponivel, $idProduto);
    }
    if (!$stmt->execute()) {
        $erro = $stmt->error;
        $stmt->close();
        throw new RuntimeException($erro ?: 'Falha ao atualizar estoque do produto.');
    }
    $stmt->close();
}

function estoque_registrar_entrada(mysqli $conn, int $idProduto, int $quantidade, string $motivo, ?int $idUsuario = null): array {
    $conn->begin_transaction();
    try {
        $produto = estoque_buscar_produto_para_update($conn, $idProduto);
        if (!$produto) {
            throw new RuntimeException('Produto nÃ£o encontrado.');
        }

        $antes = (int)$produto['estoque_atual'];
        $depois = $antes + $quantidade;
        estoque_atualizar_quantidade_produto($conn, $idProduto, $depois);
        estoque_inserir_movimentacao($conn, $idProduto, 'entrada', $quantidade, $antes, $depois, $motivo, $idUsuario);

        $conn->commit();
        return ['antes' => $antes, 'depois' => $depois];
    } catch (Throwable $e) {
        $conn->rollback();
        throw $e;
    }
}

function estoque_registrar_saida(mysqli $conn, int $idProduto, int $quantidade, string $motivo, ?int $idUsuario = null): array {
    $conn->begin_transaction();
    try {
        $produto = estoque_buscar_produto_para_update($conn, $idProduto);
        if (!$produto) {
            throw new RuntimeException('Produto nÃ£o encontrado.');
        }

        $antes = (int)$produto['estoque_atual'];
        if ($antes < $quantidade) {
            throw new RuntimeException('Estoque insuficiente para realizar a saÃ­da.');
        }

        $depois = $antes - $quantidade;
        estoque_atualizar_quantidade_produto($conn, $idProduto, $depois);
        estoque_inserir_movimentacao($conn, $idProduto, 'saida', $quantidade, $antes, $depois, $motivo, $idUsuario);

        $conn->commit();
        return ['antes' => $antes, 'depois' => $depois];
    } catch (Throwable $e) {
        $conn->rollback();
        throw $e;
    }
}

function estoque_registrar_ajuste(mysqli $conn, int $idProduto, int $novoEstoque, string $motivo, ?int $idUsuario = null): array {
    $conn->begin_transaction();
    try {
        $produto = estoque_buscar_produto_para_update($conn, $idProduto);
        if (!$produto) {
            throw new RuntimeException('Produto nÃ£o encontrado.');
        }

        $antes = (int)$produto['estoque_atual'];
        $diferenca = abs($novoEstoque - $antes);
        estoque_atualizar_quantidade_produto($conn, $idProduto, $novoEstoque);
        estoque_inserir_movimentacao($conn, $idProduto, 'ajuste', $diferenca, $antes, $novoEstoque, $motivo, $idUsuario);

        $conn->commit();
        return ['antes' => $antes, 'depois' => $novoEstoque];
    } catch (Throwable $e) {
        $conn->rollback();
        throw $e;
    }
}

function estoque_registrar_transferencia(
    mysqli $conn,
    int $idProduto,
    int $quantidade,
    string $motivo,
    string $origem,
    string $destino,
    ?int $idUsuario = null
): array {
    $conn->begin_transaction();
    try {
        $produto = estoque_buscar_produto_para_update($conn, $idProduto);
        if (!$produto) {
            throw new RuntimeException('Produto nÃ£o encontrado.');
        }

        $antes = (int)$produto['estoque_atual'];
        estoque_atualizar_quantidade_produto($conn, $idProduto, $antes, $destino);
        estoque_inserir_movimentacao($conn, $idProduto, 'transferencia_out', $quantidade, $antes, $antes, $motivo, $idUsuario, null, $origem, $destino);

        $conn->commit();
        return ['antes' => $antes, 'depois' => $antes];
    } catch (Throwable $e) {
        $conn->rollback();
        throw $e;
    }
}

function estoque_atualizar_limites(
    mysqli $conn,
    int $idProduto,
    int $estoqueMinimo,
    int $estoqueMaximo,
    string $unidade,
    string $localizacao
): void {
    $stmt = $conn->prepare("UPDATE produtos SET estoque_minimo = ?, estoque_maximo = ?, unidade = ?, localizacao = ? WHERE id = ?");
    $stmt->bind_param("iissi", $estoqueMinimo, $estoqueMaximo, $unidade, $localizacao, $idProduto);
    if (!$stmt->execute()) {
        $erro = $stmt->error;
        $stmt->close();
        throw new RuntimeException($erro ?: 'Falha ao atualizar limites do produto.');
    }
    $stmt->close();
}

function estoque_baixar_item_pedido(mysqli $conn, int $idProduto, int $quantidade, int $idPedido, ?int $idUsuario = null): array {
    $produto = estoque_buscar_produto_para_update($conn, $idProduto);
    if (!$produto) {
        throw new RuntimeException("Produto #$idProduto nÃ£o encontrado.");
    }

    $antes = (int)$produto['estoque_atual'];
    if ($antes < $quantidade) {
        throw new RuntimeException("Estoque insuficiente para o produto #$idProduto.");
    }

    $depois = $antes - $quantidade;
    estoque_atualizar_quantidade_produto($conn, $idProduto, $depois);
    estoque_inserir_movimentacao(
        $conn,
        $idProduto,
        'saida',
        $quantidade,
        $antes,
        $depois,
        "Baixa automÃ¡tica â€“ Pedido #$idPedido",
        $idUsuario,
        $idPedido
    );

    return ['antes' => $antes, 'depois' => $depois];
}
