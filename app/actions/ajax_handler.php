<?php
require_once FARMAVIDA_ROOT . '/app/core/bootstrap.php';
require_once FARMAVIDA_ROOT . '/app/core/config.php';
require_once FARMAVIDA_ROOT . '/app/core/helpers.php';
require_once FARMAVIDA_ROOT . '/services/pedido_service.php';

header('Content-Type: application/json');

$action = $_GET['action'] ?? $_POST['action'] ?? '';

const STATUS_VALIDOS = ['pendente', 'preparando', 'pronto', 'entregue', 'cancelado'];

function carregar_itens_pedido_admin(mysqli $conn, int $pedidoId): array
{
    $stmt = $conn->prepare(
        "SELECT pi.*, pr.nome as produto_nome
         FROM pedido_itens pi
         JOIN produtos pr ON pi.id_produto = pr.id
         WHERE pi.id_pedido = ?"
    );
    $stmt->bind_param("i", $pedidoId);
    $stmt->execute();
    $itens = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    return $itens;
}

function montar_resposta_pedidos_dono(mysqli $conn, int $pagina, int $porPagina): array
{
    $pagina = max(1, $pagina);
    $porPagina = max(1, min($porPagina, 100));

    $totalPedidos = (int)$conn->query("SELECT COUNT(*) AS t FROM pedidos")->fetch_assoc()['t'];
    $totalPaginas = $totalPedidos > 0 ? (int)ceil($totalPedidos / $porPagina) : 0;
    $paginaAjustada = $totalPaginas > 0 ? min($pagina, $totalPaginas) : 1;
    $offset = ($paginaAjustada - 1) * $porPagina;

    $stmt = $conn->prepare(
        "SELECT p.*, u.nome as cliente_nome, u.telefone, u.endereco
         FROM pedidos p
         JOIN usuarios u ON p.id_cliente = u.id
         ORDER BY p.criado_em DESC
         LIMIT ? OFFSET ?"
    );
    $stmt->bind_param("ii", $porPagina, $offset);
    $stmt->execute();
    $pedidos = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    foreach ($pedidos as &$pedido) {
        $pedido['itens'] = carregar_itens_pedido_admin($conn, (int)$pedido['id']);
        $pedido['total_itens'] = count($pedido['itens']);
    }
    unset($pedido);

    return [
        'pedidos' => $pedidos,
        'paginacao' => [
            'pagina' => $paginaAjustada,
            'por_pagina' => $porPagina,
            'total_pedidos' => $totalPedidos,
            'total_paginas' => $totalPaginas,
        ],
    ];
}

switch ($action) {

    case 'buscar_pedidos_dono':
        if (!isset($_SESSION['usuario']) || $_SESSION['tipo'] != 'dono') {
            echo json_encode(['erro' => 'NÃ£o autorizado']); exit;
        }
        $pagina = (int)($_GET['pagina'] ?? 1);
        $porPagina = (int)($_GET['limite'] ?? 20);
        echo json_encode(montar_resposta_pedidos_dono($conn, $pagina, $porPagina));
        break;

     

    case 'buscar_stats_dono':
        if (!isset($_SESSION['usuario']) || $_SESSION['tipo'] != 'dono') {
            echo json_encode(['erro' => 'NÃ£o autorizado']); exit;
        }
        $stats = $conn->query(
            "SELECT COUNT(DISTINCT id) as total_pedidos,
                    SUM(total) as faturamento_total,
                    SUM(CASE WHEN status='pendente' THEN 1 ELSE 0 END) as pedidos_pendentes
             FROM pedidos"
        )->fetch_assoc();

        $total_produtos = $conn->query(
            "SELECT COUNT(*) as t FROM produtos WHERE disponivel=1"
        )->fetch_assoc()['t'];

        $total_clientes = $conn->query(
            "SELECT COUNT(*) as t FROM usuarios WHERE tipo='cliente'"
        )->fetch_assoc()['t'];

        echo json_encode([
            'stats'          => $stats,
            'total_produtos' => $total_produtos,
            'total_clientes' => $total_clientes,
        ]);
        break;

     

    case 'atualizar_status':
        if (!isset($_SESSION['usuario']) || $_SESSION['tipo'] != 'dono') {
            echo json_encode(['erro' => 'NÃ£o autorizado']); exit;
        }
        verificar_csrf();

        $id_pedido = (int)($_POST['id_pedido'] ?? 0);
        $status    = $_POST['status'] ?? '';
        try {
            if ($id_pedido <= 0 || !in_array($status, STATUS_VALIDOS, true)) {
                echo json_encode(['erro' => 'Dados invalidos']); exit;
            }

            pedido_atualizar_status($conn, $id_pedido, $status, (int)($_SESSION['id_usuario'] ?? 0) ?: null);
            echo json_encode(['sucesso' => true, 'mensagem' => 'Status atualizado!']);
        } catch (RuntimeException $e) {
            echo json_encode(['erro' => $e->getMessage()]);
        } catch (Throwable $e) {
            echo json_encode(['erro' => 'Falha ao atualizar o pedido']);
        }
        break;


     

    case 'buscar_pedidos_cliente':
        if (!isset($_SESSION['usuario']) || $_SESSION['tipo'] != 'cliente') {
            echo json_encode(['erro' => 'NÃ£o autorizado']); exit;
        }
        $id_cliente = (int)$_SESSION['id_usuario'];

        $stmt = $conn->prepare(
            "SELECT p.*, COUNT(pi.id) as total_itens
             FROM pedidos p
             LEFT JOIN pedido_itens pi ON p.id = pi.id_pedido
             WHERE p.id_cliente = ?
               AND p.status NOT IN ('entregue', 'cancelado')
             GROUP BY p.id
             ORDER BY p.criado_em DESC"
        );
        $stmt->bind_param("i", $id_cliente);
        $stmt->execute();
        $pedidos = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();

        echo json_encode(['pedidos' => $pedidos]);
        break;

     

    case 'buscar_status_pedido':
        if (!isset($_SESSION['usuario'])) {
            echo json_encode(['erro' => 'NÃ£o autorizado']); exit;
        }
        $id = (int)($_GET['id'] ?? 0);
        if ($id <= 0) { echo json_encode(['pedido' => null]); exit; }

        if (($_SESSION['tipo'] ?? '') === 'dono') {
            $stmt = $conn->prepare(
                "SELECT id, status, conta_solicitada FROM pedidos WHERE id = ?"
            );
            $stmt->bind_param("i", $id);
        } else {
            $id_cliente = (int)($_SESSION['id_usuario'] ?? 0);
            $stmt = $conn->prepare(
                "SELECT id, status, conta_solicitada FROM pedidos WHERE id = ? AND id_cliente = ?"
            );
            $stmt->bind_param("ii", $id, $id_cliente);
        }
        $stmt->execute();
        $pedido = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        echo json_encode(['pedido' => $pedido]);
        break;

     

    case 'pedir_conta':
        if (!isset($_SESSION['usuario']) || $_SESSION['tipo'] != 'cliente') {
            echo json_encode(['erro' => 'NÃ£o autorizado']); exit;
        }
        verificar_csrf();
        $id_pedido  = (int)($_POST['id_pedido'] ?? 0);
        $id_cliente = (int)$_SESSION['id_usuario'];

        $stmt = $conn->prepare(
            "SELECT * FROM pedidos
             WHERE id = ? AND id_cliente = ? AND status = 'pronto'"
        );
        $stmt->bind_param("ii", $id_pedido, $id_cliente);
        $stmt->execute();
        $pedido = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (!$pedido) {
            echo json_encode(['erro' => 'Pedido nÃ£o encontrado ou nÃ£o estÃ¡ pronto']); exit;
        }

        $stmt = $conn->prepare(
            "UPDATE pedidos SET conta_solicitada = 1 WHERE id = ?"
        );
        $stmt->bind_param("i", $id_pedido);
        $stmt->execute();
        $stmt->close();

        echo json_encode(['sucesso' => true, 'mensagem' => 'Pagamento solicitado com sucesso!']);
        break;

     

    case 'contar_carrinho':
        if (!isset($_SESSION['usuario']) || $_SESSION['tipo'] != 'cliente') {
            echo json_encode(['count' => 0]); exit;
        }
        $id_cliente = (int)$_SESSION['id_usuario'];

        $stmt = $conn->prepare(
            "SELECT COUNT(*) as t FROM carrinho WHERE id_cliente = ?"
        );
        $stmt->bind_param("i", $id_cliente);
        $stmt->execute();
        $count = $stmt->get_result()->fetch_assoc()['t'];
        $stmt->close();

        echo json_encode(['count' => $count]);
        break;

     

    case 'atualizar_quantidade_carrinho':
        if (!isset($_SESSION['usuario']) || $_SESSION['tipo'] != 'cliente') {
            echo json_encode(['erro' => 'NÃ£o autorizado']); exit;
        }
        verificar_csrf();
        $id_cliente  = (int)$_SESSION['id_usuario'];
        $id_carrinho = (int)($_POST['id_carrinho'] ?? 0);
        $quantidade  = (int)($_POST['quantidade']  ?? 0);

        if ($quantidade > 0) {
            $stmt = $conn->prepare(
                "UPDATE carrinho SET quantidade = ?
                 WHERE id = ? AND id_cliente = ?"
            );
            $stmt->bind_param("iii", $quantidade, $id_carrinho, $id_cliente);
            $stmt->execute();
            $stmt->close();
        } else {
            $stmt = $conn->prepare(
                "DELETE FROM carrinho WHERE id = ? AND id_cliente = ?"
            );
            $stmt->bind_param("ii", $id_carrinho, $id_cliente);
            $stmt->execute();
            $stmt->close();
        }

        $stmt = $conn->prepare(
            "SELECT c.quantidade, p.preco
             FROM carrinho c
             JOIN produtos p ON c.id_produto = p.id
             WHERE c.id_cliente = ? AND p.disponivel = 1"
        );
        $stmt->bind_param("i", $id_cliente);
        $stmt->execute();
        $itens = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();

        $total = 0;
        foreach ($itens as $item) { $total += $item['preco'] * $item['quantidade']; }

        echo json_encode([
            'sucesso'         => true,
            'total'           => $total,
            'total_formatado' => formatar_preco($total),
            'count'           => count($itens),
        ]);
        break;

     

    case 'buscar_carrinho':
        if (!isset($_SESSION['usuario']) || $_SESSION['tipo'] != 'cliente') {
            echo json_encode(['erro' => 'NÃ£o autorizado']); exit;
        }
        $id_cliente = (int)$_SESSION['id_usuario'];

        $stmt = $conn->prepare(
            "SELECT c.*, p.nome, p.descricao, p.preco, p.imagem, p.categoria
             FROM carrinho c
             JOIN produtos p ON c.id_produto = p.id
             WHERE c.id_cliente = ? AND p.disponivel = 1"
        );
        $stmt->bind_param("i", $id_cliente);
        $stmt->execute();
        $itens = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();

        $total = 0;
        foreach ($itens as &$item) {
            $item['imagem'] = url_imagem_produto($item['imagem'] ?? null, $item['nome'] ?? 'Produto', $item['categoria'] ?? 'Sem categoria');
            $total += $item['preco'] * $item['quantidade'];
        }
        unset($item);

        echo json_encode([
            'itens'           => $itens,
            'total'           => $total,
            'total_formatado' => formatar_preco($total),
        ]);
        break;

     

     
    case 'alertas_estoque':
        if (!isset($_SESSION['usuario']) || $_SESSION['tipo'] != 'dono') {
            echo json_encode(['erro' => 'NÃ£o autorizado']); exit;
        }
        $zerados = $conn->query(
            "SELECT COUNT(*) as t FROM produtos WHERE estoque_atual = 0"
        )->fetch_assoc()['t'];

        $baixos = $conn->query(
            "SELECT COUNT(*) as t
             FROM produtos WHERE estoque_atual > 0 AND estoque_atual <= estoque_minimo"
        )->fetch_assoc()['t'];

        $criticos = $conn->query(
            "SELECT id, nome, estoque_atual, estoque_minimo
             FROM produtos WHERE estoque_atual <= estoque_minimo
             ORDER BY estoque_atual ASC LIMIT 10"
        )->fetch_all(MYSQLI_ASSOC);

        echo json_encode([
            'zerados'  => $zerados,
            'baixos'   => $baixos,
            'criticos' => $criticos,
        ]);
        break;

     

    default:
        echo json_encode(['erro' => 'AÃ§Ã£o nÃ£o reconhecida']);
}
