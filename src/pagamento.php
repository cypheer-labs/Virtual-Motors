<?php

use VirtualMotors\Repository\CarroRepository;

require_once __DIR__ . '/database.php';
require_once __DIR__ . '/carrorepository.php';

session_start();

$modoVisualizacao = ($_GET['visualizacao'] ?? '') === '1';
$temaVisualizacao = in_array($_GET['tema'] ?? '', ['light', 'dark'], true)
    ? $_GET['tema']
    : null;
$chaveCarrinho = 'virtual_motors_carrinho';
$chaveCompraDireta = 'virtual_motors_compra_direta';
$compraDireta = false;
$idCarroDireto = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['acao'] ?? '') === 'comprar_direto') {
    $idCarroDireto = filter_var($_POST['id_carro'] ?? '', FILTER_VALIDATE_INT);
    if ($idCarroDireto === false || $idCarroDireto < 1) {
        $_SESSION['virtual_motors_carrinho_mensagem'] = 'Não foi possível identificar o veículo para compra.';
        header('Location: carrinho.php');
        exit;
    }

    $_SESSION[$chaveCompraDireta] = (int) $idCarroDireto;
}

if (empty($_SESSION['usuario_email'])) {
    $_SESSION['virtual_motors_pagamento_pendente'] = true;
    header('Location: login.php');
    exit;
}
unset($_SESSION['virtual_motors_pagamento_pendente']);

if (!$modoVisualizacao && isset($_SESSION[$chaveCompraDireta])) {
    $idCarroDireto = filter_var($_SESSION[$chaveCompraDireta], FILTER_VALIDATE_INT);
    unset($_SESSION[$chaveCompraDireta]);
    $compraDireta = $idCarroDireto !== false && $idCarroDireto > 0;
}

$itensCarrinho = $_SESSION[$chaveCarrinho] ?? [];

if ($modoVisualizacao) {
    $pedidoPendente = $_SESSION['virtual_motors_pedido_pendente_qr'] ?? [];
    $itensPedido = is_array($pedidoPendente['itens'] ?? null) ? $pedidoPendente['itens'] : [];
} elseif ($compraDireta) {
    $itensPedido = [(int) $idCarroDireto => 1];
} else {
    $itensPedido = $itensCarrinho;
}

if (!$modoVisualizacao && (!is_array($itensPedido) || $itensPedido === [])) {
    header('Location: carrinho.php');
    exit;
}

$veiculos = [];
$erroBanco = false;

try {
    $veiculos = (new CarroRepository())->buscarPorIds(array_keys($itensPedido));
} catch (PDOException $erro) {
    error_log($erro->getMessage());
    http_response_code(500);
    $erroBanco = true;
}

$totalPedido = 0;
if (!$modoVisualizacao && !$erroBanco && $veiculos !== []) {
    $itensPedidoConfirmados = [];
    foreach ($veiculos as $veiculo) {
        $idCarro = (int) $veiculo['id_carro'];
        $quantidade = (int) ($itensPedido[$idCarro] ?? 1);
        if ($quantidade > 0) {
            $itensPedidoConfirmados[$idCarro] = $quantidade;
        }
    }

    $_SESSION['virtual_motors_pedido_pendente_qr'] = [
        'itens' => $itensPedidoConfirmados,
        'token' => bin2hex(random_bytes(32)),
    ];
    unset($_SESSION['virtual_motors_pedido_qr_autorizado']);
}
?>
<!DOCTYPE html>
<html lang="pt-BR"<?= $temaVisualizacao !== null ? ' data-theme="' . htmlspecialchars($temaVisualizacao, ENT_QUOTES, 'UTF-8') . '"' : '' ?>>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="color-scheme" content="light dark">
    <meta name="description" content="Confira seu pedido Virtual Motors e preencha os dados de entrega.">
    <title>Pagamento - Virtual Motors</title>
    <link rel="stylesheet" href="../public/style.css">
    <link rel="icon" href="../assets/vm.png" type="image/png">
</head>
<body<?= $modoVisualizacao ? ' class="checkout-preview"' : '' ?>>
    <?php
    $headerBasePath = '../';
    require __DIR__ . '/header.php';
    ?>

    <main class="cart-wrapper checkout-wrapper">
        <div class="cart-title-area">
            <span>Finalização da compra</span>
            <h1>Entrega e pagamento</h1>
            <p>Preencha os dados para revisar seu pedido.</p>
        </div>

        <?php if ($erroBanco) : ?>
            <p class="cart-message cart-error" role="alert">Não foi possível consultar os itens do pedido. Verifique a conexão com o banco e tente novamente.</p>
            <a class="button button-primary" href="carrinho.php">Voltar ao carrinho</a>
        <?php elseif ($veiculos === []) : ?>
            <div class="empty-cart">
                <p>Não encontramos os veículos deste pedido no estoque.</p>
                <a href="carrinho.php" class="button button-primary">Voltar ao carrinho</a>
            </div>
        <?php else : ?>
            <div class="checkout-layout">
                <div class="checkout-form">
                    <p class="checkout-notice" role="note">
                        Esta página é uma prévia. O pagamento não está integrado; não informe dados reais de cartão.
                    </p>

                    <section class="checkout-section" aria-labelledby="deliveryTitle">
                        <div class="checkout-section-heading">
                            <span class="checkout-step">1</span>
                            <div>
                                <h2 id="deliveryTitle">Endereço de entrega</h2>
                                <p>Informe onde deseja receber o veículo.</p>
                            </div>
                        </div>

                        <div class="checkout-field checkout-cep-field">
                            <label for="cep">CEP</label>
                            <div class="cep-input-row">
                                <input id="cep" name="cep" type="text" form="checkoutReviewForm" inputmode="numeric" autocomplete="postal-code" maxlength="9" placeholder="00000-000" required>
                                <button class="button button-outline checkout-cep-button" id="consultCep" type="button" data-endpoint="consulta_cep.php">Consultar CEP</button>
                            </div>
                            <p class="checkout-help" id="cepStatus" role="status" aria-live="polite">Informe o CEP e consulte para preencher o endereço automaticamente.</p>
                        </div>

                        <div class="checkout-fields-grid">
                            <div class="checkout-field checkout-field-wide">
                                <label for="address">Rua / avenida</label>
                                <input id="address" name="address" type="text" form="checkoutReviewForm" autocomplete="street-address" placeholder="Nome da rua ou avenida" required>
                            </div>
                            <div class="checkout-field">
                                <label for="addressNumber">Número</label>
                                <input id="addressNumber" name="addressNumber" type="text" form="checkoutReviewForm" autocomplete="address-line2" placeholder="Nº" required>
                            </div>
                            <div class="checkout-field">
                                <label for="addressComplement">Complemento <span>(opcional)</span></label>
                                <input id="addressComplement" name="addressComplement" type="text" form="checkoutReviewForm" placeholder="Apartamento, bloco...">
                            </div>
                            <div class="checkout-field">
                                <label for="neighborhood">Bairro</label>
                                <input id="neighborhood" name="neighborhood" type="text" form="checkoutReviewForm" autocomplete="address-level3" placeholder="Bairro" required>
                            </div>
                            <div class="checkout-field">
                                <label for="city">Cidade</label>
                                <input id="city" name="city" type="text" form="checkoutReviewForm" autocomplete="address-level2" placeholder="Cidade" required>
                            </div>
                            <div class="checkout-field">
                                <label for="state">Estado</label>
                                <select id="state" name="state" form="checkoutReviewForm" autocomplete="address-level1" required>
                                    <option value="">Selecione</option>
                                    <option value="AC">Acre</option>
                                    <option value="AL">Alagoas</option>
                                    <option value="AP">Amapá</option>
                                    <option value="AM">Amazonas</option>
                                    <option value="BA">Bahia</option>
                                    <option value="CE">Ceará</option>
                                    <option value="DF">Distrito Federal</option>
                                    <option value="ES">Espírito Santo</option>
                                    <option value="GO">Goiás</option>
                                    <option value="MA">Maranhão</option>
                                    <option value="MT">Mato Grosso</option>
                                    <option value="MS">Mato Grosso do Sul</option>
                                    <option value="MG">Minas Gerais</option>
                                    <option value="PA">Pará</option>
                                    <option value="PB">Paraíba</option>
                                    <option value="PR">Paraná</option>
                                    <option value="PE">Pernambuco</option>
                                    <option value="PI">Piauí</option>
                                    <option value="RJ">Rio de Janeiro</option>
                                    <option value="RN">Rio Grande do Norte</option>
                                    <option value="RS">Rio Grande do Sul</option>
                                    <option value="RO">Rondônia</option>
                                    <option value="RR">Roraima</option>
                                    <option value="SC">Santa Catarina</option>
                                    <option value="SP">São Paulo</option>
                                    <option value="SE">Sergipe</option>
                                    <option value="TO">Tocantins</option>
                                </select>
                            </div>
                        </div>
                    </section>

                    <section class="checkout-section" aria-labelledby="paymentTitle">
                        <div class="checkout-section-heading">
                            <span class="checkout-step">2</span>
                            <div>
                                <h2 id="paymentTitle">Forma de pagamento</h2>
                                <p>Escolha uma opção para visualizar os detalhes.</p>
                            </div>
                        </div>

                        <div class="payment-methods">
                            <label class="payment-method">
                                <input type="radio" name="paymentMethod" value="pix" form="checkoutReviewForm" checked>
                                <span><strong>Pix</strong><small>Pagamento instantâneo</small></span>
                            </label>
                            <label class="payment-method">
                                <input type="radio" name="paymentMethod" value="credit" form="checkoutReviewForm">
                                <span><strong>Cartão de crédito</strong><small>Dados não serão enviados</small></span>
                            </label>
                            <label class="payment-method">
                                <input type="radio" name="paymentMethod" value="boleto" form="checkoutReviewForm">
                                <span><strong>Boleto</strong><small>Opção demonstrativa</small></span>
                            </label>
                        </div>
                        <p class="payment-method-message" id="paymentMethodMessage" role="status">A opção Pix será disponibilizada quando a integração de pagamento estiver pronta.</p>
                    </section>

                    <form class="checkout-review-form" id="checkoutReviewForm" method="post" action="qrcode.php">
                        <input type="hidden" name="acao" value="revisar">
                        <input type="hidden" name="tema" value="">
                        <input
                            type="hidden"
                            name="token"
                            value="<?= htmlspecialchars($_SESSION['virtual_motors_pedido_pendente_qr']['token'] ?? '', ENT_QUOTES, 'UTF-8') ?>"
                        >
                        <button class="button button-primary checkout-submit" id="reviewCheckout" type="submit"<?= $modoVisualizacao ? ' disabled' : '' ?>>Revisar pedido</button>
                    </form>
                </div>

                <aside class="order-summary checkout-summary">
                    <h2>Resumo do pedido</h2>
                    <?php foreach ($veiculos as $veiculo) : ?>
                        <?php
                        $idCarro = (int) $veiculo['id_carro'];
                        $quantidade = (int) ($itensPedido[$idCarro] ?? 1);
                        $subtotal = (float) $veiculo['preco'] * $quantidade;
                        $totalPedido += $subtotal;
                        ?>
                        <div class="checkout-order-item">
                            <img class="checkout-order-image" src="<?= htmlspecialchars($veiculo['imagem'], ENT_QUOTES, 'UTF-8') ?>" alt="Foto ilustrativa do veículo <?= htmlspecialchars($veiculo['nome'], ENT_QUOTES, 'UTF-8') ?>">
                            <div>
                                <strong><?= htmlspecialchars($veiculo['nome'], ENT_QUOTES, 'UTF-8') ?></strong>
                                <span><?= $quantidade ?> unidade(s)</span>
                            </div>
                            <strong>R$ <?= number_format($subtotal, 2, ',', '.') ?></strong>
                        </div>
                    <?php endforeach; ?>
                    <div class="summary-row">
                        <span>Entrega</span>
                        <strong>A combinar</strong>
                    </div>
                    <div class="summary-row checkout-total">
                        <span>Total dos veículos</span>
                        <strong>R$ <?= number_format($totalPedido, 2, ',', '.') ?></strong>
                    </div>
                    <a class="cart-continue-link" href="carrinho.php">Voltar e alterar carrinho</a>
                </aside>
            </div>
        <?php endif; ?>
    </main>

    <script src="../public/script.js" defer></script>
</body>
</html>
