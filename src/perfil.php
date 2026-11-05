<?php

declare(strict_types=1);

use VirtualMotors\Repository\PedidoRepository;
use VirtualMotors\Repository\UserRepository;

require_once __DIR__ . '/database.php';
require_once __DIR__ . '/pedidorepository.php';
require_once __DIR__ . '/userrepository.php';
require_once __DIR__ . '/authsession.php';

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

$sessaoValida = validar_sessao_usuario();
$idSessao = filter_var($_SESSION['usuario_id'] ?? null, FILTER_VALIDATE_INT);
$idSolicitado = isset($_GET['id']) ? filter_var($_GET['id'], FILTER_VALIDATE_INT) : null;
$perfilPublico = isset($_GET['id']);
$idPerfil = $perfilPublico ? $idSolicitado : $idSessao;

if ($perfilPublico && ($idPerfil === false || $idPerfil === null || $idPerfil < 1)) {
    http_response_code(404);
    exit('Perfil não encontrado.');
}

if (!$perfilPublico && (!$sessaoValida || $idSessao === false || $idSessao === null || $idSessao < 1)) {
    header('Location: login.php');
    exit;
}

$perfilDoUsuarioAtual = $idSessao !== false && $idSessao !== null && $idSessao === $idPerfil;
$mensagemSucesso = $perfilDoUsuarioAtual ? ($_SESSION['perfil_sucesso'] ?? '') : '';
if ($perfilDoUsuarioAtual) {
    unset($_SESSION['perfil_sucesso']);
}

$perfil = null;
$erroBanco = false;
try {
    $perfil = (new UserRepository())->findProfileById((int) $idPerfil);
} catch (PDOException $erro) {
    error_log($erro->getMessage());
    http_response_code(503);
    $erroBanco = true;
}

$pedidosRecentes = [];
$uidConta = '';
$erroCompras = false;
if ($perfilDoUsuarioAtual) {
    try {
        $uidConta = (new UserRepository())->findUidById((int) $idSessao) ?? '';
        $_SESSION['usuario_uid'] = $uidConta;
        $pedidosRecentes = (new PedidoRepository())->buscarRecentesPorUsuario((int) $idSessao);
    } catch (PDOException $erro) {
        error_log($erro->getMessage());
        http_response_code(503);
        $erroCompras = true;
    }
}

if ($perfil === null && !$erroBanco) {
    http_response_code(404);
}

$nomePerfil = is_string($perfil['nome'] ?? null) ? $perfil['nome'] : '';
$bioPerfil = is_string($perfil['bio_perfil'] ?? null) ? $perfil['bio_perfil'] : '';
$partesBio = preg_split('~(https?://[^\s<]+)~iu', $bioPerfil, -1, PREG_SPLIT_DELIM_CAPTURE);
$bioHtml = '';
if (is_array($partesBio)) {
    foreach ($partesBio as $parteBio) {
        if (preg_match('~^https?://~iu', $parteBio) !== 1) {
            $bioHtml .= htmlspecialchars($parteBio, ENT_QUOTES, 'UTF-8');
            continue;
        }

        $url = rtrim($parteBio, '.,!?;:');
        $pontuacao = substr($parteBio, strlen($url));
        if (filter_var($url, FILTER_VALIDATE_URL) === false) {
            $bioHtml .= htmlspecialchars($parteBio, ENT_QUOTES, 'UTF-8');
            continue;
        }

        $urlEscapada = htmlspecialchars($url, ENT_QUOTES, 'UTF-8');
        $bioHtml .= '<a href="' . $urlEscapada . '" target="_blank" rel="noopener noreferrer">'
            . $urlEscapada . '</a>' . htmlspecialchars($pontuacao, ENT_QUOTES, 'UTF-8');
    }
}
$fotoPerfil = is_string($perfil['foto_perfil'] ?? null) ? $perfil['foto_perfil'] : null;
$bannerPerfil = is_string($perfil['banner_perfil'] ?? null) ? $perfil['banner_perfil'] : null;
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="color-scheme" content="light dark">
    <title><?= $perfilPublico ? 'Perfil' : 'Meu perfil' ?> · Virtual Motors</title>
    <link rel="stylesheet" href="../public/style.css">
</head>
<body>
    <?php
    $headerBasePath = '../';
    require __DIR__ . '/header.php';
    ?>
    <main class="profile-page-wrapper">
        <div class="purchase-history-heading">
            <span class="section-tag">ÁREA DO CLIENTE</span>
            <h1><?= $perfilPublico ? 'Perfil de ' . htmlspecialchars($nomePerfil, ENT_QUOTES, 'UTF-8') : 'Meu perfil' ?></h1>
            <?php if ($perfilDoUsuarioAtual) : ?>
                <p>Acompanhe um resumo das suas compras recentes.</p>
                <?php if ($uidConta !== '') : ?>
                    <p class="profile-uid">UID da conta: <strong><?= htmlspecialchars($uidConta, ENT_QUOTES, 'UTF-8') ?></strong></p>
                <?php endif; ?>
            <?php endif; ?>
        </div>

        <?php if ($mensagemSucesso !== '') : ?>
            <p class="cart-message" role="status"><?= htmlspecialchars((string) $mensagemSucesso, ENT_QUOTES, 'UTF-8') ?></p>
        <?php endif; ?>
        <?php if ($erroBanco || $perfil === null) : ?>
            <p class="cart-message cart-error" role="alert">Não foi possível carregar o perfil agora. Tente novamente em instantes.</p>
        <?php else : ?>
            <section class="profile-view-card" aria-label="Perfil de <?= htmlspecialchars($nomePerfil, ENT_QUOTES, 'UTF-8') ?>">
                <div class="profile-cover">
                    <?php if ($bannerPerfil !== null && $bannerPerfil !== '') : ?>
                        <img src="<?= htmlspecialchars('../public/' . $bannerPerfil, ENT_QUOTES, 'UTF-8') ?>" alt="">
                    <?php endif; ?>
                </div>
                <div class="profile-view-content">
                    <div class="profile-view-identity">
                        <div class="profile-preview-avatar">
                            <?php if ($fotoPerfil !== null && $fotoPerfil !== '') : ?>
                                <img src="<?= htmlspecialchars('../public/' . $fotoPerfil, ENT_QUOTES, 'UTF-8') ?>" alt="Foto de perfil">
                            <?php else : ?>
                                <span aria-hidden="true"><?= htmlspecialchars(strtoupper(substr($nomePerfil, 0, 1)), ENT_QUOTES, 'UTF-8') ?></span>
                            <?php endif; ?>
                        </div>
                        <h2 class="profile-view-name"><?= htmlspecialchars($nomePerfil, ENT_QUOTES, 'UTF-8') ?></h2>
                    </div>
                    <?php if ($bioPerfil !== '') : ?>
                        <p class="profile-view-bio"><?= nl2br($bioHtml, false) ?></p>
                    <?php endif; ?>
                </div>
            </section>
            <?php if ($perfilDoUsuarioAtual) : ?>
                <nav class="profile-page-actions" aria-label="Opções do perfil">
                    <a class="button button-primary" href="personalizar-perfil.php">Personalizar perfil</a>
                    <a class="button button-outline" href="minhas-compras.php">Minhas compras</a>
                </nav>
            <?php endif; ?>
        <?php endif; ?>

        <?php if ($perfilDoUsuarioAtual) : ?>
            <section class="profile-purchases" aria-labelledby="profilePurchasesTitle">
                <div class="profile-purchases-heading">
                    <h2 id="profilePurchasesTitle">Minhas compras</h2>
                    <a class="button button-outline" href="minhas-compras.php">Ver todas as compras</a>
                </div>

                <?php if ($erroCompras) : ?>
                    <p class="cart-message cart-error" role="alert">Não foi possível carregar suas compras recentes. Tente novamente em instantes.</p>
                <?php elseif ($pedidosRecentes === []) : ?>
                    <section class="purchase-history-empty">
                        <h3>Nenhuma compra encontrada</h3>
                        <p>Suas compras aparecerão aqui após a conclusão do pedido.</p>
                        <a class="button button-primary" href="../index.php#veiculos">Explorar veículos</a>
                    </section>
                <?php else : ?>
                    <div class="purchase-history-list">
                        <?php foreach ($pedidosRecentes as $pedido) : ?>
                            <?php $statusPedido = str_replace(' (demonstração)', '', $pedido['status_pedido']); ?>
                            <article class="purchase-order profile-purchase-order">
                                <header class="purchase-order-header">
                                    <div>
                                        <span class="purchase-order-label">Pedido #<?= (int) $pedido['id_pedido'] ?></span>
                                        <h3><?= htmlspecialchars(date('d/m/Y \à\s H:i', strtotime($pedido['criado_em'])), ENT_QUOTES, 'UTF-8') ?></h3>
                                    </div>
                                    <span class="purchase-order-status">
                                        <?= htmlspecialchars($statusPedido, ENT_QUOTES, 'UTF-8') ?>
                                    </span>
                                </header>
                                <footer class="purchase-order-total">
                                    <span>Total do pedido</span>
                                    <strong>R$ <?= number_format((float) $pedido['valor_total'], 2, ',', '.') ?></strong>
                                </footer>
                            </article>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </section>
        <?php endif; ?>
    </main>
    <script src="../public/script.js" defer></script>
</body>
</html>
