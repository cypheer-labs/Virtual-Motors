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

if (!validar_sessao_usuario()) {
    header('Location: login.php');
    exit;
}

$idUsuario = filter_var($_SESSION['usuario_id'] ?? null, FILTER_VALIDATE_INT);
if ($idUsuario === false || $idUsuario === null || $idUsuario < 1) {
    header('Location: login.php');
    exit;
}

$pedidosRecentes = [];
$erroBanco = false;
$uidConta = '';
try {
    $uidConta = (new UserRepository())->findUidById($idUsuario) ?? '';
    $_SESSION['usuario_uid'] = $uidConta;
    $pedidosRecentes = (new PedidoRepository())->buscarRecentesPorUsuario($idUsuario);
} catch (PDOException $erro) {
    error_log($erro->getMessage());
    http_response_code(503);
    $erroBanco = true;
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="color-scheme" content="light dark">
    <title>Meu perfil · Virtual Motors</title>
    <link rel="stylesheet" href="../public/style.css">
</head>
<body>
    <?php
    $headerBasePath = '../';
    require __DIR__ . '/header.php';
    ?>
    <main class="purchase-history-wrapper">
        <div class="purchase-history-heading">
            <span class="section-tag">ÁREA DO CLIENTE</span>
            <h1>Meu perfil</h1>
            <p>Acompanhe um resumo das suas compras recentes.</p>
            <p class="profile-uid">UID da conta: <strong><?= htmlspecialchars($uidConta, ENT_QUOTES, 'UTF-8') ?></strong></p>
        </div>

        <section class="profile-purchases" aria-labelledby="profilePurchasesTitle">
            <div class="profile-purchases-heading">
                <h2 id="profilePurchasesTitle">Minhas compras</h2>
                <a class="button button-outline" href="minhas-compras.php">Ver todas as compras</a>
            </div>

            <?php if ($erroBanco) : ?>
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
    </main>
    <script src="../public/script.js" defer></script>
</body>
</html>
