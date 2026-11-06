<?php

use VirtualMotors\Repository\CarroRepository;
use VirtualMotors\Repository\CarrinhoRepository;

require_once __DIR__ . '/database.php';
require_once __DIR__ . '/carrorepository.php';
require_once __DIR__ . '/carrinhorepository.php';
require_once __DIR__ . '/authsession.php';

session_start();

validar_sessao_usuario();

$chaveCarrinho = 'virtual_motors_carrinho';
if (!isset($_SESSION[$chaveCarrinho]) || !is_array($_SESSION[$chaveCarrinho])) {
    $_SESSION[$chaveCarrinho] = [];
}

$idUsuarioSessao = filter_var($_SESSION['usuario_id'] ?? null, FILTER_VALIDATE_INT);
$usuarioAutenticado = is_int($idUsuarioSessao) && $idUsuarioSessao > 0;
$idUsuario = $usuarioAutenticado ? $idUsuarioSessao : 0;
$carrinhoRepository = null;
if ($usuarioAutenticado) {
    try {
        $carrinhoRepository = new CarrinhoRepository();
        if ((int) ($_SESSION['virtual_motors_carrinho_usuario_id'] ?? 0) !== $idUsuario) {
            $_SESSION[$chaveCarrinho] = $carrinhoRepository->combinar(
                $carrinhoRepository->buscar($idUsuario),
                $_SESSION[$chaveCarrinho]
            );
            $_SESSION['virtual_motors_carrinho_usuario_id'] = $idUsuario;
            $carrinhoRepository->salvar($idUsuario, $_SESSION[$chaveCarrinho]);
        }
    } catch (Throwable $erro) {
        error_log($erro->getMessage());
        $_SESSION['virtual_motors_carrinho_mensagem'] =
            'Não foi possível sincronizar seu carrinho. Tente novamente; seus itens desta sessão continuam disponíveis.';
    }
}

$mensagem = $_SESSION['virtual_motors_carrinho_mensagem'] ?? '';
unset($_SESSION['virtual_motors_carrinho_mensagem']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $acao = $_POST['acao'] ?? '';

    $compraAdicionada = false;

    if (in_array($acao, ['adicionar', 'comprar'], true)) {
        $idCarro = filter_var($_POST['id_carro'] ?? '', FILTER_VALIDATE_INT);

        if ($idCarro === false || $idCarro < 1) {
            $_SESSION['virtual_motors_carrinho_mensagem'] = 'Não foi possível identificar o veículo.';
        } else {
            try {
                $veiculos = (new CarroRepository())->buscarPorIds([$idCarro]);
                if ($veiculos === []) {
                    $_SESSION['virtual_motors_carrinho_mensagem'] = 'Este veículo não está mais disponível.';
                } else {
                    $idCarro = (int) $veiculos[0]['id_carro'];
                    $quantidadeAtual = (int) ($_SESSION[$chaveCarrinho][$idCarro] ?? 0);
                    $estoqueDisponivel = (int) $veiculos[0]['estoque'];
                    if ($quantidadeAtual >= $estoqueDisponivel) {
                        $_SESSION['virtual_motors_carrinho_mensagem'] =
                            'A quantidade solicitada excede o estoque disponível.';
                    } else {
                        $_SESSION[$chaveCarrinho][$idCarro] = $quantidadeAtual + 1;
                        $_SESSION['virtual_motors_carrinho_mensagem'] = 'Veículo adicionado ao carrinho.';
                        $compraAdicionada = true;
                    }
                }
            } catch (PDOException $erro) {
                error_log($erro->getMessage());
                $_SESSION['virtual_motors_carrinho_mensagem'] = 'Não foi possível consultar o veículo. Tente novamente.';
            }
        }
    } elseif ($acao === 'remover') {
        $idCarro = filter_var($_POST['id_carro'] ?? '', FILTER_VALIDATE_INT);
        if ($idCarro !== false) {
            unset($_SESSION[$chaveCarrinho][$idCarro]);
            $_SESSION['virtual_motors_carrinho_mensagem'] = 'Veículo removido do carrinho.';
        }
    } elseif ($acao === 'atualizar') {
        $quantidades = $_POST['quantidade'] ?? [];
        if (is_array($quantidades) && $_SESSION[$chaveCarrinho] !== []) {
            try {
                $veiculosNoCarrinho = (new CarroRepository())->buscarPorIds(
                    array_keys($_SESSION[$chaveCarrinho])
                );
                $estoques = [];
                foreach ($veiculosNoCarrinho as $veiculo) {
                    $estoques[(int) $veiculo['id_carro']] = (int) $veiculo['estoque'];
                }

                $excedeEstoque = false;
                foreach ($quantidades as $id => $quantidadeEnviada) {
                    $idCarro = filter_var($id, FILTER_VALIDATE_INT);
                    $quantidade = filter_var($quantidadeEnviada, FILTER_VALIDATE_INT);
                    if (
                        $idCarro === false
                        || !array_key_exists($idCarro, $_SESSION[$chaveCarrinho])
                        || $quantidade === false
                    ) {
                        continue;
                    }

                    if ($quantidade < 1) {
                        unset($_SESSION[$chaveCarrinho][$idCarro]);
                    } elseif ($quantidade > ($estoques[$idCarro] ?? 0)) {
                        $excedeEstoque = true;
                    } else {
                        $_SESSION[$chaveCarrinho][$idCarro] = $quantidade;
                    }
                }
                $_SESSION['virtual_motors_carrinho_mensagem'] = $excedeEstoque
                    ? 'Uma ou mais quantidades excedem o estoque. Revise os itens antes de continuar.'
                    : 'Carrinho atualizado.';
            } catch (PDOException $erro) {
                error_log($erro->getMessage());
                $_SESSION['virtual_motors_carrinho_mensagem'] =
                    'Não foi possível validar o estoque. Tente novamente.';
            }
        } else {
            $_SESSION['virtual_motors_carrinho_mensagem'] = 'Carrinho atualizado.';
        }
    }

    if ($usuarioAutenticado) {
        if ($carrinhoRepository !== null) {
            try {
                $carrinhoRepository->salvar($idUsuario, $_SESSION[$chaveCarrinho]);
            } catch (Throwable $erro) {
                error_log($erro->getMessage());
                $_SESSION['virtual_motors_carrinho_mensagem'] =
                    'Não foi possível salvar as alterações do carrinho na sua conta. Tente novamente.';
            }
        } else {
            $_SESSION['virtual_motors_carrinho_mensagem'] =
                'Não foi possível salvar as alterações do carrinho na sua conta. Tente novamente.';
        }
    }

    header('Location: ' . ($acao === 'comprar' && $compraAdicionada ? 'pagamento.php' : 'carrinho.php'));
    exit;
}

$veiculos = [];
$erroBanco = false;
if ($_SESSION[$chaveCarrinho] !== []) {
    try {
        $veiculos = (new CarroRepository())->buscarPorIds(array_keys($_SESSION[$chaveCarrinho]));
    } catch (PDOException $erro) {
        error_log($erro->getMessage());
        http_response_code(500);
        $erroBanco = true;
    }
}

$carrinhoComEstoqueInsuficiente = false;
foreach ($veiculos as $veiculo) {
    $idCarro = (int) $veiculo['id_carro'];
    if ((int) ($_SESSION[$chaveCarrinho][$idCarro] ?? 0) > (int) $veiculo['estoque']) {
        $carrinhoComEstoqueInsuficiente = true;
        break;
    }
}

$totalCarrinho = 0;
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <?php require __DIR__ . '/gtm.php'; ?>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="color-scheme" content="light dark">
    <title>Carrinho - Virtual Motors</title>
    <link rel="stylesheet" href="../public/style.css?v=profile-header-layout">
</head>
<body>
    <?php
    $headerBasePath = '../';
    require __DIR__ . '/header.php';
    ?>

    <main class="cart-wrapper">
        <div class="cart-title-area">
            <span>Seu carrinho</span>
            <h1>Carrinho de compras</h1>
            <p class="cart-persistence-note">
                <?php if ($usuarioAutenticado) : ?>
                    Seus itens ficam salvos na sua conta.
                <?php else : ?>
                    <a href="login.php">Entre na sua conta</a> para manter seus itens salvos.
                <?php endif; ?>
            </p>
        </div>

        <?php if ($mensagem !== '') : ?>
            <p class="cart-message" role="status"><?= htmlspecialchars($mensagem, ENT_QUOTES, 'UTF-8') ?></p>
        <?php endif; ?>
        <?php if ($carrinhoComEstoqueInsuficiente) : ?>
            <p class="cart-message cart-error" role="alert">
                Há itens sem estoque suficiente. Ajuste ou remova essas quantidades antes de continuar.
            </p>
        <?php endif; ?>

        <?php if ($erroBanco) : ?>
            <p class="cart-message cart-error" role="alert">Não foi possível carregar o carrinho. Verifique a conexão com o banco de dados.</p>
        <?php elseif ($veiculos === []) : ?>
            <div class="empty-cart">
                <p>Seu carrinho está vazio.</p>
                <a href="../index.php#veiculos" class="button button-primary">Ver veículos</a>
            </div>
        <?php else : ?>
            <form id="atualizar-carrinho" method="post" action="carrinho.php">
                <input type="hidden" name="acao" value="atualizar">
            </form>

            <div class="cart-layout">
                <section class="cart-items" aria-label="Itens do carrinho">
                    <?php foreach ($veiculos as $veiculo) : ?>
                        <?php
                        $idCarro = (int) $veiculo['id_carro'];
                        $quantidade = (int) ($_SESSION[$chaveCarrinho][$idCarro] ?? 1);
                        $subtotal = (float) $veiculo['preco'] * $quantidade;
                        $totalCarrinho += $subtotal;
                        ?>
                        <article class="cart-card">
                            <div class="cart-item-info">
                                <img class="cart-item-image" src="<?= htmlspecialchars($veiculo['imagem'], ENT_QUOTES, 'UTF-8') ?>" alt="Foto ilustrativa do veículo <?= htmlspecialchars($veiculo['nome'], ENT_QUOTES, 'UTF-8') ?>">
                                <div class="cart-item-details">
                                    <h2><?= htmlspecialchars($veiculo['nome'], ENT_QUOTES, 'UTF-8') ?></h2>
                                    <div class="cart-item-tags">
                                        <span class="tag"><?= htmlspecialchars($veiculo['jogo_origem'], ENT_QUOTES, 'UTF-8') ?></span>
                                        <?php if ($veiculo['cor'] !== null && $veiculo['cor'] !== '') : ?>
                                            <span class="tag"><?= htmlspecialchars($veiculo['cor'], ENT_QUOTES, 'UTF-8') ?></span>
                                        <?php endif; ?>
                                    </div>
                                    <p class="cart-stock">Disponível: <?= (int) $veiculo['estoque'] ?> unidade(s)</p>
                                    <label class="cart-quantity">
                                        Quantidade
                                        <input
                                            form="atualizar-carrinho"
                                            type="number"
                                            name="quantidade[<?= $idCarro ?>]"
                                            min="0"
                                            max="<?= (int) $veiculo['estoque'] ?>"
                                            step="1"
                                            value="<?= $quantidade ?>"
                                        >
                                    </label>
                                </div>
                            </div>
                            <div class="cart-item-right">
                                <strong class="cart-item-price">R$ <?= number_format($subtotal, 2, ',', '.') ?></strong>
                                <form method="post" action="carrinho.php">
                                    <input type="hidden" name="acao" value="remover">
                                    <input type="hidden" name="id_carro" value="<?= $idCarro ?>">
                                    <button class="btn-remove" type="submit" aria-label="Remover <?= htmlspecialchars($veiculo['nome'], ENT_QUOTES, 'UTF-8') ?>">Remover</button>
                                </form>
                            </div>
                        </article>
                    <?php endforeach; ?>
                    <button form="atualizar-carrinho" class="button button-primary" type="submit">Atualizar quantidades</button>
                </section>

                <aside class="order-summary">
                    <h2>Resumo do pedido</h2>
                    <div class="summary-row">
                        <span>Total</span>
                        <strong>R$ <?= number_format($totalCarrinho, 2, ',', '.') ?></strong>
                    </div>
                    <p>Revise seus itens e informe o endereço na próxima etapa.</p>
                    <?php if ($carrinhoComEstoqueInsuficiente) : ?>
                        <span class="button button-primary cart-checkout-disabled" aria-disabled="true">
                            Estoque insuficiente
                        </span>
                    <?php else : ?>
                        <a href="pagamento.php" class="button button-primary">Ir para pagamento</a>
                    <?php endif; ?>
                    <a href="../index.php#veiculos" class="cart-continue-link">Continuar comprando</a>
                </aside>
            </div>
        <?php endif; ?>
    </main>

    <script src="../public/script.js" defer></script>
</body>
</html>
