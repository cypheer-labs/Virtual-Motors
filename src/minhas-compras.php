<?php

use VirtualMotors\Repository\PedidoRepository;
use VirtualMotors\Repository\CarroRepository;

require_once __DIR__ . '/database.php';
require_once __DIR__ . '/pedidorepository.php';
require_once __DIR__ . '/carrorepository.php';

session_start();

if (empty($_SESSION['usuario_email']) || empty($_SESSION['usuario_id'])) {
    header('Location: login.php');
    exit;
}

$pedidos = [];
$erroBanco = false;
try {
    $pedidos = (new PedidoRepository())->buscarPorUsuario((int) $_SESSION['usuario_id']);
    $idsCarros = [];
    foreach ($pedidos as $pedido) {
        foreach ($pedido['itens'] as $item) {
            $idsCarros[] = (int) $item['id_carro'];
        }
    }

    $imagensCarros = [];
    foreach ((new CarroRepository())->buscarPorIds($idsCarros) as $carro) {
        $imagensCarros[(int) $carro['id_carro']] = $carro['imagem'];
    }
} catch (PDOException $erro) {
    error_log($erro->getMessage());
    http_response_code(503);
    $erroBanco = true;
}

$headerBasePath = '../';
$headerPaginaCompras = true;
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="color-scheme" content="light dark">
    <meta name="description" content="Consulte seus pedidos e detalhes de compra na Virtual Motors.">
    <title>Minhas compras - Virtual Motors</title>
    <link rel="stylesheet" href="../public/style.css">
    <link rel="icon" href="../assets/vm.png" type="image/png">
</head>
<body>
    <?php require __DIR__ . '/header.php'; ?>

    <main class="purchase-history-wrapper">
        <div class="purchase-history-heading">
            <span class="section-tag">ÁREA DO CLIENTE</span>
            <h1>Minhas compras</h1>
            <p>Acompanhe seus pedidos, itens e dados de entrega.</p>
        </div>

        <?php if ($erroBanco) : ?>
            <p class="cart-message cart-error" role="alert">Não foi possível carregar seu histórico. Tente novamente em instantes.</p>
        <?php elseif ($pedidos === []) : ?>
            <section class="purchase-history-empty">
                <h2>Nenhuma compra demonstrativa registrada</h2>
                <p>Suas compras aparecerão aqui assim que você concluir um pedido de demonstração.</p>
                <a class="button button-primary" href="../index.php#veiculos">Explorar veículos</a>
            </section>
        <?php else : ?>
            <div class="purchase-history-list">
                <?php foreach ($pedidos as $pedido) : ?>
                    <article class="purchase-order" aria-labelledby="order-<?= (int) $pedido['id_pedido'] ?>">
                        <header class="purchase-order-header">
                            <div>
                                <span class="purchase-order-label">Pedido #<?= (int) $pedido['id_pedido'] ?></span>
                                <h2 id="order-<?= (int) $pedido['id_pedido'] ?>">
                                    <?= htmlspecialchars(date('d/m/Y \à\s H:i', strtotime($pedido['criado_em'])), ENT_QUOTES, 'UTF-8') ?>
                                </h2>
                            </div>
                            <span class="purchase-order-status"><?= htmlspecialchars($pedido['status_pedido'], ENT_QUOTES, 'UTF-8') ?></span>
                        </header>

                        <div class="purchase-order-content">
                            <section class="purchase-order-items" aria-label="Itens do pedido">
                                <h3>Itens comprados</h3>
                                <?php foreach ($pedido['itens'] as $item) : ?>
                                    <div class="purchase-order-item">
                                        <img
                                            class="purchase-order-item-image"
                                            src="<?= htmlspecialchars($imagensCarros[$item['id_carro']] ?? '../assets/image.png', ENT_QUOTES, 'UTF-8') ?>"
                                            alt="Foto do veículo <?= htmlspecialchars($item['nome_carro'] ?: 'comprado', ENT_QUOTES, 'UTF-8') ?>"
                                            loading="lazy"
                                        >
                                        <div>
                                            <strong><?= htmlspecialchars($item['nome_carro'] ?: 'Veículo', ENT_QUOTES, 'UTF-8') ?></strong>
                                            <span><?= htmlspecialchars($item['jogo_origem'] ?: 'Origem não informada', ENT_QUOTES, 'UTF-8') ?> · <?= (int) $item['quantidade'] ?> unidade(s)</span>
                                        </div>
                                        <strong>R$ <?= number_format((float) $item['preco_unitario'] * (int) $item['quantidade'], 2, ',', '.') ?></strong>
                                    </div>
                                <?php endforeach; ?>
                            </section>

                            <dl class="purchase-order-details">
                                <div>
                                    <dt>Pagamento</dt>
                                    <dd><?= htmlspecialchars($pedido['pagamento'], ENT_QUOTES, 'UTF-8') ?></dd>
                                </div>
                                <div>
                                    <dt>Endereço de entrega</dt>
                                    <dd><?= htmlspecialchars($pedido['endereco_entrega'], ENT_QUOTES, 'UTF-8') ?></dd>
                                </div>
                            </dl>
                        </div>

                        <footer class="purchase-order-total">
                            <span>Total do pedido</span>
                            <strong>R$ <?= number_format((float) $pedido['valor_total'], 2, ',', '.') ?></strong>
                        </footer>
                    </article>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </main>

    <footer class="footer">
        <div class="container footer-content">
            <div class="footer-brand">
                <img src="../assets/virtual-motors.png" alt="Virtual Motors">
                <p>Seu próximo veículo começa aqui.</p>
            </div>
            <div class="footer-column">
                <h4>Empresa</h4>
                <a href="sobre-nos.php">Sobre nós</a>
                <a href="../index.php#veiculos">Veículos</a>
                <a href="../index.php#contato">Contato</a>
            </div>
            <div class="footer-column">
                <h4>Atendimento</h4>
                <a href="../index.php#contato">WhatsApp</a>
                <a href="../index.php#contato">Instagram</a>
                <a href="../index.php#contato">Localização</a>
            </div>
        </div>
        <div class="footer-bottom">
            <div class="container">
                <p>© 2026 Virtual Motors. Todos os direitos reservados.</p>
            </div>
        </div>
    </footer>
    <script src="../public/script.js" defer></script>
</body>
</html>