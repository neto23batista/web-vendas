<?php
include "config.php";

define('EXECUTAR_AUTOMATICAMENTE', true);
$deve_executar = EXECUTAR_AUTOMATICAMENTE || isset($_GET['executar']);
if (!$deve_executar) die("Use ?executar=1 para forçar a limpeza.");

$total_remover = $conn->query("SELECT COUNT(*) as total FROM pedidos WHERE status IN ('entregue', 'cancelado')")->fetch_assoc()['total'];

if ($total_remover == 0) { echo "✅ Nenhum pedido finalizado para remover."; exit; }

$ids_remover = [];
$resultado_ids = $conn->query("SELECT id FROM pedidos WHERE status IN ('entregue', 'cancelado')");
while ($row = $resultado_ids->fetch_assoc()) { $ids_remover[] = $row['id']; }
$ids_string = implode(',', $ids_remover);

$conn->begin_transaction();
try {
    $conn->query("DELETE FROM pedido_itens WHERE id_pedido IN ($ids_string)");
    $itens_removidos = $conn->affected_rows;
    $conn->query("DELETE FROM pedidos WHERE id IN ($ids_string)");
    $pedidos_removidos = $conn->affected_rows;
    $conn->commit();
    echo "✅ Limpeza: $pedidos_removidos pedidos e $itens_removidos itens removidos.";
} catch (Exception $e) {
    $conn->rollback();
    echo "❌ Erro: " . $e->getMessage();
}
$conn->close();
?>
