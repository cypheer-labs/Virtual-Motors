<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/admin_guard.php';

try {
    exigir_admin();
    $pdo = admin_pdo();
} catch (PDOException $exception) {
    error_log($exception->getMessage());
    http_response_code(503);
    ?>
    <!DOCTYPE html>
    <html lang="pt-BR">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>Painel indisponível · Virtual Motors</title>
        <link rel="stylesheet" href="admin.css">
    </head>
    <body class="admin-layout">
        <main class="admin-main">
            <h1>Painel administrativo indisponível</h1>
            <p class="admin-state error" role="alert">Não foi possível verificar seu acesso ou conectar ao banco de dados. Tente novamente mais tarde.</p>
        </main>
    </body>
    </html>
    <?php
    exit;
}

function dashboard_escape(mixed $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function dashboard_query(PDO $pdo, string $sql): array
{
    try {
        return [$pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC), null];
    } catch (PDOException $exception) {
        error_log($exception->getMessage());

        return [[], 'Não foi possível carregar estes dados.'];
    }
}

function dashboard_table(array $rows, ?string $error, string $emptyMessage): void
{
    if ($error !== null) {
        echo '<p class="admin-state error" role="alert">' . dashboard_escape($error) . '</p>';
        return;
    }

    if ($rows === []) {
        echo '<p class="admin-state">' . dashboard_escape($emptyMessage) . '</p>';
        return;
    }

    $columns = array_keys($rows[0]);
    echo '<div class="admin-table-wrap"><table class="admin-table"><thead><tr>';
    foreach ($columns as $column) {
        echo '<th scope="col">' . dashboard_escape($column) . '</th>';
    }
    echo '</tr></thead><tbody>';

    foreach ($rows as $row) {
        echo '<tr>';
        foreach ($columns as $column) {
            $value = $row[$column];
            if ($column === 'preco' || $column === 'valor_total') {
                $value = 'R$ ' . number_format((float) $value, 2, ',', '.');
            }
            echo '<td>' . dashboard_escape($value) . '</td>';
        }
        echo '</tr>';
    }

    echo '</tbody></table></div>';
}

[$carroStats, $carroStatsError] = dashboard_query(
    $pdo,
    'SELECT COUNT(*) AS total, COALESCE(AVG(preco), 0) AS media, '
    . 'COALESCE(SUM(estoque), 0) AS unidades_estoque, '
    . 'COALESCE(SUM(estoque = 0), 0) AS produtos_sem_estoque FROM carros'
);
$totalCarros = (int) ($carroStats[0]['total'] ?? 0);
$precoMedio = (float) ($carroStats[0]['media'] ?? 0);
$unidadesEstoque = (int) ($carroStats[0]['unidades_estoque'] ?? 0);
$produtosSemEstoque = (int) ($carroStats[0]['produtos_sem_estoque'] ?? 0);

[$usuarioStats, $usuarioStatsError] = dashboard_query(
    $pdo,
    "SELECT COUNT(*) AS total, COALESCE(SUM(tipo_usuario = 'admin'), 0) AS admins FROM usuario"
);
$totalUsuarios = (int) ($usuarioStats[0]['total'] ?? 0);
$totalAdmins = (int) ($usuarioStats[0]['admins'] ?? 0);

[$pedidoStats, $pedidoStatsError] = dashboard_query(
    $pdo,
    'SELECT COUNT(*) AS total FROM pedido'
);
$totalPedidos = (int) ($pedidoStats[0]['total'] ?? 0);

[$recentCarros, $recentCarrosError] = dashboard_query(
    $pdo,
    'SELECT id_carro, nome, cor, preco, estoque, jogo_origem '
    . 'FROM carros ORDER BY id_carro DESC LIMIT 5'
);
[$recentUsuarios, $recentUsuariosError] = dashboard_query(
    $pdo,
    'SELECT id_usuario, uid AS UID, nome, email, tipo_usuario FROM usuario ORDER BY id_usuario DESC LIMIT 5'
);
[$recentPedidos, $recentPedidosError] = dashboard_query(
    $pdo,
    'SELECT p.id_pedido, p.id_usuario, u.uid AS UID, p.data_entrega, p.valor_total, p.pagamento, p.status_pedido '
    . 'FROM pedido p JOIN usuario u ON u.id_usuario = p.id_usuario '
    . 'ORDER BY p.id_pedido DESC LIMIT 5'
);
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="color-scheme" content="light dark">
    <title>Painel · Virtual Motors</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Barlow:wght@400;500;600&family=Barlow+Semi+Condensed:wght@600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="admin.css">
</head>
<body class="admin-layout">
<nav class="admin-nav" aria-label="Navegação administrativa">
    <p class="marca">Virtual Motors</p>
    <a href="dashboard.php" aria-current="page">Painel</a>
    <a href="produtos.php">Produtos</a>
    <a href="usuarios.php">Usuários</a>
    <a href="pedidos.php">Pedidos</a>
    <a class="sair" href="../index.php">Voltar à loja</a>
</nav>

<main class="admin-main">
    <div class="admin-page-actions">
        <a class="admin-button secondary" href="../index.php">← Voltar à página inicial</a>
    </div>
    <h1>Painel administrativo</h1>
    <p class="admin-sub">Resumo dos dados registrados na loja.</p>

    <div class="admin-indicators">
        <article class="admin-indicator">
            <div class="admin-indicator-value"><?= $carroStatsError !== null ? '–' : $totalCarros ?></div>
            <div class="admin-indicator-label">Carros cadastrados</div>
            <div class="admin-indicator-note">
                <?= $carroStatsError !== null
                    ? dashboard_escape($carroStatsError)
                    : 'Preço médio: R$ ' . number_format($precoMedio, 2, ',', '.')
                        . ' · Estoque: ' . $unidadesEstoque . ' unidade(s); '
                        . $produtosSemEstoque . ' produto(s) sem estoque' ?>
            </div>
        </article>
        <article class="admin-indicator">
            <div class="admin-indicator-value"><?= $usuarioStatsError !== null ? '–' : $totalUsuarios ?></div>
            <div class="admin-indicator-label">Usuários</div>
            <div class="admin-indicator-note">
                <?= $usuarioStatsError !== null ? dashboard_escape($usuarioStatsError) : $totalAdmins . ' administrador(es)' ?>
            </div>
        </article>
        <article class="admin-indicator">
            <div class="admin-indicator-value"><?= $pedidoStatsError !== null ? '–' : $totalPedidos ?></div>
            <div class="admin-indicator-label">Pedidos</div>
            <div class="admin-indicator-note">
                <?= $pedidoStatsError !== null ? dashboard_escape($pedidoStatsError) : 'Total registrado' ?>
            </div>
        </article>
    </div>

    <section class="admin-section">
        <div class="admin-section-heading">
            <h2>Últimos carros</h2>
            <a href="produtos.php">Gerenciar produtos</a>
        </div>
        <?php dashboard_table($recentCarros, $recentCarrosError, 'Nenhum carro cadastrado ainda.'); ?>
    </section>

    <section class="admin-section">
        <div class="admin-section-heading">
            <h2>Últimos usuários</h2>
            <a href="usuarios.php">Gerenciar usuários</a>
        </div>
        <?php dashboard_table($recentUsuarios, $recentUsuariosError, 'Nenhum usuário cadastrado ainda.'); ?>
    </section>

    <section class="admin-section">
        <div class="admin-section-heading">
            <h2>Últimos pedidos</h2>
            <a href="pedidos.php">Gerenciar pedidos</a>
        </div>
        <?php dashboard_table($recentPedidos, $recentPedidosError, 'Nenhum pedido registrado ainda.'); ?>
    </section>
</main>
</body>
</html>
