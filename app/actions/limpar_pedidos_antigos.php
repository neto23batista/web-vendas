<?php
require_once FARMAVIDA_ROOT . '/app/core/bootstrap.php';
require_once FARMAVIDA_ROOT . '/app/core/config.php';
require_once FARMAVIDA_ROOT . '/app/core/helpers.php';

 
verificar_login('dono');

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_POST['executar'])) {
    http_response_code(405);
    die("Método não permitido.");
}
verificar_csrf();

 
$resultado = $conn->query(
    "SELECT COUNT(*) as total FROM pedidos WHERE status IN ('entregue', 'cancelado')"
);
$total_remover = (int)$resultado->fetch_assoc()['total'];

if ($total_remover === 0) {
    redirecionar('painel_dono.php', 'Nenhum pedido finalizado para remover.');
}

 
$ids_remover = [];
$res_ids = $conn->query(
    "SELECT id FROM pedidos WHERE status IN ('entregue', 'cancelado')"
);
while ($row = $res_ids->fetch_assoc()) {
    $ids_remover[] = (int)$row['id'];    
}

if (empty($ids_remover)) {
    redirecionar('painel_dono.php', 'Nenhum pedido encontrado.');
}

$ids_string = implode(',', $ids_remover);  
$placeholders = implode(',', array_fill(0, count($ids_remover), '?'));
$types = str_repeat('i', count($ids_remover));

$conn->begin_transaction();
try {
    $stmt = $conn->prepare("DELETE FROM pedido_itens WHERE id_pedido IN ($placeholders)");
    $stmt->bind_param($types, ...$ids_remover);
    $stmt->execute();
    $itens_removidos = $stmt->affected_rows;
    $stmt->close();

    $stmt = $conn->prepare("DELETE FROM pedidos WHERE id IN ($placeholders)");
    $stmt->bind_param($types, ...$ids_remover);
    $stmt->execute();
    $pedidos_removidos = $stmt->affected_rows;
    $stmt->close();

    $conn->commit();

    $logsDir = FARMAVIDA_ROOT . '/logs';
    @mkdir($logsDir, 0755, true);
    $log = date('Y-m-d H:i:s') . " | Admin: {$_SESSION['usuario']} | "
         . "Limpeza: $pedidos_removidos pedidos, $itens_removidos itens\n";
    file_put_contents($logsDir . '/limpeza.log', $log, FILE_APPEND);

    redirecionar('painel_dono.php', "Limpeza concluída: $pedidos_removidos pedido(s) e $itens_removidos item(ns) removidos.");
} catch (Exception $e) {
    $conn->rollback();
    error_log('Erro na limpeza de pedidos: ' . $e->getMessage());
    redirecionar('painel_dono.php', 'Erro ao limpar pedidos finalizados. Verifique os logs do servidor.', 'erro');
}

$conn->close();
