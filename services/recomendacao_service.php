<?php
if (!defined('FARMAVIDA_ROOT')) {
    define('FARMAVIDA_ROOT', dirname(__DIR__));
}

/**
 * Sugere produtos com base no histórico do cliente (categorias mais compradas),
 * excluindo itens já no carrinho.
 */
function recomendar_por_historico(mysqli $conn, int $idCliente, array $idsCarrinho, int $limit = 6): array {
    $placeholders = implode(',', array_fill(0, count($idsCarrinho), '?'));
    $typesIds = str_repeat('i', count($idsCarrinho));

    $sqlCarrinho = count($idsCarrinho) ? "AND p.id NOT IN ($placeholders)" : '';
    $sql = "
        SELECT p.*
        FROM pedido_itens pi
        JOIN pedidos pd ON pd.id = pi.id_pedido
        JOIN produtos p ON p.id = pi.id_produto
        WHERE pd.id_cliente = ?
          AND p.disponivel = 1
          $sqlCarrinho
        GROUP BY p.id
        ORDER BY SUM(pi.quantidade) DESC, p.preco DESC
        LIMIT ?
    ";

    $stmt = $conn->prepare($sql);
    $types = 'i' . $typesIds . 'i';
    $params = [$idCliente, ...$idsCarrinho, $limit];
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $res = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
    return $res;
}

/**
 * Sugere produtos da mesma categoria de itens do carrinho.
 */
function recomendar_por_categoria(mysqli $conn, array $categorias, array $idsCarrinho, int $limit = 6): array {
    if (empty($categorias)) {
        return [];
    }
    $cats = implode(',', array_fill(0, count($categorias), '?'));
    $placeholders = implode(',', array_fill(0, count($idsCarrinho), '?'));
    $sqlNotIn = count($idsCarrinho) ? "AND id NOT IN ($placeholders)" : '';

    $sql = "
        SELECT *
        FROM produtos
        WHERE categoria IN ($cats)
          AND disponivel = 1
          $sqlNotIn
        ORDER BY preco DESC
        LIMIT ?
    ";
    $stmt = $conn->prepare($sql);
    $types = str_repeat('s', count($categorias)) . str_repeat('i', count($idsCarrinho)) . 'i';
    $params = [...$categorias, ...$idsCarrinho, $limit];
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $res = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
    return $res;
}

/**
 * Kits pré-montados (estáticos, sugeridos por tema).
 */
function kits_pre_montados(): array {
    return [
        [
            'slug' => 'kit-gestante',
            'titulo' => 'Kit Gestante',
            'descricao' => 'Vitaminas, hidratante anti-estrias e termômetro digital.',
            'tags' => ['gestante', 'suplemento', 'higiene']
        ],
        [
            'slug' => 'kit-atleta',
            'titulo' => 'Kit Atleta',
            'descricao' => 'Recuperação muscular, bandagem elástica, isotônico em pó.',
            'tags' => ['atleta', 'performance']
        ],
        [
            'slug' => 'kit-infantil',
            'titulo' => 'Kit Infantil',
            'descricao' => 'Antitérmico infantil, curativos personagens, soro nasal.',
            'tags' => ['infantil', 'primeiros-socorros']
        ],
        [
            'slug' => 'kit-idoso',
            'titulo' => 'Kit Idoso',
            'descricao' => 'Polivitamínico 50+, creme para articulação, organizador de comprimidos.',
            'tags' => ['idoso', 'bem-estar']
        ],
    ];
}

/**
 * Sugestão simples de genéricos: produtos da categoria 'Genéricos' que não estão no carrinho.
 */
function recomendar_genericos(mysqli $conn, array $idsCarrinho, int $limit = 4): array {
    $placeholders = implode(',', array_fill(0, count($idsCarrinho), '?'));
    $sqlNotIn = count($idsCarrinho) ? "AND id NOT IN ($placeholders)" : '';
    $sql = "SELECT * FROM produtos WHERE categoria = 'Genéricos' AND disponivel = 1 $sqlNotIn LIMIT ?";
    $stmt = $conn->prepare($sql);
    $types = str_repeat('i', count($idsCarrinho)) . 'i';
    $params = [...$idsCarrinho, $limit];
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $res = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
    return $res;
}
