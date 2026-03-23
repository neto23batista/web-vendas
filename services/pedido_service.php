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
    float $taxaDeliveryValor
): array {
    $conn->begin_transaction();
    try {
        $stmt = $conn->prepare(
            "SELECT c.*, p.nome, p.preco
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

        $total = $subtotal + ($tipoRetirada === 'delivery' ? $taxaDeliveryValor : 0.0);
        $pagamentoStatus = $formaPagamento === 'app' ? 'pendente' : 'aprovado';
        $numeroMesa = '';

        $stmt = $conn->prepare(
            "INSERT INTO pedidos (id_cliente, total, observacoes, numero_mesa, tipo_retirada, forma_pagamento, pagamento_status)
             VALUES (?, ?, ?, ?, ?, ?, ?)"
        );
        $stmt->bind_param("idsssss", $idCliente, $total, $observacoes, $numeroMesa, $tipoRetirada, $formaPagamento, $pagamentoStatus);
        if (!$stmt->execute()) {
            $erro = $stmt->error;
            $stmt->close();
            throw new RuntimeException($erro ?: 'Falha ao criar pedido.');
        }
        $idPedido = (int)$conn->insert_id;
        $stmt->close();

        foreach ($itens as $item) {
            $stmt = $conn->prepare(
                "INSERT INTO pedido_itens (id_pedido, id_produto, quantidade, preco_unitario)
                 VALUES (?, ?, ?, ?)"
            );
            $stmt->bind_param("iiid", $idPedido, $item['id_produto'], $item['quantidade'], $item['preco']);
            if (!$stmt->execute()) {
                $erro = $stmt->error;
                $stmt->close();
                throw new RuntimeException($erro ?: 'Falha ao gravar item do pedido.');
            }
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
        $stmt = $conn->prepare("SELECT status FROM pedidos WHERE id = ? FOR UPDATE");
        $stmt->bind_param("i", $idPedido);
        if (!$stmt->execute()) {
            $erro = $stmt->error;
            $stmt->close();
            throw new RuntimeException($erro ?: 'Falha ao carregar pedido.');
        }
        $pedidoAtual = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (!$pedidoAtual) {
            throw new RuntimeException('Pedido nÃ£o encontrado.');
        }

        $stmt = $conn->prepare("UPDATE pedidos SET status = ? WHERE id = ?");
        $stmt->bind_param("si", $status, $idPedido);
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

            foreach ($itens as $item) {
                estoque_baixar_item_pedido(
                    $conn,
                    (int)$item['id_produto'],
                    (int)$item['quantidade'],
                    $idPedido,
                    $idUsuario
                );
            }
        }

        $conn->commit();
    } catch (Throwable $e) {
        $conn->rollback();
        throw $e;
    }
}
