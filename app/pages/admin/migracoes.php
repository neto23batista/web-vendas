<?php
require_once FARMAVIDA_ROOT . '/app/core/bootstrap.php';
require_once FARMAVIDA_ROOT . '/app/core/config.php';
require_once FARMAVIDA_ROOT . '/app/core/helpers.php';
require_once FARMAVIDA_ROOT . '/services/schema_service.php';

verificar_login('dono');

function migracao_componentes_meta(): array {
    return [
        'auth' => [
            'titulo' => 'Acesso e Senha',
            'descricao' => 'Recuperacao de senha, tokens e estrutura de autenticacao.',
            'icone' => 'fa-key',
        ],
        'erp' => [
            'titulo' => 'ERP e Integracoes',
            'descricao' => 'API keys, webhooks e tabelas de integracao externa.',
            'icone' => 'fa-plug',
        ],
        'estoque' => [
            'titulo' => 'Estoque',
            'descricao' => 'Colunas de controle, movimentacoes e apoio operacional.',
            'icone' => 'fa-boxes-stacked',
        ],
        'nfe' => [
            'titulo' => 'NF-e',
            'descricao' => 'Campos fiscais para pedidos, produtos e clientes.',
            'icone' => 'fa-file-invoice',
        ],
        'pagamentos' => [
            'titulo' => 'Pagamentos',
            'descricao' => 'Campos do Mercado Pago, status e indices de consulta.',
            'icone' => 'fa-credit-card',
        ],
    ];
}

function migracao_descrever_item(array $item): string {
    $tipo = $item['type'] ?? '';
    $tabela = $item['table'] ?? '';
    $coluna = $item['column'] ?? '';
    $indice = $item['index'] ?? '';

    if ($tipo === 'table') {
        return "Criar tabela `$tabela`.";
    }

    if ($tipo === 'column') {
        return "Adicionar coluna `$coluna` na tabela `$tabela`.";
    }

    if ($tipo === 'index') {
        return "Criar indice `$indice` na tabela `$tabela`.";
    }

    return 'Aplicar ajuste estrutural no banco.';
}

$resultadoMigracao = null;
$componentesSelecionados = [];
$componentes = schema_componentes_disponiveis();
$metaComponentes = migracao_componentes_meta();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verificar_csrf();

    $componentesSolicitados = $_POST['componentes'] ?? [];
    $componentesSelecionados = array_values(array_intersect($componentes, $componentesSolicitados));
    $resultadoMigracao = schema_executar_migracoes($conn, $componentesSelecionados ?: null);

    if (empty($resultadoMigracao['falhas'])) {
        $quantidadeExecutada = count($resultadoMigracao['executadas']);
        $mensagem = $quantidadeExecutada === 0
            ? 'Banco ja atualizado. Nenhuma migracao pendente.'
            : "Migracoes executadas com sucesso: $quantidadeExecutada alteracao(oes) aplicada(s).";
        redirecionar('migracoes.php', $mensagem);
    }
}

$pendentes = schema_listar_migracoes_pendentes($conn);
$pendentesPorComponente = [];
foreach ($pendentes as $pendente) {
    $pendentesPorComponente[$pendente['component']][] = $pendente;
}

$totalPendentes = count($pendentes);
$componentesComPendencia = count(array_filter(
    $componentes,
    static fn(string $componente): bool => !empty($pendentesPorComponente[$componente])
));
$statusGeral = $totalPendentes === 0 ? 'Banco atualizado' : 'Acao necessaria';
$descricaoGeral = $totalPendentes === 0
    ? 'Todas as estruturas previstas para este sistema ja existem no banco.'
    : 'Existem alteracoes de banco pendentes. Execute-as antes de usar os modulos afetados.';
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Migracoes - FarmaVida</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="style.css?v=1774207549">
    <style>
        .mig-layout { display: grid; gap: 20px; }
        .mig-hero {
            display: grid;
            gap: 16px;
            grid-template-columns: minmax(0, 1.8fr) minmax(280px, 1fr);
            align-items: stretch;
        }
        .mig-hero-main {
            background:
                radial-gradient(circle at top right, rgba(0, 229, 160, .14), transparent 35%),
                linear-gradient(180deg, rgba(255, 255, 255, .04), rgba(255, 255, 255, .02));
            border: 1px solid var(--border);
            border-radius: var(--radius-lg);
            padding: 28px;
            box-shadow: var(--shadow-md);
        }
        .mig-eyebrow {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 7px 12px;
            border-radius: var(--radius-full);
            background: rgba(0, 229, 160, .12);
            color: var(--primary);
            font-size: 12px;
            font-weight: 700;
            letter-spacing: .04em;
            text-transform: uppercase;
        }
        .mig-title {
            margin-top: 14px;
            font-family: 'Bricolage Grotesque', sans-serif;
            font-size: clamp(30px, 5vw, 42px);
            line-height: 1.05;
            letter-spacing: -.04em;
        }
        .mig-copy {
            margin-top: 12px;
            max-width: 760px;
            color: var(--text2);
            font-size: 15px;
        }
        .mig-steps {
            display: grid;
            gap: 10px;
            margin-top: 22px;
        }
        .mig-step {
            display: grid;
            grid-template-columns: 34px 1fr;
            gap: 12px;
            align-items: start;
            padding: 12px 14px;
            border: 1px solid var(--border);
            border-radius: 14px;
            background: rgba(255, 255, 255, .03);
        }
        .mig-step-num {
            width: 34px;
            height: 34px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            background: rgba(77, 156, 255, .14);
            color: var(--secondary);
            font-size: 13px;
            font-weight: 800;
        }
        .mig-step strong { display: block; margin-bottom: 2px; }
        .mig-step span { color: var(--text2); font-size: 13px; }
        .mig-summary {
            display: grid;
            gap: 14px;
        }
        .mig-summary-card {
            border: 1px solid var(--border);
            border-radius: var(--radius-lg);
            padding: 22px;
            background: linear-gradient(180deg, rgba(255, 255, 255, .05), rgba(255, 255, 255, .02));
            box-shadow: var(--shadow-md);
        }
        .mig-summary-card h2 {
            font-size: 13px;
            text-transform: uppercase;
            letter-spacing: .06em;
            color: var(--text3);
            margin-bottom: 10px;
        }
        .mig-status {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 8px 12px;
            border-radius: var(--radius-full);
            font-size: 12px;
            font-weight: 700;
            margin-bottom: 10px;
        }
        .mig-status.ok {
            background: rgba(0, 229, 160, .12);
            color: var(--primary);
        }
        .mig-status.warn {
            background: rgba(255, 184, 48, .12);
            color: var(--warning);
        }
        .mig-stat-grid {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 12px;
            margin-top: 10px;
        }
        .mig-stat {
            padding: 14px;
            border-radius: 14px;
            background: rgba(255, 255, 255, .03);
            border: 1px solid var(--border);
        }
        .mig-stat strong {
            display: block;
            font-size: 24px;
            font-family: 'Bricolage Grotesque', sans-serif;
            line-height: 1;
            margin-bottom: 6px;
        }
        .mig-stat span {
            color: var(--text2);
            font-size: 12px;
        }
        .mig-section {
            border: 1px solid var(--border);
            border-radius: var(--radius-lg);
            background: linear-gradient(180deg, rgba(255, 255, 255, .04), rgba(255, 255, 255, .02));
            box-shadow: var(--shadow-md);
            overflow: hidden;
        }
        .mig-section-head {
            padding: 22px 24px 14px;
            border-bottom: 1px solid var(--border);
        }
        .mig-section-head h2 {
            display: flex;
            align-items: center;
            gap: 10px;
            font-size: 24px;
            margin-bottom: 6px;
        }
        .mig-section-head p {
            color: var(--text2);
            font-size: 14px;
            max-width: 760px;
        }
        .mig-section-body { padding: 24px; }
        .mig-component-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(230px, 1fr));
            gap: 14px;
        }
        .mig-component {
            position: relative;
            display: block;
            padding: 16px;
            border-radius: 16px;
            border: 1px solid var(--border);
            background: rgba(8, 16, 30, .8);
            transition: transform var(--t), border-color var(--t), background var(--t), box-shadow var(--t);
            cursor: pointer;
        }
        .mig-component:hover {
            transform: translateY(-2px);
            border-color: var(--border2);
            box-shadow: var(--shadow-sm);
        }
        .mig-component input {
            position: absolute;
            inset: 0;
            opacity: 0;
            cursor: pointer;
        }
        .mig-component.checked {
            border-color: rgba(0, 229, 160, .42);
            background: rgba(0, 229, 160, .07);
            box-shadow: 0 10px 30px rgba(0, 229, 160, .08);
        }
        .mig-component-top {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 10px;
            margin-bottom: 12px;
        }
        .mig-component-icon {
            width: 42px;
            height: 42px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            background: rgba(77, 156, 255, .12);
            color: var(--secondary);
            font-size: 17px;
        }
        .mig-checkmark {
            width: 26px;
            height: 26px;
            border-radius: 50%;
            border: 1px solid var(--border2);
            background: rgba(255, 255, 255, .03);
            display: flex;
            align-items: center;
            justify-content: center;
            color: transparent;
            transition: all var(--t);
        }
        .mig-component.checked .mig-checkmark {
            color: #071019;
            background: var(--primary);
            border-color: var(--primary);
        }
        .mig-component-title {
            font-size: 18px;
            font-weight: 800;
            margin-bottom: 6px;
        }
        .mig-component-copy {
            color: var(--text2);
            font-size: 13px;
            min-height: 40px;
        }
        .mig-component-meta {
            margin-top: 14px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 10px;
            flex-wrap: wrap;
        }
        .mig-badge {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 6px 10px;
            border-radius: var(--radius-full);
            font-size: 11px;
            font-weight: 700;
        }
        .mig-badge.ok {
            color: var(--primary);
            background: rgba(0, 229, 160, .1);
        }
        .mig-badge.warn {
            color: var(--warning);
            background: rgba(255, 184, 48, .12);
        }
        .mig-actions {
            display: flex;
            align-items: center;
            gap: 12px;
            flex-wrap: wrap;
            margin-top: 18px;
        }
        .mig-actions-note {
            color: var(--text2);
            font-size: 13px;
        }
        .mig-actions .btn[disabled] {
            opacity: .55;
            cursor: not-allowed;
            box-shadow: none;
        }
        .mig-list {
            display: grid;
            gap: 18px;
        }
        .mig-group {
            border: 1px solid var(--border);
            border-radius: 16px;
            overflow: hidden;
            background: rgba(255, 255, 255, .02);
        }
        .mig-group-head {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
            padding: 16px 18px;
            border-bottom: 1px solid var(--border);
            background: rgba(255, 255, 255, .03);
        }
        .mig-group-head h3 {
            display: flex;
            align-items: center;
            gap: 10px;
            font-size: 18px;
        }
        .mig-group-body {
            display: grid;
            gap: 10px;
            padding: 16px;
        }
        .mig-item {
            padding: 14px 16px;
            border-radius: 14px;
            border: 1px solid var(--border);
            background: rgba(8, 16, 30, .65);
        }
        .mig-item strong {
            display: block;
            margin-bottom: 6px;
            font-size: 14px;
        }
        .mig-item p {
            color: var(--text2);
            font-size: 13px;
        }
        .mig-empty {
            display: grid;
            place-items: center;
            text-align: center;
            min-height: 240px;
            padding: 30px;
            border: 1px dashed var(--border2);
            border-radius: 18px;
            background: radial-gradient(circle at top, rgba(0, 229, 160, .08), transparent 45%);
        }
        .mig-empty i {
            font-size: 42px;
            color: var(--primary);
            margin-bottom: 16px;
        }
        .mig-empty h3 {
            font-size: 28px;
            margin-bottom: 8px;
        }
        .mig-empty p {
            max-width: 520px;
            color: var(--text2);
        }
        .mig-error-list {
            margin-top: 14px;
            display: grid;
            gap: 10px;
        }
        .mig-error-item {
            padding: 12px 14px;
            border-radius: 12px;
            background: rgba(255, 77, 109, .1);
            border: 1px solid rgba(255, 77, 109, .18);
        }
        .mig-error-item strong {
            display: block;
            margin-bottom: 4px;
        }
        .mig-error-item span {
            color: #ffc1cc;
            font-size: 13px;
        }
        @media (max-width: 980px) {
            .mig-hero {
                grid-template-columns: 1fr;
            }
            .mig-section-head h2 {
                font-size: 21px;
            }
        }
    </style>
</head>
<body>
<div class="header">
    <div class="header-container">
        <div class="logo" style="cursor:default;">
            <div class="logo-icon"><i class="fas fa-database"></i></div>
            Migracoes
        </div>
        <div class="nav-buttons">
            <a href="painel_dono.php" class="btn btn-secondary"><i class="fas fa-arrow-left"></i> Painel</a>
        </div>
    </div>
</div>

<div class="container">
    <?php if (isset($_SESSION['sucesso'])): ?>
        <div class="alert alert-success"><i class="fas fa-check-circle"></i> <?= htmlspecialchars($_SESSION['sucesso']) ?></div>
        <?php unset($_SESSION['sucesso']); ?>
    <?php endif; ?>

    <?php if (isset($_SESSION['erro'])): ?>
        <div class="alert alert-error"><i class="fas fa-exclamation-circle"></i> <?= htmlspecialchars($_SESSION['erro']) ?></div>
        <?php unset($_SESSION['erro']); ?>
    <?php endif; ?>

    <?php if ($resultadoMigracao && !empty($resultadoMigracao['falhas'])): ?>
        <div class="alert alert-error">
            <i class="fas fa-triangle-exclamation"></i>
            Algumas alteracoes falharam. Revise os detalhes abaixo antes de tentar novamente.
        </div>
    <?php endif; ?>

    <div class="mig-layout">
        <section class="mig-hero">
            <div class="mig-hero-main">
                <span class="mig-eyebrow"><i class="fas fa-layer-group"></i> Controle de banco</span>
                <h1 class="mig-title">Atualize o schema com seguranca</h1>
                <p class="mig-copy">
                    Esta tela aplica alteracoes estruturais do banco de dados de forma controlada.
                    Os modulos do sistema nao executam mais <code>ALTER TABLE</code> ou <code>CREATE TABLE</code> durante o uso normal.
                </p>

                <div class="mig-steps">
                    <div class="mig-step">
                        <div class="mig-step-num">1</div>
                        <div>
                            <strong>Veja o status atual</strong>
                            <span>Confira quantas alteracoes ainda faltam e quais modulos serao afetados.</span>
                        </div>
                    </div>
                    <div class="mig-step">
                        <div class="mig-step-num">2</div>
                        <div>
                            <strong>Selecione os modulos</strong>
                            <span>Se nada for marcado, o sistema aplica todas as pendencias encontradas.</span>
                        </div>
                    </div>
                    <div class="mig-step">
                        <div class="mig-step-num">3</div>
                        <div>
                            <strong>Execute e valide</strong>
                            <span>Depois da execucao, os modulos dependentes ficam liberados para uso.</span>
                        </div>
                    </div>
                </div>
            </div>

            <div class="mig-summary">
                <div class="mig-summary-card">
                    <h2>Status geral</h2>
                    <div class="mig-status <?= $totalPendentes === 0 ? 'ok' : 'warn' ?>">
                        <i class="fas <?= $totalPendentes === 0 ? 'fa-circle-check' : 'fa-triangle-exclamation' ?>"></i>
                        <?= htmlspecialchars($statusGeral) ?>
                    </div>
                    <p style="color:var(--text2);font-size:14px;"><?= htmlspecialchars($descricaoGeral) ?></p>

                    <div class="mig-stat-grid">
                        <div class="mig-stat">
                            <strong><?= $totalPendentes ?></strong>
                            <span>alteracao(oes) pendente(s)</span>
                        </div>
                        <div class="mig-stat">
                            <strong><?= $componentesComPendencia ?></strong>
                            <span>modulo(s) afetado(s)</span>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <section class="mig-section">
            <div class="mig-section-head">
                <h2><i class="fas fa-play-circle"></i> Executar migracoes</h2>
                <p>Escolha um ou mais modulos para atualizar. Se tudo ja estiver aplicado, a tela mostra que o banco esta sincronizado.</p>
            </div>
            <div class="mig-section-body">
                <form method="POST">
                    <?= campo_csrf() ?>

                    <div class="mig-component-grid">
                        <?php foreach ($componentes as $componente): ?>
                            <?php
                            $meta = $metaComponentes[$componente] ?? [
                                'titulo' => strtoupper($componente),
                                'descricao' => 'Modulo sem descricao cadastrada.',
                                'icone' => 'fa-cube',
                            ];
                            $quantidade = count($pendentesPorComponente[$componente] ?? []);
                            $selecionado = in_array($componente, $componentesSelecionados, true);
                            ?>
                            <label class="mig-component<?= $selecionado ? ' checked' : '' ?>">
                                <input type="checkbox" name="componentes[]" value="<?= htmlspecialchars($componente) ?>" <?= $selecionado ? 'checked' : '' ?>>
                                <div class="mig-component-top">
                                    <div class="mig-component-icon"><i class="fas <?= htmlspecialchars($meta['icone']) ?>"></i></div>
                                    <div class="mig-checkmark"><i class="fas fa-check"></i></div>
                                </div>
                                <div class="mig-component-title"><?= htmlspecialchars($meta['titulo']) ?></div>
                                <div class="mig-component-copy"><?= htmlspecialchars($meta['descricao']) ?></div>
                                <div class="mig-component-meta">
                                    <span class="mig-badge <?= $quantidade === 0 ? 'ok' : 'warn' ?>">
                                        <i class="fas <?= $quantidade === 0 ? 'fa-circle-check' : 'fa-hourglass-half' ?>"></i>
                                        <?= $quantidade === 0 ? 'Sem pendencia' : "$quantidade pendencia(s)" ?>
                                    </span>
                                    <span style="color:var(--text3);font-size:12px;font-weight:700;"><?= strtoupper(htmlspecialchars($componente)) ?></span>
                                </div>
                            </label>
                        <?php endforeach; ?>
                    </div>

                    <div class="mig-actions">
                        <button type="submit" class="btn btn-primary" <?= $totalPendentes === 0 ? 'disabled' : '' ?>>
                            <i class="fas fa-bolt"></i>
                            <?= $totalPendentes === 0 ? 'Banco atualizado' : 'Executar migracoes' ?>
                        </button>
                        <span class="mig-actions-note">
                            <?= $totalPendentes === 0
                                ? 'Nenhuma acao necessaria agora.'
                                : 'Se nenhum modulo for marcado, todas as pendencias serao aplicadas.' ?>
                        </span>
                    </div>
                </form>
            </div>
        </section>

        <section class="mig-section">
            <div class="mig-section-head">
                <h2><i class="fas fa-list-check"></i> Pendencias atuais</h2>
                <p>Lista detalhada do que ainda falta criar ou ajustar no banco.</p>
            </div>
            <div class="mig-section-body">
                <?php if (empty($pendentes)): ?>
                    <div class="mig-empty">
                        <div>
                            <i class="fas fa-check-double"></i>
                            <h3>Nenhuma migracao pendente</h3>
                            <p>O banco ja possui todas as tabelas, colunas e indices previstos neste bloco de evolucao.</p>
                        </div>
                    </div>
                <?php else: ?>
                    <div class="mig-list">
                        <?php foreach ($componentes as $componente): ?>
                            <?php if (empty($pendentesPorComponente[$componente])) continue; ?>
                            <?php $meta = $metaComponentes[$componente] ?? ['titulo' => strtoupper($componente), 'icone' => 'fa-cube']; ?>
                            <div class="mig-group">
                                <div class="mig-group-head">
                                    <h3>
                                        <i class="fas <?= htmlspecialchars($meta['icone']) ?>" style="color:var(--secondary);"></i>
                                        <?= htmlspecialchars($meta['titulo']) ?>
                                    </h3>
                                    <span class="mig-badge warn">
                                        <i class="fas fa-hourglass-half"></i>
                                        <?= count($pendentesPorComponente[$componente]) ?> item(ns)
                                    </span>
                                </div>
                                <div class="mig-group-body">
                                    <?php foreach ($pendentesPorComponente[$componente] as $item): ?>
                                        <div class="mig-item">
                                            <strong><?= htmlspecialchars($item['id']) ?></strong>
                                            <p><?= htmlspecialchars(migracao_descrever_item($item)) ?></p>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>

                <?php if ($resultadoMigracao && !empty($resultadoMigracao['falhas'])): ?>
                    <div class="mig-error-list">
                        <?php foreach ($resultadoMigracao['falhas'] as $falha): ?>
                            <div class="mig-error-item">
                                <strong><?= htmlspecialchars($falha['id']) ?></strong>
                                <span><?= htmlspecialchars($falha['erro']) ?></span>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </section>
    </div>
</div>

<script>
document.querySelectorAll('.mig-component input[type="checkbox"]').forEach(function (input) {
    input.addEventListener('change', function () {
        input.closest('.mig-component').classList.toggle('checked', input.checked);
    });
});
</script>
</body>
</html>
