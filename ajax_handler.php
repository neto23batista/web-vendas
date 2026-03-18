<?php
session_start();
include "config.php";
include "helpers.php";

header('Content-Type: application/json');

$action = $_GET['action'] ?? $_POST['action'] ?? '';

switch ($action) {
    case 'buscar_pedidos_dono':
        if (!isset($_SESSION['usuario']) || $_SESSION['tipo'] != 'dono') { echo json_encode(['erro'=>'Não autorizado']); exit; }
        $pedidos = $conn->query("SELECT p.*, u.nome as cliente_nome, u.telefone, u.endereco FROM pedidos p JOIN usuarios u ON p.id_cliente = u.id ORDER BY p.criado_em DESC LIMIT 20")->fetch_all(MYSQLI_ASSOC);
        foreach ($pedidos as &$pedido) {
            $itens = $conn->query("SELECT pi.*, pr.nome as produto_nome FROM pedido_itens pi JOIN produtos pr ON pi.id_produto = pr.id WHERE pi.id_pedido = {$pedido['id']}")->fetch_all(MYSQLI_ASSOC);
            $pedido['itens'] = $itens; $pedido['total_itens'] = count($itens);
        }
        unset($pedido);
        echo json_encode(['pedidos' => $pedidos]);
        break;

    case 'buscar_stats_dono':
        if (!isset($_SESSION['usuario']) || $_SESSION['tipo'] != 'dono') { echo json_encode(['erro'=>'Não autorizado']); exit; }
        $stats = $conn->query("SELECT COUNT(DISTINCT id) as total_pedidos, SUM(total) as faturamento_total, SUM(CASE WHEN status='pendente' THEN 1 ELSE 0 END) as pedidos_pendentes FROM pedidos")->fetch_assoc();
        $total_produtos = $conn->query("SELECT COUNT(*) as t FROM produtos WHERE disponivel=1")->fetch_assoc()['t'];
        $total_clientes = $conn->query("SELECT COUNT(*) as t FROM usuarios WHERE tipo='cliente'")->fetch_assoc()['t'];
        echo json_encode(['stats' => $stats, 'total_produtos' => $total_produtos, 'total_clientes' => $total_clientes]);
        break;

    case 'atualizar_status':
        if (!isset($_SESSION['usuario']) || $_SESSION['tipo'] != 'dono') {
            echo json_encode(['erro' => 'Não autorizado']); exit;
        }
        $id_pedido = (int) $_POST['id_pedido'];
        $status    = $conn->real_escape_string($_POST['status']);

        // Buscar status atual para detectar transição → entregue
        $pedido_atual = $conn->query("SELECT status FROM pedidos WHERE id=$id_pedido")->fetch_assoc();

        $conn->query("UPDATE pedidos SET status='$status' WHERE id=$id_pedido");

        // Baixar estoque automaticamente ao marcar como entregue/retirado
        if ($status === 'entregue' && $pedido_atual && $pedido_atual['status'] !== 'entregue') {
            $itens_pedido = $conn->query("SELECT id_produto, quantidade FROM pedido_itens WHERE id_pedido=$id_pedido")->fetch_all(MYSQLI_ASSOC);
            $id_admin = $_SESSION['id_usuario'] ?? null;

            foreach ($itens_pedido as $it) {
                $id_prod = (int)$it['id_produto'];
                $qtd     = (int)$it['quantidade'];
                $prod    = $conn->query("SELECT estoque_atual FROM produtos WHERE id=$id_prod")->fetch_assoc();

                if ($prod) {
                    $antes  = (int)$prod['estoque_atual'];
                    $depois = max(0, $antes - $qtd);
                    $conn->query("UPDATE produtos SET estoque_atual=$depois WHERE id=$id_prod");
                    if ($depois === 0) $conn->query("UPDATE produtos SET disponivel=0 WHERE id=$id_prod");

                    // Registrar movimentação
                    $motivo = "Baixa automática – Pedido #$id_pedido";
                    $tipo_baixa = 'saida';
                    $stmt = $conn->prepare("INSERT INTO movimentacoes_estoque (id_produto,tipo,quantidade,estoque_anterior,estoque_novo,motivo,id_pedido,id_usuario) VALUES (?,?,?,?,?,?,?,?)");
                    $stmt->bind_param("isiissii", $id_prod, $tipo_baixa, $qtd, $antes, $depois, $motivo, $id_pedido, $id_admin);
                    $stmt->execute();
                }
            }
        }

        echo json_encode(['sucesso' => true, 'mensagem' => 'Status atualizado!']);
        break;

    case 'buscar_pedidos_cliente':
        if (!isset($_SESSION['usuario']) || $_SESSION['tipo'] != 'cliente') { echo json_encode(['erro'=>'Não autorizado']); exit; }
        $id_cliente = $_SESSION['id_usuario'];
        $pedidos = $conn->query("SELECT p.*, COUNT(pi.id) as total_itens FROM pedidos p LEFT JOIN pedido_itens pi ON p.id = pi.id_pedido WHERE p.id_cliente = $id_cliente AND p.status NOT IN ('entregue', 'cancelado') GROUP BY p.id ORDER BY p.criado_em DESC")->fetch_all(MYSQLI_ASSOC);
        echo json_encode(['pedidos' => $pedidos]);
        break;

    case 'buscar_status_pedido':
        $id = (int)$_GET['id'];
        $pedido = $conn->query("SELECT id, status, conta_solicitada FROM pedidos WHERE id=$id")->fetch_assoc();
        echo json_encode(['pedido' => $pedido]);
        break;

    case 'pedir_conta':
        if (!isset($_SESSION['usuario']) || $_SESSION['tipo'] != 'cliente') { echo json_encode(['erro'=>'Não autorizado']); exit; }
        $id_pedido = (int) $_POST['id_pedido'];
        $id_cliente = $_SESSION['id_usuario'];
        $pedido = $conn->query("SELECT * FROM pedidos WHERE id=$id_pedido AND id_cliente=$id_cliente AND status='pronto'")->fetch_assoc();
        if (!$pedido) { echo json_encode(['erro'=>'Pedido não encontrado ou não está pronto']); exit; }
        $conn->query("UPDATE pedidos SET conta_solicitada=1 WHERE id=$id_pedido");
        echo json_encode(['sucesso' => true, 'mensagem' => 'Pagamento solicitado com sucesso!']);
        break;

    case 'contar_carrinho':
        if (!isset($_SESSION['usuario']) || $_SESSION['tipo'] != 'cliente') { echo json_encode(['count'=>0]); exit; }
        $id_cliente = $_SESSION['id_usuario'];
        $count = $conn->query("SELECT COUNT(*) as t FROM carrinho WHERE id_cliente=$id_cliente")->fetch_assoc()['t'];
        echo json_encode(['count' => $count]);
        break;

    case 'atualizar_quantidade_carrinho':
        if (!isset($_SESSION['usuario']) || $_SESSION['tipo'] != 'cliente') { echo json_encode(['erro'=>'Não autorizado']); exit; }
        $id_cliente  = $_SESSION['id_usuario'];
        $id_carrinho = (int) $_POST['id_carrinho'];
        $quantidade  = (int) $_POST['quantidade'];
        if ($quantidade > 0) { $conn->query("UPDATE carrinho SET quantidade=$quantidade WHERE id=$id_carrinho AND id_cliente=$id_cliente"); }
        else { $conn->query("DELETE FROM carrinho WHERE id=$id_carrinho AND id_cliente=$id_cliente"); }
        $itens = $conn->query("SELECT c.quantidade, p.preco FROM carrinho c JOIN produtos p ON c.id_produto = p.id WHERE c.id_cliente = $id_cliente AND p.disponivel = 1")->fetch_all(MYSQLI_ASSOC);
        $total = 0;
        foreach ($itens as $item) { $total += $item['preco'] * $item['quantidade']; }
        echo json_encode(['sucesso'=>true,'total'=>$total,'total_formatado'=>formatar_preco($total),'count'=>count($itens)]);
        break;

    case 'buscar_carrinho':
        if (!isset($_SESSION['usuario']) || $_SESSION['tipo'] != 'cliente') { echo json_encode(['erro'=>'Não autorizado']); exit; }
        $id_cliente = $_SESSION['id_usuario'];
        $itens = $conn->query("SELECT c.*, p.nome, p.descricao, p.preco, p.imagem FROM carrinho c JOIN produtos p ON c.id_produto = p.id WHERE c.id_cliente = $id_cliente AND p.disponivel = 1")->fetch_all(MYSQLI_ASSOC);
        $total = 0;
        foreach ($itens as $item) { $total += $item['preco'] * $item['quantidade']; }
        echo json_encode(['itens'=>$itens,'total'=>$total,'total_formatado'=>formatar_preco($total)]);
        break;

    default:
        echo json_encode(['erro' => 'Ação não reconhecida']);
}

// ---- ESTOQUE: alerta para o painel admin ----
// action=alertas_estoque
if ($action === 'alertas_estoque') {
    if (!isset($_SESSION['usuario']) || $_SESSION['tipo'] != 'dono') {
        echo json_encode(['erro' => 'Não autorizado']); exit;
    }
    $zerados = $conn->query("SELECT COUNT(*) as t FROM produtos WHERE estoque_atual = 0")->fetch_assoc()['t'];
    $baixos  = $conn->query("SELECT COUNT(*) as t FROM produtos WHERE estoque_atual > 0 AND estoque_atual <= estoque_minimo")->fetch_assoc()['t'];
    $criticos = $conn->query("SELECT id, nome, estoque_atual, estoque_minimo FROM produtos WHERE estoque_atual <= estoque_minimo ORDER BY estoque_atual ASC LIMIT 10")->fetch_all(MYSQLI_ASSOC);
    echo json_encode(['zerados' => $zerados, 'baixos' => $baixos, 'criticos' => $criticos]);
    exit;
}
?>
