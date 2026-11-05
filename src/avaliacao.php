<?php

use VirtualMotors\Repository\AvaliacaoRepository;

require_once __DIR__ . '/database.php';
require_once __DIR__ . '/avaliacaorepository.php';

session_start();

$idUsuario = filter_var($_SESSION['usuario_id'] ?? null, FILTER_VALIDATE_INT);
$usuarioAutenticado = $idUsuario !== false && $idUsuario !== null && $idUsuario > 0;
$idPedidoEnviado = $_POST['pedido'] ?? $_GET['pedido'] ?? null;
$idPedido = filter_var($idPedidoEnviado, FILTER_VALIDATE_INT);
$idPedido = $idPedido !== false && $idPedido !== null && $idPedido > 0 ? $idPedido : null;
$mensagemErro = null;
$mensagemSucesso = $_SESSION['virtual_motors_avaliacao_mensagem'] ?? null;
$compraConfirmada = !empty($_SESSION['virtual_motors_pagamento_sucesso']);
unset($_SESSION['virtual_motors_avaliacao_mensagem'], $_SESSION['virtual_motors_pagamento_sucesso']);
$chaveCsrf = 'virtual_motors_avaliacao_csrf';
if (!isset($_SESSION[$chaveCsrf])) {
    $_SESSION[$chaveCsrf] = bin2hex(random_bytes(32));
}

$avaliacoes = [];
$pedidoConfirmado = null;
$avaliacaoDoPedido = null;
$erroBanco = false;

try {
    $repositorio = new AvaliacaoRepository();

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $tokenEnviado = $_POST['csrf_token'] ?? '';
        $estrelasEnviadas = filter_var($_POST['estrelas'] ?? null, FILTER_VALIDATE_INT);
        $descricaoEnviada = $_POST['descricao'] ?? '';
        $descricao = is_string($descricaoEnviada) ? trim($descricaoEnviada) : '';
        $quantidadeCaracteres = preg_match_all('/./us', $descricao);

        if (!$usuarioAutenticado) {
            header('Location: login.php');
            exit;
        }
        if (!is_string($tokenEnviado) || !hash_equals($_SESSION[$chaveCsrf], $tokenEnviado)) {
            $mensagemErro = 'A sessão expirou. Atualize a página e tente novamente.';
        } elseif ($idPedido === null) {
            $mensagemErro = 'Não foi possível identificar o pedido.';
        } elseif ($estrelasEnviadas === false || $estrelasEnviadas < 1 || $estrelasEnviadas > 5) {
            $mensagemErro = 'Selecione uma quantidade de estrelas entre 1 e 5.';
        } elseif ($quantidadeCaracteres === false || $quantidadeCaracteres < 5 || $quantidadeCaracteres > 300) {
            $mensagemErro = 'Escreva uma descrição breve, entre 5 e 300 caracteres.';
        } else {
            $pedidoConfirmado = $repositorio->buscarPedidoConfirmado($idPedido, (int) $idUsuario);
            $avaliacaoDoPedido = $repositorio->buscarPorPedido($idPedido);

            if ($pedidoConfirmado === null) {
                $mensagemErro = 'Não encontramos um pedido confirmado disponível para avaliação.';
            } elseif ($avaliacaoDoPedido !== null) {
                $mensagemErro = 'Este pedido já foi avaliado.';
            } else {
                $repositorio->criar($idPedido, (int) $idUsuario, $estrelasEnviadas, $descricao);
                $_SESSION['virtual_motors_avaliacao_mensagem'] = 'Obrigado por compartilhar sua avaliação.';
                header('Location: avaliacao.php?pedido=' . $idPedido);
                exit;
            }
        }
    }

    if ($idPedido !== null && $usuarioAutenticado) {
        $pedidoConfirmado = $repositorio->buscarPedidoConfirmado($idPedido, (int) $idUsuario);
        if ($pedidoConfirmado !== null) {
            $avaliacaoDoPedido = $repositorio->buscarPorPedido($idPedido);
        }
    }

    $avaliacoes = $repositorio->listarTodas();
} catch (PDOException $erro) {
    error_log($erro->getMessage());
    http_response_code(503);
    $erroBanco = true;
}

$headerBasePath = '../';
$headerPaginaAvaliacoes = true;
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="color-scheme" content="light dark">
    <meta name="description" content="Leia e compartilhe avaliações de clientes da Virtual Motors.">
    <title>Avaliações - Virtual Motors</title>
    <link rel="stylesheet" href="../public/style.css?v=review-details">
    <link rel="icon" href="../assets/vm.png" type="image/png">
</head>
<body>
    <?php require __DIR__ . '/header.php'; ?>

    <main class="reviews-wrapper">
        <div class="reviews-heading">
            <span class="section-tag">EXPERIÊNCIAS DE CLIENTES</span>
            <h1>Avaliações</h1>
            <p>Opiniões de quem já comprou na Virtual Motors.</p>
        </div>

        <?php if ($compraConfirmada) : ?>
            <p class="cart-message cart-success" role="status">Compra #<?= (int) ($_SESSION['virtual_motors_ultimo_pedido'] ?? 0) ?> confirmada. Obrigado pela preferência!</p>
        <?php endif; ?>

        <?php if ($mensagemSucesso !== null) : ?>
            <p class="cart-message cart-success" role="status">
                <?= htmlspecialchars((string) $mensagemSucesso, ENT_QUOTES, 'UTF-8') ?>
            </p>
        <?php endif; ?>

        <?php if ($mensagemErro !== null) : ?>
            <p class="cart-message cart-error" role="alert">
                <?= htmlspecialchars($mensagemErro, ENT_QUOTES, 'UTF-8') ?>
            </p>
        <?php endif; ?>

        <?php if ($erroBanco) : ?>
            <p class="cart-message cart-error" role="alert">Não foi possível carregar as avaliações. Tente novamente em instantes.</p>
        <?php elseif ($pedidoConfirmado !== null && $avaliacaoDoPedido === null && $usuarioAutenticado) : ?>
            <form class="review-form" method="post" action="avaliacao.php?pedido=<?= (int) $idPedido ?>">
                <h2>Avalie o pedido #<?= (int) $idPedido ?></h2>
                <p class="review-purchase-info">
                    <strong>Produto(s) comprado(s):</strong>
                    <?= htmlspecialchars($pedidoConfirmado['produtos'] ?: 'Produto não identificado', ENT_QUOTES, 'UTF-8') ?>
                </p>
                <p class="review-purchase-info">
                    <strong>Comprado em:</strong>
                    <time datetime="<?= htmlspecialchars(date(DATE_ATOM, strtotime($pedidoConfirmado['data_compra'])), ENT_QUOTES, 'UTF-8') ?>">
                        <?= htmlspecialchars(date('d/m/Y', strtotime($pedidoConfirmado['data_compra'])), ENT_QUOTES, 'UTF-8') ?>
                    </time>
                </p>
                <input type="hidden" name="pedido" value="<?= (int) $idPedido ?>">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION[$chaveCsrf], ENT_QUOTES, 'UTF-8') ?>">

                <fieldset class="review-stars">
                    <legend>Quantas estrelas você dá para sua experiência?</legend>
                    <?php for ($estrela = 1; $estrela <= 5; $estrela++) : ?>
                        <label class="review-star-option">
                            <input type="radio" name="estrelas" value="<?= $estrela ?>" required>
                            <span aria-label="<?= $estrela ?> <?= $estrela === 1 ? 'estrela' : 'estrelas' ?>">
                                <span class="review-star-symbol" aria-hidden="true">★</span> <?= $estrela ?>
                            </span>
                        </label>
                    <?php endfor; ?>
                </fieldset>

                <label class="review-description-label" for="reviewDescription">Conte brevemente como foi sua compra</label>
                <textarea
                    class="review-description"
                    id="reviewDescription"
                    name="descricao"
                    minlength="5"
                    maxlength="300"
                    required
                ></textarea>
                <p class="review-form-note">Até 300 caracteres.</p>
                <button class="button button-primary" type="submit">Enviar avaliação</button>
            </form>
        <?php elseif ($avaliacaoDoPedido !== null && $usuarioAutenticado) : ?>
            <p class="cart-message" role="status">Este pedido já recebeu sua avaliação de <?= (int) $avaliacaoDoPedido['estrelas'] ?> de 5 estrelas.</p>
        <?php elseif ($idPedido !== null && $usuarioAutenticado && !$erroBanco && $mensagemErro === null) : ?>
            <p class="cart-message cart-error" role="alert">Não encontramos um pedido confirmado disponível para avaliação.</p>
        <?php endif; ?>

        <section class="reviews-list" aria-labelledby="reviewsListTitle">
            <h2 id="reviewsListTitle">O que nossos clientes dizem</h2>
            <?php if ($avaliacoes === [] && !$erroBanco) : ?>
                <p class="reviews-empty">Ainda não há avaliações. Seja o primeiro cliente a compartilhar sua experiência.</p>
            <?php else : ?>
                <?php foreach ($avaliacoes as $avaliacao) : ?>
                    <?php $nota = (int) $avaliacao['estrelas']; ?>
                    <article class="review-item">
                        <header>
                            <h3><?= htmlspecialchars($avaliacao['nome'], ENT_QUOTES, 'UTF-8') ?></h3>
                            <span class="review-rating" aria-label="<?= $nota ?> de 5 estrelas">
                                <?= str_repeat('★', $nota) . str_repeat('☆', 5 - $nota) ?>
                            </span>
                        </header>
                        <p class="review-purchase-info">
                            <strong>Produto(s) comprado(s):</strong>
                            <?= htmlspecialchars($avaliacao['produtos'] ?: 'Produto não identificado', ENT_QUOTES, 'UTF-8') ?>
                        </p>
                        <p class="review-purchase-info">
                            <strong>Comprado em:</strong>
                            <time datetime="<?= htmlspecialchars(date(DATE_ATOM, strtotime($avaliacao['data_compra'])), ENT_QUOTES, 'UTF-8') ?>">
                                <?= htmlspecialchars(date('d/m/Y', strtotime($avaliacao['data_compra'])), ENT_QUOTES, 'UTF-8') ?>
                            </time>
                        </p>
                        <p><?= nl2br(htmlspecialchars($avaliacao['descricao'], ENT_QUOTES, 'UTF-8')) ?></p>
                        <time class="review-date" datetime="<?= htmlspecialchars(date(DATE_ATOM, strtotime($avaliacao['criado_em'])), ENT_QUOTES, 'UTF-8') ?>">
                            Avaliado em <?= htmlspecialchars(date('d/m/Y', strtotime($avaliacao['criado_em'])), ENT_QUOTES, 'UTF-8') ?>
                        </time>
                    </article>
                <?php endforeach; ?>
            <?php endif; ?>
        </section>
    </main>

    <script src="../public/script.js" defer></script>
</body>
</html>
