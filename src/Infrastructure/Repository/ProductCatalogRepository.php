<?php

namespace FarmaVida\Infrastructure\Repository;

use FarmaVida\Core\Database\Database;
use mysqli;

final class ProductCatalogRepository
{
    public function __construct(private readonly Database $database)
    {
    }

    public function categories(): array
    {
        $result = $this->connection()->query(
            "SELECT DISTINCT categoria
             FROM produtos
             WHERE disponivel = 1 AND categoria IS NOT NULL AND categoria <> ''
             ORDER BY categoria"
        );

        return $result->fetch_all(MYSQLI_ASSOC);
    }

    public function availableProducts(?string $category = null, ?string $search = null): array
    {
        $sql = "SELECT id, nome, descricao, preco, categoria, imagem, estoque_atual, estoque_minimo
                FROM produtos
                WHERE disponivel = 1";
        $types = '';
        $params = [];

        if ($category !== null && $category !== '') {
            $sql .= " AND categoria = ?";
            $types .= 's';
            $params[] = $category;
        }

        if ($search !== null && $search !== '') {
            $sql .= " AND (nome LIKE ? OR descricao LIKE ? OR categoria LIKE ?)";
            $types .= 'sss';
            $term = '%' . $search . '%';
            $params[] = $term;
            $params[] = $term;
            $params[] = $term;
        }

        $sql .= " ORDER BY categoria, nome";

        if ($types === '') {
            return $this->connection()->query($sql)->fetch_all(MYSQLI_ASSOC);
        }

        $stmt = $this->connection()->prepare($sql);
        $stmt->bind_param($types, ...$params);
        $stmt->execute();
        $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();

        return $rows;
    }

    public function topProducts(int $limit = 6): array
    {
        $stmt = $this->connection()->prepare(
            "SELECT pr.id, pr.nome, pr.preco, pr.imagem, pr.categoria, pr.estoque_atual, pr.estoque_minimo,
                    COALESCE(SUM(pi.quantidade), 0) AS total_vendido
             FROM produtos pr
             LEFT JOIN pedido_itens pi ON pi.id_produto = pr.id
             LEFT JOIN pedidos p ON p.id = pi.id_pedido AND p.status <> 'cancelado'
             WHERE pr.disponivel = 1
             GROUP BY pr.id, pr.nome, pr.preco, pr.imagem, pr.categoria, pr.estoque_atual, pr.estoque_minimo
             ORDER BY total_vendido DESC, pr.nome ASC
             LIMIT ?"
        );
        $stmt->bind_param('i', $limit);
        $stmt->execute();
        $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();

        return $rows;
    }

    public function cartCount(int $userId): int
    {
        $stmt = $this->connection()->prepare("SELECT COUNT(*) AS total FROM carrinho WHERE id_cliente = ?");
        $stmt->bind_param('i', $userId);
        $stmt->execute();
        $count = (int)($stmt->get_result()->fetch_assoc()['total'] ?? 0);
        $stmt->close();
        return $count;
    }

    public function cartPreview(int $userId, int $limit = 5): array
    {
        $stmt = $this->connection()->prepare(
            "SELECT c.id, c.quantidade, p.id AS produto_id, p.nome, p.preco, p.imagem, p.categoria
             FROM carrinho c
             JOIN produtos p ON p.id = c.id_produto
             WHERE c.id_cliente = ? AND p.disponivel = 1
             ORDER BY c.adicionado_em DESC
             LIMIT ?"
        );
        $stmt->bind_param('ii', $userId, $limit);
        $stmt->execute();
        $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
        return $rows;
    }

    private function connection(): mysqli
    {
        return $this->database->connection();
    }
}
