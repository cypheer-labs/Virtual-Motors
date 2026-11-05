<?php

declare(strict_types=1);

use VirtualMotors\Repository\PedidoRepository;

require_once __DIR__ . '/../src/admin_guard.php';
require_once __DIR__ . '/../src/pedidorepository.php';

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
        <title>Pedidos indisponíveis · Virtual Motors</title>
        <link rel="stylesheet" href="admin.css">
    </head>
    <body class="admin-layout">
        <main class="admin-main">
            <h1>Pedidos indisponíveis</h1>
            <p class="admin-state error" role="alert">
                Não foi possível verificar seu acesso ou conectar ao banco de dados.
                Tente novamente mais tarde.
            </p>
        </main>
    </body>
    </html>
    <?php
    exit;
}

function pedidos_admin_escape(mixed $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function pedidos_admin_post_string(string $key): string
{
    $value = $_POST[$key] ?? '';

    return is_string($value) ? trim($value) : '';
}

$csrfToken = $_SESSION['admin_csrf_token'] ?? null;
if (!is_string($csrfToken) || $csrfToken === '') {
    $csrfToken = bin2hex(random_bytes(32));
    $_SESSION['admin_csrf_token'] = $csrfToken;
}

$mensagem = '';
$erro = false;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $tokenEnviado = pedidos_admin_post_string('csrf_token');
    $acao = pedidos_admin_post_string('acao');
    if (!hash_equals($csrfToken, $tokenEnviado)) {
        http_response_code(400);
        $mensagem = 'Sua sessão expirou. Atualize a página e tente novamente.';
        $erro = true;
    } elseif (!in_array($acao, ['atualizar', 'excluir'], true)) {
        http_response_code(400);
        $mensagem = 'Ação inválida.';
        $erro = true;
    } else {
        $idPedido = filter_var($_POST['id_pedido'] ?? null, FILTER_VALIDATE_INT);

        if ($idPedido === false || $idPedido === null || $idPedido < 1) {
            $mensagem = 'Pedido inválido.';
            $erro = true;
        } else {
            try {
                $repository = new PedidoRepository($pdo);
                if ($acao === 'excluir') {
                    if (!$repository->excluirPelaAdministracao((int) $idPedido)) {
                        $mensagem = 'Pedido não encontrado.';
                        $erro = true;
                    } else {
                        header('Location: pedidos.php?excluido=1');
                        exit;
                    }
                } else {
                    $status = pedidos_admin_post_string('status_pedido');
                    $enderecoEntrega = pedidos_admin_post_string('endereco_entrega');
                    if (!in_array($status, PedidoRepository::STATUS_VALIDOS, true)) {
                        $mensagem = 'Selecione um status válido.';
                        $erro = true;
                    } elseif (strlen($enderecoEntrega) > 500) {
                        $mensagem = 'O endereço deve ter no máximo 500 caracteres.';
                        $erro = true;
                    } elseif (
                        !$repository->atualizarPelaAdministracao(
                            (int) $idPedido,
                            $status,
                            $enderecoEntrega
                        )
                    ) {
                        $mensagem = 'Pedido não encontrado.';
                        $erro = true;
                    } else {
                        header('Location: pedidos.php?atualizado=1');
                        exit;
                    }
                }
            } catch (PDOException $exception) {
                error_log($exception->getMessage());
                $mensagem = 'Não foi possível alterar o pedido. Tente novamente.';
                $erro = true;
            }
        }
    }
}

$fontStylesheet = 'https://fonts.googleapis.com/css2?family=Barlow:wght@400;500;600&'
    . 'family=Barlow+Semi+Condensed:wght@600;700&display=swap';

if (($_GET['atualizado'] ?? '') === '1') {
    $mensagem = 'Pedido atualizado com sucesso.';
} elseif (($_GET['excluido'] ?? '') === '1') {
    $mensagem = 'Pedido excluído permanentemente.';
}

try {
    $pedidos = (new PedidoRepository($pdo))->buscarTodosParaAdministracao();
} catch (PDOException $exception) {
    error_log($exception->getMessage());
    http_response_code(503);
    $pedidos = [];
    $mensagem = 'Não foi possível carregar os pedidos.';
    $erro = true;
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="color-scheme" content="light dark">
    <title>Pedidos · Virtual Motors</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link
        href="<?= pedidos_admin_escape($fontStylesheet) ?>"
        rel="stylesheet"
    >
    <link rel="stylesheet" href="admin.css">
</head>
<body class="admin-layout">
<nav class="admin-nav" aria-label="Navegação administrativa">
    <p class="marca">Virtual Motors</p>
    <a href="dashboard.php">Painel</a>
    <a href="produtos.php">Produtos</a>
    <a href="usuarios.php">Usuários</a>
    <a href="pedidos.php" aria-current="page">Pedidos</a>
    <a class="sair" href="../index.php">Voltar à loja</a>
</nav>

<main class="admin-main">
    <div class="admin-page-actions">
        <a class="admin-button secondary" href="../index.php">← Voltar à página inicial</a>
    </div>
    <h1>Gerenciar pedidos</h1>
    <p class="admin-sub">
        Atualize o endereço de entrega e o andamento de cada pedido.
        Marque como concluído após finalizar o atendimento.
    </p>

    <?php if ($mensagem !== '') : ?>
        <p class="admin-message<?= $erro ? ' error' : '' ?>" role="<?= $erro ? 'alert' : 'status' ?>">
            <?= pedidos_admin_escape($mensagem) ?>
        </p>
    <?php endif; ?>

    <?php if ($pedidos === []) : ?>
        <p class="admin-state">Nenhum pedido registrado ainda.</p>
    <?php else : ?>
        <div class="admin-order-list">
            <?php foreach ($pedidos as $pedido) : ?>
                <?php $idPedido = (int) $pedido['id_pedido']; ?>
                <article class="admin-order-card">
                    <header class="admin-order-heading">
                        <div>
                            <h2>Pedido #<?= $idPedido ?></h2>
                            <p>
                                <?= pedidos_admin_escape($pedido['nome_usuario']) ?>
                                · UID <?= pedidos_admin_escape($pedido['uid_usuario']) ?>
                            </p>
                        </div>
                        <strong>R$ <?= number_format((float) $pedido['valor_total'], 2, ',', '.') ?></strong>
                    </header>
                    <dl class="admin-order-details">
                        <div>
                            <dt>Data</dt>
                            <dd>
                                <?= pedidos_admin_escape(
                                    date('d/m/Y H:i', strtotime((string) $pedido['criado_em']))
                                ) ?>
                            </dd>
                        </div>
                        <div>
                            <dt>Pagamento</dt>
                            <dd><?= pedidos_admin_escape($pedido['pagamento']) ?></dd>
                        </div>
                        <div>
                            <dt>Itens</dt>
                            <dd>
                                <?php if ($pedido['itens'] === []) : ?>
                                    Sem itens registrados
                                <?php else : ?>
                                    <?php foreach ($pedido['itens'] as $indice => $item) : ?>
                                        <?= $indice > 0 ? ', ' : '' ?>
                                        <?= pedidos_admin_escape($item['nome_carro'] ?: 'Veículo') ?>
                                        × <?= (int) $item['quantidade'] ?>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </dd>
                        </div>
                    </dl>
                    <form class="admin-order-form" method="post" action="pedidos.php">
                        <input type="hidden" name="csrf_token" value="<?= pedidos_admin_escape($csrfToken) ?>">
                        <input type="hidden" name="acao" value="atualizar">
                        <input type="hidden" name="id_pedido" value="<?= $idPedido ?>">
                        <div class="admin-field">
                            <label for="status-<?= $idPedido ?>">Status</label>
                            <select id="status-<?= $idPedido ?>" name="status_pedido" required>
                                <?php foreach (PedidoRepository::STATUS_VALIDOS as $status) : ?>
                                    <option
                                        value="<?= pedidos_admin_escape($status) ?>"
                                        <?= $pedido['status_pedido'] === $status ? 'selected' : '' ?>
                                    >
                                        <?= pedidos_admin_escape($status) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="admin-field">
                            <label for="endereco-<?= $idPedido ?>">Endereço de entrega</label>
                            <input
                                id="endereco-<?= $idPedido ?>"
                                name="endereco_entrega"
                                maxlength="500"
                                value="<?= pedidos_admin_escape($pedido['endereco_entrega']) ?>"
                            >
                        </div>
                        <button class="admin-button" type="submit">Salvar pedido</button>
                    </form>
                    <form
                        class="admin-order-delete"
                        method="post"
                        action="pedidos.php"
                        onsubmit="return confirm('Excluir pedido e itens? A ação é permanente.');"
                    >
                        <input type="hidden" name="csrf_token" value="<?= pedidos_admin_escape($csrfToken) ?>">
                        <input type="hidden" name="acao" value="excluir">
                        <input type="hidden" name="id_pedido" value="<?= $idPedido ?>">
                        <button class="admin-button danger" type="submit">Excluir pedido</button>
                    </form>
                </article>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</main>
</body>
</html>
