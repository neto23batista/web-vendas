<?php
if (!defined('FARMAVIDA_ROOT')) {
    define('FARMAVIDA_ROOT', dirname(__DIR__));
}
require_once FARMAVIDA_ROOT . '/app/core/helpers.php';
require_once __DIR__ . '/estoque_service.php';

function pedido_criar_do_carrinho(
    mysqli $conn,
    int $idCliente,
    string $observacoes,
    string $tipoRetirada,
    string $formaPagamento,
    float $taxaDeliveryValor,
    ?string $janelaInicio = null,
    ?string $janelaFim = null,
    ?string $caminhoReceita = null
): array {
    $conn->begin_transaction();
    try {
        $stmt = $conn->prepare(
            "SELECT c.*, p.nome, p.preco, p.categoria, p.exige_receita, p.classe_medicamento
             FROM carrinho c
             JOIN produtos p ON c.id_produto = p.id
             WHERE c.id_cliente = ? AND p.disponivel = 1
             FOR UPDATE"
        );
        $stmt->bind_param("i", $idCliente);
        $stmt->execute();
        $itens = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();

        if (empty($itens)) {
            throw new RuntimeException('Sacola vazia.');
        }

        $subtotal = 0.0;
        foreach ($itens as $item) {
            $subtotal += (float)$item['preco'] * (int)$item['quantidade'];
        }

        $isDelivery = in_array($tipoRetirada, ['delivery', 'delivery_expressa'], true);
        $total = $subtotal + ($isDelivery ? $taxaDeliveryValor : 0.0);
        $pagamentoStatus = $formaPagamento === 'app' ? 'pendente' : 'aprovado';
        $numeroMesa = '';

        // Bloqueio por classe/receita
        $exigeReceita = false;
        foreach ($itens as $item) {
            if ((int)($item['exige_receita'] ?? 0) === 1) {
                $exigeReceita = true; break;
            }
            $classe = $item['classe_medicamento'] ?? 'livre';
            if (in_array($classe, ['antibiotico','controlado','psicotropico'], true)) {
                $exigeReceita = true; break;
            }
        }

        $agora = new DateTimeImmutable('now');
        $reservaExpiraEm = null;
        if ($tipoRetirada === 'retirada_1h') {
            $reservaExpiraEm = $agora->modify('+60 minutes')->format('Y-m-d H:i:s');
            $janelaInicio = $janelaInicio ?: $agora->format('Y-m-d H:i:s');
            $janelaFim    = $janelaFim ?: $agora->modify('+60 minutes')->format('Y-m-d H:i:s');
        } elseif ($tipoRetirada === 'delivery_expressa') {
            $reservaExpiraEm = $agora->modify('+90 minutes')->format('Y-m-d H:i:s');
            $janelaInicio = $janelaInicio ?: $agora->modify('+60 minutes')->format('Y-m-d H:i:s');
            $janelaFim    = $janelaFim ?: $agora->modify('+120 minutes')->format('Y-m-d H:i:s');
        }

        $stmt = $conn->prepare(
            "INSERT INTO pedidos (id_cliente, total, observacoes, numero_mesa, tipo_retirada, forma_pagamento, pagamento_status, reserva_expira_em, janela_inicio, janela_fim, estoque_reservado, status_clinico)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)"
        );
        $estoqueReservado = 1;
        $statusClinico = $exigeReceita ? 'aguardando_receita' : 'nao_exige';
        $stmt->bind_param(
            "idssssssssii",
            $idCliente,
            $total,
            $observacoes,
            $numeroMesa,
            $tipoRetirada,
            $formaPagamento,
            $pagamentoStatus,
            $reservaExpiraEm,
            $janelaInicio,
            $janelaFim,
            $estoqueReservado,
            $statusClinico
        );
        if (!$stmt->execute()) {
            $erro = $stmt->error;
            $stmt->close();
            throw new RuntimeException($erro ?: 'Falha ao criar pedido.');
        }
        $idPedido = (int)$conn->insert_id;
        $stmt->close();

        // URL de rastreio simples
        $rastreioUrl = "/rastreio.php?pedido={$idPedido}";
        $stmt = $conn->prepare("UPDATE pedidos SET rastreio_url = ? WHERE id = ?");
        $stmt->bind_param("si", $rastreioUrl, $idPedido);
        $stmt->execute();
        $stmt->close();

        foreach ($itens as $item) {
            $idProd = (int)$item['id_produto'];
            $qtdProd = (int)$item['quantidade'];
            // Reserva: baixa estoque agora; compensação será feita no cancelamento ou ignorada no entregue.
            $motivoReserva = "Reserva de pedido #$idPedido";
            estoque_registrar_saida($conn, $idProd, $qtdProd, $motivoReserva, $idCliente);

            $stmt = $conn->prepare(
                "INSERT INTO pedido_itens (id_pedido, id_produto, quantidade, preco_unitario)
                 VALUES (?, ?, ?, ?)"
            );
            $stmt->bind_param("iiid", $idPedido, $idProd, $qtdProd, $item['preco']);
            if (!$stmt->execute()) {
                $erro = $stmt->error;
                $stmt->close();
                throw new RuntimeException($erro ?: 'Falha ao gravar item do pedido.');
            }
            $stmt->close();
        }

        // Fidelidade e lembretes de reposição
        fidelidade_acumular_pedido($conn, $idCliente, $idPedido, $total);
        fidelidade_registrar_lembretes($conn, $idCliente, $itens);

        // Receita digital
        if ($exigeReceita && $caminhoReceita) {
            $stmt = $conn->prepare(
                "INSERT INTO receitas_uploads (id_pedido, id_cliente, caminho_arquivo, status)
                 VALUES (?, ?, ?, 'pendente')"
            );
            $stmt->bind_param("iis", $idPedido, $idCliente, $caminhoReceita);
            $stmt->execute();
            $idReceita = (int)$conn->insert_id;
            $stmt->close();

            $stmt = $conn->prepare(
                "INSERT INTO receitas_auditoria (id_receita, acao, detalhe, id_usuario)
                 VALUES (?, 'upload', 'Arquivo recebido no checkout', NULL)"
            );
            $stmt->bind_param("i", $idReceita);
            $stmt->execute();
            $stmt->close();
        }

        $stmt = $conn->prepare("DELETE FROM carrinho WHERE id_cliente = ?");
        $stmt->bind_param("i", $idCliente);
        if (!$stmt->execute()) {
            $erro = $stmt->error;
            $stmt->close();
            throw new RuntimeException($erro ?: 'Falha ao limpar carrinho.');
        }
        $stmt->close();

        $conn->commit();

        return [
            'id_pedido' => $idPedido,
            'itens' => $itens,
            'total' => $total,
            'subtotal' => $subtotal,
        ];
    } catch (Throwable $e) {
        $conn->rollback();
        throw $e;
    }
}

function pedido_atualizar_status(mysqli $conn, int $idPedido, string $status, ?int $idUsuario = null): void {
    $conn->begin_transaction();
    try {
        $stmt = $conn->prepare("SELECT status, estoque_reservado FROM pedidos WHERE id = ? FOR UPDATE");
        $stmt->bind_param("i", $idPedido);
        if (!$stmt->execute()) {
            $erro = $stmt->error;
            $stmt->close();
            throw new RuntimeException($erro ?: 'Falha ao carregar pedido.');
        }
        $pedidoAtual = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (!$pedidoAtual) {
            throw new RuntimeException('Pedido não encontrado.');
        }

        $stmt = $conn->prepare("UPDATE pedidos SET status = ?, estoque_reservado = ? WHERE id = ?");
        $novoReservado = $pedidoAtual['estoque_reservado'] ?? 0;
        $stmt->bind_param("sii", $status, $novoReservado, $idPedido);
        if (!$stmt->execute()) {
            $erro = $stmt->error;
            $stmt->close();
            throw new RuntimeException($erro ?: 'Falha ao atualizar pedido.');
        }
        $stmt->close();

        if ($status === 'entregue' && $pedidoAtual['status'] !== 'entregue') {
            $stmt = $conn->prepare("SELECT id_produto, quantidade FROM pedido_itens WHERE id_pedido = ?");
            $stmt->bind_param("i", $idPedido);
            $stmt->execute();
            $itens = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
            $stmt->close();

            if ((int)$pedidoAtual['estoque_reservado'] === 0) {
                foreach ($itens as $item) {
                    estoque_baixar_item_pedido(
                        $conn,
                        (int)$item['id_produto'],
                        (int)$item['quantidade'],
                        $idPedido,
                        $idUsuario
                    );
                }
            } else {
                $novoReservado = 0;
                $stmt = $conn->prepare("UPDATE pedidos SET estoque_reservado = 0 WHERE id = ?");
                $stmt->bind_param("i", $idPedido);
                $stmt->execute();
                $stmt->close();
            }
        }

        if ($status === 'cancelado' && (int)($pedidoAtual['estoque_reservado'] ?? 0) === 1) {
            $stmt = $conn->prepare("SELECT id_produto, quantidade FROM pedido_itens WHERE id_pedido = ?");
            $stmt->bind_param("i", $idPedido);
            $stmt->execute();
            $itens = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
            $stmt->close();

            foreach ($itens as $item) {
                estoque_registrar_entrada(
                    $conn,
                    (int)$item['id_produto'],
                    (int)$item['quantidade'],
                    "Estorno de reserva pedido #$idPedido",
                    $idUsuario
                );
            }
            $stmt = $conn->prepare("UPDATE pedidos SET estoque_reservado = 0 WHERE id = ?");
            $stmt->bind_param("i", $idPedido);
            $stmt->execute();
            $stmt->close();
        }

        $conn->commit();
    } catch (Throwable $e) {
        $conn->rollback();
        throw $e;
    }
}

/**
 * Acumula pontos de fidelidade com base no total do pedido.
 * Regra simples: 1 ponto por R$1 (arredondado para baixo).
 */
function fidelidade_acumular_pedido(mysqli $conn, int $idCliente, int $idPedido, float $total): void {
    $pontos = (int)floor($total);
    if ($pontos <= 0) {
        return;
    }

    // Upsert de saldo
    $stmt = $conn->prepare(
        "INSERT INTO fidelidade_saldos (id_cliente, saldo_pontos)
         VALUES (?, ?)
         ON DUPLICATE KEY UPDATE saldo_pontos = saldo_pontos + VALUES(saldo_pontos)"
    );
    $stmt->bind_param("ii", $idCliente, $pontos);
    if (!$stmt->execute()) {
        $erro = $stmt->error;
        $stmt->close();
        throw new RuntimeException($erro ?: 'Falha ao atualizar saldo de pontos.');
    }
    $stmt->close();

    fidelidade_registrar_movimento($conn, $idCliente, 'acumulo', $pontos, "Cashback pedido #$idPedido (1 ponto = R$1)", $idPedido);
}

function fidelidade_registrar_movimento(mysqli $conn, int $idCliente, string $tipo, int $pontos, string $descricao, ?int $idPedido = null): void {
    $stmt = $conn->prepare(
        "INSERT INTO fidelidade_movimentos (id_cliente, tipo, pontos, descricao, id_pedido)
         VALUES (?, ?, ?, ?, ?)"
    );
    $stmt->bind_param("isisi", $idCliente, $tipo, $pontos, $descricao, $idPedido);
    if (!$stmt->execute()) {
        $erro = $stmt->error;
        $stmt->close();
        throw new RuntimeException($erro ?: 'Falha ao registrar movimento de pontos.');
    }
    $stmt->close();
}

/**
 * Gera lembretes de reposição (30/60/90 dias) para itens comprados.
 */
function fidelidade_registrar_lembretes(mysqli $conn, int $idCliente, array $itens): void {
    $intervalos = [30, 60, 90];
    foreach ($itens as $item) {
        $idProd = (int)$item['id_produto'];
        foreach ($intervalos as $dias) {
            $data = (new DateTimeImmutable('today'))->modify("+{$dias} days")->format('Y-m-d');
            $stmt = $conn->prepare(
                "INSERT IGNORE INTO reposicao_lembretes (id_cliente, id_produto, intervalo_dias, proxima_reposicao_em)
                 VALUES (?, ?, ?, ?)"
            );
            $stmt->bind_param("iiis", $idCliente, $idProd, $dias, $data);
            $stmt->execute();
            $stmt->close();
        }
    }
}
