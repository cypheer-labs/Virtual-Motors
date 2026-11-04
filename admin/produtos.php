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
        <title>Produtos indisponíveis · Virtual Motors</title>
        <link rel="stylesheet" href="admin.css">
    </head>
    <body class="admin-layout">
        <main class="admin-main">
            <h1>Produtos indisponíveis</h1>
            <p class="admin-state error" role="alert">Não foi possível verificar seu acesso ou conectar ao banco de dados. Tente novamente mais tarde.</p>
        </main>
    </body>
    </html>
    <?php
    exit;
}

function produtos_escape(mixed $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function produtos_post_string(string $key): string
{
    $value = $_POST[$key] ?? '';

    return is_string($value) ? trim($value) : '';
}

function produtos_imagem_url_valida(string $url): bool
{
    if (filter_var($url, FILTER_VALIDATE_URL) === false) {
        return false;
    }

    $esquema = strtolower((string) parse_url($url, PHP_URL_SCHEME));

    return in_array($esquema, ['http', 'https'], true);
}

$csrfToken = $_SESSION['admin_csrf_token'] ?? null;
if (!is_string($csrfToken) || $csrfToken === '') {
    $csrfToken = bin2hex(random_bytes(32));
    $_SESSION['admin_csrf_token'] = $csrfToken;
}

$mensagem = '';
$erro = false;
$produtoEditando = null;
$valoresFormulario = [
    'nome' => '',
    'cor' => '',
    'preco' => '',
    'jogo_origem' => '',
    'imagem_url' => '',
];

$mensagemFlash = $_SESSION['admin_produtos_mensagem'] ?? null;
unset($_SESSION['admin_produtos_mensagem']);
if (is_string($mensagemFlash)) {
    $mensagem = $mensagemFlash;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $tokenEnviado = produtos_post_string('csrf_token');
    if (!hash_equals($csrfToken, $tokenEnviado)) {
        $mensagem = 'A sessão do formulário expirou. Atualize a página e tente novamente.';
        $erro = true;
    } else {
        $acao = produtos_post_string('acao');

        try {
            if ($acao === 'excluir') {
                $idCarro = filter_var($_POST['id_carro'] ?? null, FILTER_VALIDATE_INT);
                if ($idCarro === false || $idCarro === null || $idCarro < 1) {
                    throw new InvalidArgumentException('Produto inválido.');
                }

                $referencia = $pdo->prepare(
                    'SELECT 1 FROM item_pedido WHERE id_carro = :id_carro LIMIT 1'
                );
                $referencia->execute(['id_carro' => $idCarro]);
                if ($referencia->fetchColumn() !== false) {
                    throw new DomainException('Este produto pertence ao histórico de pedidos e não pode ser excluído.');
                }

                $excluir = $pdo->prepare('DELETE FROM carros WHERE id_carro = :id_carro');
                $excluir->execute(['id_carro' => $idCarro]);
                if ($excluir->rowCount() === 0) {
                    throw new DomainException('O produto não foi encontrado ou já foi removido.');
                }

                $_SESSION['admin_produtos_mensagem'] = 'Produto excluído com sucesso.';
                header('Location: produtos.php');
                exit;
            }

            if ($acao !== 'criar' && $acao !== 'atualizar') {
                throw new InvalidArgumentException('Ação inválida.');
            }

            $valoresFormulario = [
                'nome' => produtos_post_string('nome'),
                'cor' => produtos_post_string('cor'),
                'preco' => produtos_post_string('preco'),
                'jogo_origem' => produtos_post_string('jogo_origem'),
                'imagem_url' => produtos_post_string('imagem_url'),
            ];
            $idCarro = filter_var($_POST['id_carro'] ?? null, FILTER_VALIDATE_INT);
            $precoNormalizado = str_replace(',', '.', $valoresFormulario['preco']);

            if ($acao === 'atualizar' && ($idCarro === false || $idCarro === null || $idCarro < 1)) {
                throw new InvalidArgumentException('Produto inválido.');
            }
            if ($valoresFormulario['nome'] === '' || strlen($valoresFormulario['nome']) > 100) {
                throw new InvalidArgumentException('Informe um nome de até 100 caracteres.');
            }
            if (strlen($valoresFormulario['cor']) > 30) {
                throw new InvalidArgumentException('A cor deve ter até 30 caracteres.');
            }
            if (!preg_match('/^\d{1,8}(?:\.\d{1,2})?$/', $precoNormalizado) || (float) $precoNormalizado <= 0) {
                throw new InvalidArgumentException('Informe um preço maior que zero, com até duas casas decimais.');
            }
            if ($valoresFormulario['jogo_origem'] === '' || strlen($valoresFormulario['jogo_origem']) > 100) {
                throw new InvalidArgumentException('Informe um jogo de origem de até 100 caracteres.');
            }
            if ($valoresFormulario['imagem_url'] !== '') {
                if (
                    strlen($valoresFormulario['imagem_url']) > 2048
                    || !produtos_imagem_url_valida($valoresFormulario['imagem_url'])
                ) {
                    throw new InvalidArgumentException('Informe uma URL de imagem válida começando com http:// ou https://.');
                }
            }

            $parametros = [
                'nome' => $valoresFormulario['nome'],
                'cor' => $valoresFormulario['cor'] !== '' ? $valoresFormulario['cor'] : null,
                'preco' => $precoNormalizado,
                'jogo_origem' => $valoresFormulario['jogo_origem'],
                'imagem_url' => $valoresFormulario['imagem_url'] !== '' ? $valoresFormulario['imagem_url'] : null,
            ];

            if ($acao === 'criar') {
                $salvar = $pdo->prepare(
                    'INSERT INTO carros (nome, cor, preco, jogo_origem, imagem_url) VALUES (:nome, :cor, :preco, :jogo_origem, :imagem_url)'
                );
                $salvar->execute($parametros);
                $_SESSION['admin_produtos_mensagem'] = 'Produto cadastrado com sucesso.';
            } else {
                $existencia = $pdo->prepare('SELECT 1 FROM carros WHERE id_carro = :id_carro LIMIT 1');
                $existencia->execute(['id_carro' => $idCarro]);
                if ($existencia->fetchColumn() === false) {
                    throw new DomainException('O produto não foi encontrado ou já foi removido.');
                }

                $parametros['id_carro'] = $idCarro;
                $salvar = $pdo->prepare(
                    'UPDATE carros SET nome = :nome, cor = :cor, preco = :preco, jogo_origem = :jogo_origem, imagem_url = :imagem_url WHERE id_carro = :id_carro'
                );
                $salvar->execute($parametros);
                $_SESSION['admin_produtos_mensagem'] = 'Produto atualizado com sucesso.';
            }

            header('Location: produtos.php');
            exit;
        } catch (InvalidArgumentException | DomainException $exception) {
            $mensagem = $exception->getMessage();
            $erro = true;
        } catch (PDOException $exception) {
            error_log($exception->getMessage());
            $mensagem = 'Não foi possível salvar a alteração no banco de dados.';
            $erro = true;
        }
    }
}

$buscaEnviada = $_GET['busca'] ?? '';
$busca = is_string($buscaEnviada) ? trim($buscaEnviada) : '';
$paginaEnviada = filter_var($_GET['pagina'] ?? 1, FILTER_VALIDATE_INT);
$pagina = $paginaEnviada === false || $paginaEnviada < 1 ? 1 : $paginaEnviada;
$porPagina = 50;
$clausulaBusca = '';
$parametrosBusca = [];
if ($busca !== '') {
    // A cláusula contém apenas SQL fixo; o texto da busca usa parâmetros vinculados.
    $clausulaBusca = ' WHERE nome LIKE :nome OR cor LIKE :cor OR jogo_origem LIKE :jogo_origem';
    $termo = '%' . $busca . '%';
    $parametrosBusca = [
        'nome' => $termo,
        'cor' => $termo,
        'jogo_origem' => $termo,
    ];
}

try {
    $contagem = $pdo->prepare('SELECT COUNT(*) FROM carros' . $clausulaBusca);
    $contagem->execute($parametrosBusca);
    $totalProdutos = (int) $contagem->fetchColumn();
    $totalPaginas = max(1, (int) ceil($totalProdutos / $porPagina));
    $pagina = min($pagina, $totalPaginas);
    $offset = ($pagina - 1) * $porPagina;

    $consultaProdutos = $pdo->prepare(
        'SELECT id_carro, nome, cor, preco, jogo_origem, imagem_url FROM carros'
        . $clausulaBusca
        . ' ORDER BY id_carro DESC LIMIT ' . $porPagina . ' OFFSET ' . $offset
    );
    $consultaProdutos->execute($parametrosBusca);
    $produtos = $consultaProdutos->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $exception) {
    error_log($exception->getMessage());
    $produtos = [];
    $totalProdutos = 0;
    $totalPaginas = 1;
    $pagina = 1;
    $mensagem = 'Não foi possível carregar os produtos.';
    $erro = true;
}

$idEditarEnviado = filter_var($_GET['editar'] ?? null, FILTER_VALIDATE_INT);
if ($idEditarEnviado !== false && $idEditarEnviado !== null && $idEditarEnviado > 0) {
    try {
        $consultaEdicao = $pdo->prepare(
            'SELECT id_carro, nome, cor, preco, jogo_origem, imagem_url FROM carros WHERE id_carro = :id_carro'
        );
        $consultaEdicao->execute(['id_carro' => $idEditarEnviado]);
        $produtoEditando = $consultaEdicao->fetch(PDO::FETCH_ASSOC) ?: null;
        if ($produtoEditando === null && !$erro) {
            $mensagem = 'O produto solicitado não foi encontrado.';
            $erro = true;
        } elseif ($_SERVER['REQUEST_METHOD'] !== 'POST' || produtos_post_string('acao') !== 'atualizar') {
            $valoresFormulario = [
                'nome' => (string) ($produtoEditando['nome'] ?? ''),
                'cor' => (string) ($produtoEditando['cor'] ?? ''),
                'preco' => (string) ($produtoEditando['preco'] ?? ''),
                'jogo_origem' => (string) ($produtoEditando['jogo_origem'] ?? ''),
                'imagem_url' => (string) ($produtoEditando['imagem_url'] ?? ''),
            ];
        }
    } catch (PDOException $exception) {
        error_log($exception->getMessage());
        $mensagem = 'Não foi possível carregar o produto solicitado.';
        $erro = true;
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && produtos_post_string('acao') === 'atualizar') {
    $idFormulario = filter_var($_POST['id_carro'] ?? null, FILTER_VALIDATE_INT);
    if ($idFormulario !== false && $idFormulario !== null && $idFormulario > 0) {
        $produtoEditando = ['id_carro' => $idFormulario] + $valoresFormulario;
    }
}

$paginaAnterior = $pagina > 1 ? $pagina - 1 : null;
$proximaPagina = $pagina < $totalPaginas ? $pagina + 1 : null;
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="color-scheme" content="light dark">
    <title>Produtos · Virtual Motors</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Barlow:wght@400;500;600&family=Barlow+Semi+Condensed:wght@600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="admin.css">
</head>
<body class="admin-layout">
<nav class="admin-nav" aria-label="Navegação administrativa">
    <p class="marca">Virtual Motors</p>
    <a href="dashboard.php">Painel</a>
    <a href="produtos.php" aria-current="page">Produtos</a>
    <a href="usuarios.php">Usuários</a>
    <a href="pedidos.php">Pedidos</a>
    <a class="sair" href="../index.php">Voltar à loja</a>
</nav>

<main class="admin-main">
    <div class="admin-page-actions">
        <a class="admin-button secondary" href="../index.php">← Voltar à página inicial</a>
    </div>
    <h1>Gerenciar produtos</h1>
    <p class="admin-sub">Cadastre, pesquise e atualize os carros disponíveis no catálogo.</p>

    <?php if ($mensagem !== '') : ?>
        <p class="admin-message<?= $erro ? ' error' : '' ?>" role="<?= $erro ? 'alert' : 'status' ?>"><?= produtos_escape($mensagem) ?></p>
    <?php endif; ?>

    <section class="admin-section">
        <div class="admin-section-heading">
            <h2><?= $produtoEditando !== null ? 'Editar produto' : 'Cadastrar produto' ?></h2>
            <?php if ($produtoEditando !== null) : ?>
                <a href="produtos.php">Cancelar edição</a>
            <?php endif; ?>
        </div>
        <form class="admin-form" method="post" action="produtos.php<?= $produtoEditando !== null ? '?editar=' . (int) $produtoEditando['id_carro'] : '' ?>">
            <input type="hidden" name="csrf_token" value="<?= produtos_escape($csrfToken) ?>">
            <input type="hidden" name="acao" value="<?= $produtoEditando !== null ? 'atualizar' : 'criar' ?>">
            <?php if ($produtoEditando !== null) : ?>
                <input type="hidden" name="id_carro" value="<?= (int) $produtoEditando['id_carro'] ?>">
            <?php endif; ?>
            <div class="admin-field">
                <label for="nome">Nome</label>
                <input id="nome" name="nome" maxlength="100" required value="<?= produtos_escape($valoresFormulario['nome']) ?>">
            </div>
            <div class="admin-field">
                <label for="cor">Cor</label>
                <input id="cor" name="cor" maxlength="30" value="<?= produtos_escape($valoresFormulario['cor']) ?>">
            </div>
            <div class="admin-field">
                <label for="preco">Preço (R$)</label>
                <input id="preco" name="preco" inputmode="decimal" required value="<?= produtos_escape($valoresFormulario['preco']) ?>" placeholder="Ex.: 250000.00">
            </div>
            <div class="admin-field">
                <label for="jogo_origem">Jogo de origem</label>
                <input id="jogo_origem" name="jogo_origem" maxlength="100" required value="<?= produtos_escape($valoresFormulario['jogo_origem']) ?>">
            </div>
            <div class="admin-field">
                <label for="imagem_url">Link da imagem (URL)</label>
                <input id="imagem_url" name="imagem_url" type="url" maxlength="2048" value="<?= produtos_escape($valoresFormulario['imagem_url']) ?>" placeholder="https://exemplo.com/carro.jpg">
                <small>Opcional. Use um link direto para uma imagem pública; se deixar vazio, o catálogo usa a imagem padrão.</small>
            </div>
            <div class="admin-actions">
                <button class="admin-button" type="submit"><?= $produtoEditando !== null ? 'Salvar alterações' : 'Cadastrar produto' ?></button>
            </div>
        </form>
    </section>

    <section class="admin-section">
        <div class="admin-section-heading">
            <h2>Produtos cadastrados (<?= $totalProdutos ?>)</h2>
        </div>
        <form class="admin-form" method="get" action="produtos.php" role="search">
            <div class="admin-field">
                <label for="busca">Pesquisar por nome, cor ou jogo</label>
                <input id="busca" name="busca" value="<?= produtos_escape($busca) ?>">
            </div>
            <div class="admin-actions">
                <button class="admin-button" type="submit">Pesquisar</button>
                <?php if ($busca !== '') : ?>
                    <a class="admin-button secondary" href="produtos.php">Limpar busca</a>
                <?php endif; ?>
            </div>
        </form>

        <?php if ($erro && $produtos === [] && $mensagem === 'Não foi possível carregar os produtos.') : ?>
            <p class="admin-state error" role="alert"><?= produtos_escape($mensagem) ?></p>
        <?php elseif ($produtos === []) : ?>
            <p class="admin-state"><?= $busca !== '' ? 'Nenhum produto corresponde à pesquisa.' : 'Nenhum produto cadastrado ainda.' ?></p>
        <?php else : ?>
            <div class="admin-table-wrap">
                <table class="admin-table">
                    <thead>
                    <tr>
                        <th scope="col">ID</th>
                        <th scope="col">Nome</th>
                        <th scope="col">Cor</th>
                        <th scope="col">Preço</th>
                        <th scope="col">Jogo de origem</th>
                        <th scope="col">Imagem</th>
                        <th scope="col">Ações</th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($produtos as $produto) : ?>
                        <tr>
                            <td><?= (int) $produto['id_carro'] ?></td>
                            <td><?= produtos_escape($produto['nome']) ?></td>
                            <td><?= produtos_escape($produto['cor'] ?? '') ?></td>
                            <td>R$ <?= number_format((float) $produto['preco'], 2, ',', '.') ?></td>
                            <td><?= produtos_escape($produto['jogo_origem']) ?></td>
                            <td>
                                <?php if (is_string($produto['imagem_url']) && produtos_imagem_url_valida($produto['imagem_url'])) : ?>
                                    <a class="admin-link" href="<?= produtos_escape($produto['imagem_url']) ?>" target="_blank" rel="noopener noreferrer">Ver imagem</a>
                                <?php else : ?>
                                    Sem imagem
                                <?php endif; ?>
                            </td>
                            <td>
                                <div class="admin-actions">
                                    <a class="admin-link" href="produtos.php?editar=<?= (int) $produto['id_carro'] ?>">Editar</a>
                                    <form method="post" action="produtos.php" onsubmit="return confirm('Deseja realmente excluir este produto?');">
                                        <input type="hidden" name="csrf_token" value="<?= produtos_escape($csrfToken) ?>">
                                        <input type="hidden" name="acao" value="excluir">
                                        <input type="hidden" name="id_carro" value="<?= (int) $produto['id_carro'] ?>">
                                        <button class="admin-button secondary" type="submit">Excluir</button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <div class="admin-actions" style="justify-content:space-between; margin-top:12px">
                <span>Página <?= $pagina ?> de <?= $totalPaginas ?></span>
                <div class="admin-actions">
                    <?php if ($paginaAnterior !== null) : ?>
                        <a class="admin-button secondary" href="produtos.php?<?= produtos_escape(http_build_query(['busca' => $busca, 'pagina' => $paginaAnterior])) ?>">Anterior</a>
                    <?php endif; ?>
                    <?php if ($proximaPagina !== null) : ?>
                        <a class="admin-button secondary" href="produtos.php?<?= produtos_escape(http_build_query(['busca' => $busca, 'pagina' => $proximaPagina])) ?>">Próxima</a>
                    <?php endif; ?>
                </div>
            </div>
        <?php endif; ?>
    </section>
</main>
</body>
</html>
