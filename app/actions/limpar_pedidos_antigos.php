<?php
require_once FARMAVIDA_ROOT . '/app/core/bootstrap.php';
require_once FARMAVIDA_ROOT . '/app/core/config.php';
require_once FARMAVIDA_ROOT . '/app/core/helpers.php';

// SÃ³ o dono pode executar a limpeza
verificar_login('dono');

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_POST['executar'])) {
    http_response_code(405);
    die("MÃ©todo nÃ£o permitido.");
}
verificar_csrf();

// Contar pedidos a remover
$resultado = $conn->query(
    "SELECT COUNT(*) as total FROM pedidos WHERE status IN ('entregue', 'cancelado')"
);
$total_remover = (int)$resultado->fetch_assoc()['total'];

if ($total_remover === 0) {
    redirecionar('painel_dono.php', 'Nenhum pedido finalizado para remover.');
}

// Buscar IDs (vindos do banco, sem input do usuÃ¡rio â€” seguro)
$ids_remover = [];
$res_ids = $conn->query(
    "SELECT id FROM pedidos WHERE status IN ('entregue', 'cancelado')"
);
while ($row = $res_ids->fetch_assoc()) {
    $ids_remover[] = (int)$row['id'];   // cast para garantir que sÃ£o inteiros
}

if (empty($ids_remover)) {
    redirecionar('painel_dono.php', 'Nenhum pedido encontrado.');
}

$ids_string = implode(',', $ids_remover); // seguro: apenas inteiros

$conn->begin_transaction();
try {
    $conn->query("DELETE FROM pedido_itens WHERE id_pedido IN ($ids_string)");
    $itens_removidos = $conn->affected_rows;

    $conn->query("DELETE FROM pedidos WHERE id IN ($ids_string)");
    $pedidos_removidos = $conn->affected_rows;

    $conn->commit();

    // Log da operaÃ§Ã£o
    @mkdir('logs', 0755, true);
    $log = date('Y-m-d H:i:s') . " | Admin: {$_SESSION['usuario']} | "
         . "Limpeza: $pedidos_removidos pedidos, $itens_removidos itens\n";
    file_put_contents('logs/limpeza.log', $log, FILE_APPEND);

    redirecionar('painel_dono.php', "Limpeza concluÃ­da: $pedidos_removidos pedido(s) e $itens_removidos item(ns) removidos.");
} catch (Exception $e) {
    $conn->rollback();
    error_log('Erro na limpeza de pedidos: ' . $e->getMessage());
    redirecionar('painel_dono.php', 'Erro ao limpar pedidos finalizados. Verifique os logs do servidor.', 'erro');
}

$conn->close();
