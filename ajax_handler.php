<?php
session_start();
include "config.php";
include "helpers.php";

header('Content-Type: application/json');

$action = $_GET['action'] ?? $_POST['action'] ?? '';

// Statuses permitidos — whitelist explícita
const STATUS_VALIDOS = ['pendente', 'preparando', 'pronto', 'entregue', 'cancelado'];

switch ($action) {

    case 'buscar_pedidos_dono':
        if (!isset($_SESSION['usuario']) || $_SESSION['tipo'] != 'dono') {
            echo json_encode(['erro' => 'Não autorizado']); exit;
        }
        // Sem input do usuário nesta query — apenas sessão validada
        $pedidos = $conn->query(
            "SELECT p.*, u.nome as cliente_nome, u.telefone, u.endereco
             FROM pedidos p
             JOIN usuarios u ON p.id_cliente = u.id
             ORDER BY p.criado_em DESC LIMIT 20"
        )->fetch_all(MYSQLI_ASSOC);

        foreach ($pedidos as &$pedido) {
            $stmt = $conn->prepare(
                "SELECT pi.*, pr.nome as produto_nome
                 FROM pedido_itens pi
                 JOIN produtos pr ON pi.id_produto = pr.id
                 WHERE pi.id_pedido = ?"
            );
            $stmt->bind_param("i", $pedido['id']);
            $stmt->execute();
            $itens = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
            $pedido['itens'] = $itens;
            $pedido['total_itens'] = count($itens);
            $stmt->close();
        }
        unset($pedido);
        echo json_encode(['pedidos' => $pedidos]);
        break;

    // -----------------------------------------------------------------

    case 'buscar_stats_dono':
        if (!isset($_SESSION['usuario']) || $_SESSION['tipo'] != 'dono') {
            echo json_encode(['erro' => 'Não autorizado']); exit;
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

    // -----------------------------------------------------------------

    case 'atualizar_status':
        if (!isset($_SESSION['usuario']) || $_SESSION['tipo'] != 'dono') {
            echo json_encode(['erro' => 'Não autorizado']); exit;
        }

        $id_pedido = (int)($_POST['id_pedido'] ?? 0);
        $status    = $_POST['status'] ?? '';

        // ✅ Whitelist — rejeita qualquer valor fora dos permitidos
        if ($id_pedido <= 0 || !in_array($status, STATUS_VALIDOS, true)) {
            echo json_encode(['erro' => 'Dados inválidos']); exit;
        }

        // Busca status atual para detectar transição → entregue
        $stmt = $conn->prepare("SELECT status FROM pedidos WHERE id = ?");
        $stmt->bind_param("i", $id_pedido);
        $stmt->execute();
        $pedido_atual = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        // ✅ Prepared statement para o UPDATE
        $stmt = $conn->prepare("UPDATE pedidos SET status = ? WHERE id = ?");
        $stmt->bind_param("si", $status, $id_pedido);
        $stmt->execute();
        $stmt->close();

        // Baixa de estoque ao marcar como entregue
        if ($status === 'entregue' && $pedido_atual && $pedido_atual['status'] !== 'entregue') {
            $stmt_itens = $conn->prepare(
                "SELECT id_produto, quantidade FROM pedido_itens WHERE id_pedido = ?"
            );
            $stmt_itens->bind_param("i", $id_pedido);
            $stmt_itens->execute();
            $itens_pedido = $stmt_itens->get_result()->fetch_all(MYSQLI_ASSOC);
            $stmt_itens->close();

            $id_admin = $_SESSION['id_usuario'] ?? null;

            foreach ($itens_pedido as $it) {
                $id_prod = (int)$it['id_produto'];
                $qtd     = (int)$it['quantidade'];

                $stmt_prod = $conn->prepare(
                    "SELECT estoque_atual FROM produtos WHERE id = ?"
                );
                $stmt_prod->bind_param("i", $id_prod);
                $stmt_prod->execute();
                $prod = $stmt_prod->get_result()->fetch_assoc();
                $stmt_prod->close();

                if ($prod) {
                    $antes  = (int)$prod['estoque_atual'];
                    $depois = max(0, $antes - $qtd);

                    $stmt_up = $conn->prepare(
                        "UPDATE produtos SET estoque_atual = ? WHERE id = ?"
                    );
                    $stmt_up->bind_param("ii", $depois, $id_prod);
                    $stmt_up->execute();
                    $stmt_up->close();

                    if ($depois === 0) {
                        $stmt_dis = $conn->prepare(
                            "UPDATE produtos SET disponivel = 0 WHERE id = ?"
                        );
                        $stmt_dis->bind_param("i", $id_prod);
                        $stmt_dis->execute();
                        $stmt_dis->close();
                    }

                    // Registra movimentação
                    $motivo    = "Baixa automática – Pedido #$id_pedido";
                    $tipo_baixa = 'saida';
                    $stmt_mov = $conn->prepare(
                        "INSERT INTO movimentacoes_estoque
                         (id_produto, tipo, quantidade, estoque_anterior, estoque_novo, motivo, id_pedido, id_usuario)
                         VALUES (?, ?, ?, ?, ?, ?, ?, ?)"
                    );
                    $stmt_mov->bind_param(
                        "isiissii",
                        $id_prod, $tipo_baixa, $qtd,
                        $antes, $depois, $motivo,
                        $id_pedido, $id_admin
                    );
                    $stmt_mov->execute();
                    $stmt_mov->close();
                }
            }
        }

        echo json_encode(['sucesso' => true, 'mensagem' => 'Status atualizado!']);
        break;

    // -----------------------------------------------------------------

    case 'buscar_pedidos_cliente':
        if (!isset($_SESSION['usuario']) || $_SESSION['tipo'] != 'cliente') {
            echo json_encode(['erro' => 'Não autorizado']); exit;
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

    // -----------------------------------------------------------------

    case 'buscar_status_pedido':
        $id = (int)($_GET['id'] ?? 0);
        if ($id <= 0) { echo json_encode(['pedido' => null]); exit; }

        $stmt = $conn->prepare(
            "SELECT id, status, conta_solicitada FROM pedidos WHERE id = ?"
        );
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $pedido = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        echo json_encode(['pedido' => $pedido]);
        break;

    // -----------------------------------------------------------------

    case 'pedir_conta':
        if (!isset($_SESSION['usuario']) || $_SESSION['tipo'] != 'cliente') {
            echo json_encode(['erro' => 'Não autorizado']); exit;
        }
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
            echo json_encode(['erro' => 'Pedido não encontrado ou não está pronto']); exit;
        }

        $stmt = $conn->prepare(
            "UPDATE pedidos SET conta_solicitada = 1 WHERE id = ?"
        );
        $stmt->bind_param("i", $id_pedido);
        $stmt->execute();
        $stmt->close();

        echo json_encode(['sucesso' => true, 'mensagem' => 'Pagamento solicitado com sucesso!']);
        break;

    // -----------------------------------------------------------------

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

    // -----------------------------------------------------------------

    case 'atualizar_quantidade_carrinho':
        if (!isset($_SESSION['usuario']) || $_SESSION['tipo'] != 'cliente') {
            echo json_encode(['erro' => 'Não autorizado']); exit;
        }
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

    // -----------------------------------------------------------------

    case 'buscar_carrinho':
        if (!isset($_SESSION['usuario']) || $_SESSION['tipo'] != 'cliente') {
            echo json_encode(['erro' => 'Não autorizado']); exit;
        }
        $id_cliente = (int)$_SESSION['id_usuario'];

        $stmt = $conn->prepare(
            "SELECT c.*, p.nome, p.descricao, p.preco, p.imagem
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
            'itens'           => $itens,
            'total'           => $total,
            'total_formatado' => formatar_preco($total),
        ]);
        break;

    // -----------------------------------------------------------------

    // ✅ alertas_estoque DENTRO do switch — corrige o bug de saída JSON dupla
    case 'alertas_estoque':
        if (!isset($_SESSION['usuario']) || $_SESSION['tipo'] != 'dono') {
            echo json_encode(['erro' => 'Não autorizado']); exit;
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

    // -----------------------------------------------------------------

    default:
        echo json_encode(['erro' => 'Ação não reconhecida']);
}
