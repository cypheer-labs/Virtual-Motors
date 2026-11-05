<?php

session_start();

require_once __DIR__ . '/database.php';
require_once __DIR__ . '/carrorepository.php';
require_once __DIR__ . '/pedidorepository.php';

$chavePedidoPendente = 'virtual_motors_pedido_pendente_qr';
$chavePedidoAutorizado = 'virtual_motors_pedido_qr_autorizado';
$chaveTema = 'virtual_motors_pedido_tema';
$chaveCarrinho = 'virtual_motors_carrinho';
$chaveSucesso = 'virtual_motors_pagamento_sucesso';
$mensagemErro = null;
$pagamentoConcluido = !empty($_SESSION[$chaveSucesso]);
unset($_SESSION[$chaveSucesso]);

if (empty($_SESSION['usuario_email'])) {
    header('Location: login.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $pedidoPendente = $_SESSION[$chavePedidoPendente] ?? null;
    $tokenSessao = is_array($pedidoPendente) && isset($pedidoPendente['token'])
        ? $pedidoPendente['token']
        : '';
    $tokenEnviado = $_POST['token'] ?? '';

    if (!is_string($tokenEnviado) || $tokenSessao === '' || !hash_equals($tokenSessao, $tokenEnviado)) {
        http_response_code(400);
        $mensagemErro = 'Não foi possível validar esta etapa. Volte ao pagamento e tente novamente.';
    } elseif (($_POST['acao'] ?? '') === 'revisar' && !empty($pedidoPendente['itens'])) {
        $camposEndereco = [
            'cep' => 'CEP',
            'address' => 'rua/avenida',
            'addressNumber' => 'número',
            'neighborhood' => 'bairro',
            'city' => 'cidade',
            'state' => 'estado',
        ];
        $endereco = [];
        foreach ($camposEndereco as $campo => $rotulo) {
            $valor = $_POST[$campo] ?? '';
            if (!is_string($valor) || trim($valor) === '') {
                http_response_code(422);
                $mensagemErro = 'Preencha o endereço de entrega completo para continuar.';
                break;
            }
            $endereco[$campo] = trim($valor);
        }

        $cepNumerico = preg_replace('/\D/', '', $endereco['cep'] ?? '');
        $formaPagamento = $_POST['paymentMethod'] ?? '';
        if ($mensagemErro === null && (strlen($cepNumerico) !== 8 || !in_array($endereco['state'], ['AC', 'AL', 'AP', 'AM', 'BA', 'CE', 'DF', 'ES', 'GO', 'MA', 'MT', 'MS', 'MG', 'PA', 'PB', 'PR', 'PE', 'PI', 'RJ', 'RN', 'RS', 'RO', 'RR', 'SC', 'SP', 'SE', 'TO'], true))) {
            http_response_code(422);
            $mensagemErro = 'Confira o CEP e o estado informados.';
        } elseif ($mensagemErro === null && !in_array($formaPagamento, ['pix', 'credit', 'boleto'], true)) {
            http_response_code(422);
            $mensagemErro = 'Selecione uma forma de pagamento válida.';
        }

        if ($mensagemErro !== null) {
            $_SESSION[$chavePedidoAutorizado] = true;
        } else {
            $enderecoCompleto = implode(', ', array_filter([
                $endereco['address'] . ', ' . $endereco['addressNumber'],
                trim((string) ($_POST['addressComplement'] ?? '')),
                $endereco['neighborhood'],
                $endereco['city'] . '/' . $endereco['state'],
                $endereco['cep'],
            ]));
            $formasPagamento = ['pix' => 'Pix', 'credit' => 'Cartão de crédito', 'boleto' => 'Boleto'];
            $_SESSION['virtual_motors_checkout'] = [
                'endereco_entrega' => $enderecoCompleto,
                'pagamento' => $formasPagamento[$formaPagamento],
            ];
        }

        if ($mensagemErro === null) {
            $temaEnviado = $_POST['tema'] ?? '';
            $_SESSION[$chaveTema] = in_array($temaEnviado, ['light', 'dark'], true)
            ? $temaEnviado
            : 'light';
            $_SESSION[$chavePedidoAutorizado] = true;
            header('Location: qrcode.php');
            exit;
        }
    } elseif (($_POST['acao'] ?? '') === 'confirmar' && !empty($_SESSION[$chavePedidoAutorizado])) {
        try {
            $checkout = $_SESSION['virtual_motors_checkout'] ?? null;
            $itensPedido = is_array($pedidoPendente['itens'] ?? null) ? $pedidoPendente['itens'] : [];
            $idUsuario = (int) ($_SESSION['usuario_id'] ?? 0);
            if ($idUsuario < 1 || !is_array($checkout) || $itensPedido === []) {
                throw new \RuntimeException('Dados insuficientes para registrar o pedido.');
            }

            $veiculos = (new \VirtualMotors\Repository\CarroRepository())->buscarPorIds(array_keys($itensPedido));
            if (count($veiculos) !== count($itensPedido)) {
                throw new \RuntimeException('Um ou mais veículos não estão mais disponíveis.');
            }

            $itens = [];
            $valorTotal = 0;
            foreach ($veiculos as $veiculo) {
                $idCarro = (int) $veiculo['id_carro'];
                $quantidade = (int) ($itensPedido[$idCarro] ?? 0);
                $preco = (float) $veiculo['preco'];
                if ($quantidade < 1 || $preco < 0) {
                    throw new \RuntimeException('Os itens do pedido são inválidos.');
                }
                $itens[] = [
                    'id_carro' => $idCarro,
                    'quantidade' => $quantidade,
                    'preco_unitario' => $preco,
                    'nome_carro' => $veiculo['nome'],
                    'jogo_origem' => $veiculo['jogo_origem'],
                ];
                $valorTotal += $preco * $quantidade;
            }

            $idPedido = (new \VirtualMotors\Repository\PedidoRepository())->criar([
                'id_usuario' => $idUsuario,
                'valor_total' => $valorTotal,
                'pagamento' => $checkout['pagamento'],
                'endereco_entrega' => $checkout['endereco_entrega'],
                'itens' => $itens,
            ]);

            $_SESSION['virtual_motors_ultimo_pedido'] = $idPedido;
            $_SESSION[$chaveCarrinho] = [];
            unset(
                $_SESSION['virtual_motors_compra_direta'],
                $_SESSION[$chavePedidoPendente],
                $_SESSION['virtual_motors_checkout'],
                $_SESSION[$chavePedidoAutorizado]
            );
            $_SESSION[$chaveSucesso] = true;
            header('Location: avaliacao.php?pedido=' . $idPedido);
            exit;
        } catch (\Throwable $erro) {
            error_log($erro->getMessage());
            http_response_code(503);
            $mensagemErro = 'Não foi possível registrar sua compra. Seus itens continuam no carrinho; tente novamente.';
        }
    } else {
        http_response_code(400);
        $mensagemErro = 'Não foi possível continuar com este pedido. Volte ao pagamento e tente novamente.';
    }
}

if (!$pagamentoConcluido && empty($_SESSION[$chavePedidoAutorizado])) {
    header('Location: pagamento.php');
    exit;
}

$pedidoPendente = $_SESSION[$chavePedidoPendente] ?? [];
$token = is_array($pedidoPendente) && isset($pedidoPendente['token'])
    ? $pedidoPendente['token']
    : '';
$temaQr = in_array($_SESSION[$chaveTema] ?? '', ['light', 'dark'], true)
    ? $_SESSION[$chaveTema]
    : 'light';

if ($pagamentoConcluido) {
    unset($_SESSION[$chaveTema]);
}
?>
<!DOCTYPE html>
<html lang="pt-BR" data-theme="<?= htmlspecialchars($temaQr, ENT_QUOTES, 'UTF-8') ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="color-scheme" content="light dark">
    <meta name="description" content="Revise seu pedido e veja o QR Code demonstrativo da Virtual Motors.">
    <title><?= $pagamentoConcluido ? 'Pagamento realizado' : 'Pagamento por QR Code' ?> - Virtual Motors</title>
    <link rel="stylesheet" href="../public/style.css">
    <link rel="icon" href="../assets/vm.png" type="image/png">
</head>
<body class="<?= $pagamentoConcluido ? 'qr-payment-success-page' : 'qr-payment-page' ?>">
    <?php if (!$pagamentoConcluido) : ?>
        <iframe
            class="qr-payment-checkout-background"
            src="pagamento.php?visualizacao=1&amp;tema=<?= htmlspecialchars($temaQr, ENT_QUOTES, 'UTF-8') ?>"
            title="Página de pagamento em segundo plano"
            aria-hidden="true"
            tabindex="-1"
        ></iframe>
    <?php endif; ?>

    <main class="qr-payment-main<?= $pagamentoConcluido ? '' : ' qr-payment-overlay' ?>">
        <section
            class="qr-payment-card<?= $pagamentoConcluido ? '' : ' qr-payment-modal' ?>"
            aria-labelledby="qrPaymentTitle"
            <?= $pagamentoConcluido ? '' : 'role="dialog" aria-modal="true"' ?>
        >
            <?php if ($pagamentoConcluido) : ?>
                <h1 id="qrPaymentTitle">Pagamento realizado</h1>
                <p class="qr-payment-success" role="status" aria-live="polite">
                    Compra #<?= (int) ($_SESSION['virtual_motors_ultimo_pedido'] ?? 0) ?> confirmada. Obrigado pela preferência!
                </p>
                <p>Seu pedido já está disponível no histórico de compras.</p>
                <a class="button button-primary" href="minhas-compras.php">Consultar minhas compras</a>
                <a class="button button-primary" href="../index.php">Ir para a página inicial</a>
            <?php else : ?>
                <span class="qr-payment-brand">Virtual Motors</span>
                <a class="qr-payment-back" href="pagamento.php">← Voltar ao pagamento</a>
                <h1 id="qrPaymentTitle">Realize o pagamento</h1>
                <p>Escaneie o QR Code para continuar com seu pedido.</p>
                <div class="qr-payment-image-frame">
                    <img
                        class="qr-payment-image"
                        src="../assets/qrcode-pix.webp"
                        alt="QR Code ilustrativo para pagamento"
                        width="512"
                        height="342"
                    >
                </div>
                <p class="qr-payment-notice" role="note">
                    Esta é uma demonstração: o QR Code não processa pagamentos reais.
                </p>
                <?php if ($mensagemErro !== null) : ?>
                    <p class="cart-message cart-error" role="alert">
                        <?= htmlspecialchars($mensagemErro, ENT_QUOTES, 'UTF-8') ?>
                    </p>
                    <a class="button button-outline" href="pagamento.php">Voltar ao pagamento</a>
                <?php else : ?>
                    <form method="post" action="qrcode.php">
                        <input type="hidden" name="acao" value="confirmar">
                        <input
                            type="hidden"
                            name="token"
                            value="<?= htmlspecialchars($token, ENT_QUOTES, 'UTF-8') ?>"
                        >
                        <button class="button button-primary" type="submit">Confirmar pagamento</button>
                    </form>
                <?php endif; ?>
            <?php endif; ?>
        </section>
    </main>

    <script src="../public/script.js" defer></script>
</body>
</html>
