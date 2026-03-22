<?php
session_start();
include "config.php";
include "helpers.php";

// Só o dono pode executar a limpeza
verificar_login('dono');

// Confirmar intenção explícita (via GET ou constante)
define('EXECUTAR_AUTOMATICAMENTE', false);
$deve_executar = EXECUTAR_AUTOMATICAMENTE || isset($_GET['executar']);
if (!$deve_executar) {
    die("Use ?executar=1 para confirmar a limpeza de pedidos finalizados.");
}

// Contar pedidos a remover
$resultado = $conn->query(
    "SELECT COUNT(*) as total FROM pedidos WHERE status IN ('entregue', 'cancelado')"
);
$total_remover = (int)$resultado->fetch_assoc()['total'];

if ($total_remover === 0) {
    echo "✅ Nenhum pedido finalizado para remover.";
    exit;
}

// Buscar IDs (vindos do banco, sem input do usuário — seguro)
$ids_remover = [];
$res_ids = $conn->query(
    "SELECT id FROM pedidos WHERE status IN ('entregue', 'cancelado')"
);
while ($row = $res_ids->fetch_assoc()) {
    $ids_remover[] = (int)$row['id'];   // cast para garantir que são inteiros
}

if (empty($ids_remover)) {
    echo "✅ Nenhum pedido encontrado.";
    exit;
}

$ids_string = implode(',', $ids_remover); // seguro: apenas inteiros

$conn->begin_transaction();
try {
    $conn->query("DELETE FROM pedido_itens WHERE id_pedido IN ($ids_string)");
    $itens_removidos = $conn->affected_rows;

    $conn->query("DELETE FROM pedidos WHERE id IN ($ids_string)");
    $pedidos_removidos = $conn->affected_rows;

    $conn->commit();

    // Log da operação
    @mkdir('logs', 0755, true);
    $log = date('Y-m-d H:i:s') . " | Admin: {$_SESSION['usuario']} | "
         . "Limpeza: $pedidos_removidos pedidos, $itens_removidos itens\n";
    file_put_contents('logs/limpeza.log', $log, FILE_APPEND);

    echo "✅ Limpeza concluída: $pedidos_removidos pedido(s) e $itens_removidos item(ns) removidos.";
} catch (Exception $e) {
    $conn->rollback();
    error_log('Erro na limpeza de pedidos: ' . $e->getMessage());
    echo "❌ Erro ao limpar. Verifique os logs do servidor.";
}

$conn->close();
