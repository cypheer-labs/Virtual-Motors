<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/admin_guard.php';
require_once __DIR__ . '/../src/userrepository.php';

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
        <?php require __DIR__ . '/../src/gtm.php'; ?>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>Usuários indisponíveis · Virtual Motors</title>
        <link rel="stylesheet" href="admin.css">
    </head>
    <body class="admin-layout">
        <main class="admin-main">
            <h1>Usuários indisponíveis</h1>
            <p class="admin-state error" role="alert">Não foi possível verificar seu acesso ou conectar ao banco de dados. Tente novamente mais tarde.</p>
        </main>
    </body>
    </html>
    <?php
    exit;
}

function usuarios_escape(mixed $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function usuarios_post_string(string $key): string
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
$usuarioEditando = null;
$valoresFormulario = [
    'nome' => '',
    'email' => '',
    'telefone' => '',
    'endereco' => '',
    'tipo_usuario' => 'cliente',
];

$mensagemFlash = $_SESSION['admin_usuarios_mensagem'] ?? null;
unset($_SESSION['admin_usuarios_mensagem']);
if (is_string($mensagemFlash)) {
    $mensagem = $mensagemFlash;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $tokenEnviado = usuarios_post_string('csrf_token');
    if (!hash_equals($csrfToken, $tokenEnviado)) {
        $mensagem = 'A sessão do formulário expirou. Atualize a página e tente novamente.';
        $erro = true;
    } else {
        $acao = usuarios_post_string('acao');

        try {
            if ($acao === 'excluir') {
                $idUsuario = filter_var($_POST['id_usuario'] ?? null, FILTER_VALIDATE_INT);
                if ($idUsuario === false || $idUsuario === null || $idUsuario < 1) {
                    throw new InvalidArgumentException('Usuário inválido.');
                }
                if ($idUsuario === (int) $_SESSION['usuario_id']) {
                    throw new DomainException('Você não pode excluir a própria conta enquanto estiver conectado.');
                }

                $referencia = $pdo->prepare(
                    'SELECT 1 FROM pedido WHERE id_usuario = :id_usuario LIMIT 1'
                );
                $referencia->execute(['id_usuario' => $idUsuario]);
                if ($referencia->fetchColumn() !== false) {
                    throw new DomainException('Este usuário possui pedidos registrados e não pode ser excluído.');
                }

                $pdo->beginTransaction();
                $administradores = $pdo->query(
                    "SELECT id_usuario FROM usuario WHERE tipo_usuario = 'admin' FOR UPDATE"
                )->fetchAll(PDO::FETCH_COLUMN);
                $alvo = $pdo->prepare('SELECT tipo_usuario FROM usuario WHERE id_usuario = :id_usuario');
                $alvo->execute(['id_usuario' => $idUsuario]);
                $tipoAlvo = $alvo->fetchColumn();

                if ($tipoAlvo === false) {
                    throw new DomainException('O usuário não foi encontrado ou já foi removido.');
                }
                if ($tipoAlvo === 'admin' && count($administradores) <= 1) {
                    throw new DomainException('O último administrador não pode ser excluído.');
                }

                $excluir = $pdo->prepare('DELETE FROM usuario WHERE id_usuario = :id_usuario');
                $excluir->execute(['id_usuario' => $idUsuario]);
                $pdo->commit();

                $_SESSION['admin_usuarios_mensagem'] = 'Usuário excluído com sucesso.';
                header('Location: usuarios.php');
                exit;
            }

            if ($acao !== 'criar' && $acao !== 'atualizar') {
                throw new InvalidArgumentException('Ação inválida.');
            }

            $valoresFormulario = [
                'nome' => usuarios_post_string('nome'),
                'email' => usuarios_post_string('email'),
                'telefone' => usuarios_post_string('telefone'),
                'endereco' => usuarios_post_string('endereco'),
                'tipo_usuario' => usuarios_post_string('tipo_usuario'),
            ];
            $senhaEnviada = $_POST['senha'] ?? '';
            $senha = is_string($senhaEnviada) ? $senhaEnviada : '';
            $idUsuario = filter_var($_POST['id_usuario'] ?? null, FILTER_VALIDATE_INT);

            if ($acao === 'atualizar' && ($idUsuario === false || $idUsuario === null || $idUsuario < 1)) {
                throw new InvalidArgumentException('Usuário inválido.');
            }
            if ($valoresFormulario['nome'] === '' || strlen($valoresFormulario['nome']) > 100) {
                throw new InvalidArgumentException('Informe um nome de até 100 caracteres.');
            }
            if (
                $valoresFormulario['email'] === ''
                || strlen($valoresFormulario['email']) > 100
                || filter_var($valoresFormulario['email'], FILTER_VALIDATE_EMAIL) === false
            ) {
                throw new InvalidArgumentException('Informe um e-mail válido de até 100 caracteres.');
            }
            if (strlen($valoresFormulario['telefone']) > 20) {
                throw new InvalidArgumentException('O telefone deve ter até 20 caracteres.');
            }
            if (strlen($valoresFormulario['endereco']) > 150) {
                throw new InvalidArgumentException('O endereço deve ter até 150 caracteres.');
            }
            if (!in_array($valoresFormulario['tipo_usuario'], ['cliente', 'admin'], true)) {
                throw new InvalidArgumentException('Selecione um perfil válido.');
            }
            if ($acao === 'criar' && (strlen($senha) < 8 || strlen($senha) > 72)) {
                throw new InvalidArgumentException('A senha deve ter entre 8 e 72 caracteres.');
            }
            if ($acao === 'atualizar' && $senha !== '' && (strlen($senha) < 8 || strlen($senha) > 72)) {
                throw new InvalidArgumentException('A nova senha deve ter entre 8 e 72 caracteres.');
            }
            if (
                $acao === 'atualizar'
                && $idUsuario === (int) $_SESSION['usuario_id']
                && $valoresFormulario['tipo_usuario'] !== 'admin'
            ) {
                throw new DomainException('Você não pode remover o próprio perfil de administrador.');
            }

            $parametros = [
                'nome' => $valoresFormulario['nome'],
                'email' => $valoresFormulario['email'],
                'telefone' => $valoresFormulario['telefone'] !== '' ? $valoresFormulario['telefone'] : null,
                'endereco' => $valoresFormulario['endereco'] !== '' ? $valoresFormulario['endereco'] : null,
                'tipo_usuario' => $valoresFormulario['tipo_usuario'],
            ];

            if ($acao === 'criar') {
                $uidCriado = (new \VirtualMotors\Repository\UserRepository($pdo))->create(
                    $valoresFormulario['nome'],
                    $valoresFormulario['email'],
                    password_hash($senha, PASSWORD_DEFAULT),
                    $valoresFormulario['tipo_usuario'],
                    $valoresFormulario['telefone'],
                    $valoresFormulario['endereco'] !== '' ? $valoresFormulario['endereco'] : null
                );
                if ($uidCriado === null) {
                    throw new DomainException('Este e-mail já está cadastrado para outro usuário.');
                }
                $_SESSION['admin_usuarios_mensagem'] = 'Usuário cadastrado com sucesso. UID: ' . $uidCriado;
            } else {
                $existencia = $pdo->prepare(
                    'SELECT tipo_usuario FROM usuario WHERE id_usuario = :id_usuario LIMIT 1'
                );
                $existencia->execute(['id_usuario' => $idUsuario]);
                $tipoAtual = $existencia->fetchColumn();
                if ($tipoAtual === false) {
                    throw new DomainException('O usuário não foi encontrado ou já foi removido.');
                }
                if ($tipoAtual === 'admin' && $valoresFormulario['tipo_usuario'] !== 'admin') {
                    $pdo->beginTransaction();
                    $administradores = $pdo->query(
                        "SELECT id_usuario FROM usuario WHERE tipo_usuario = 'admin' FOR UPDATE"
                    )->fetchAll(PDO::FETCH_COLUMN);
                    if (count($administradores) <= 1) {
                        throw new DomainException('O último administrador não pode perder o perfil administrativo.');
                    }
                }

                $parametros['id_usuario'] = $idUsuario;
                if ($senha !== '') {
                    $parametros['senha'] = password_hash($senha, PASSWORD_DEFAULT);
                    $sql = 'UPDATE usuario SET nome = :nome, email = :email, telefone = :telefone, '
                        . 'endereco = :endereco, tipo_usuario = :tipo_usuario, senha = :senha, '
                        . 'auth_version = auth_version + 1 WHERE id_usuario = :id_usuario';
                } else {
                    $sql = 'UPDATE usuario SET nome = :nome, email = :email, telefone = :telefone, endereco = :endereco, tipo_usuario = :tipo_usuario WHERE id_usuario = :id_usuario';
                }
                $salvar = $pdo->prepare($sql);
                $salvar->execute($parametros);
                if ($pdo->inTransaction()) {
                    $pdo->commit();
                }
                if ($idUsuario === (int) $_SESSION['usuario_id']) {
                    $_SESSION['usuario_email'] = $valoresFormulario['email'];
                    $_SESSION['usuario_nome'] = $valoresFormulario['nome'];
                }
                $_SESSION['admin_usuarios_mensagem'] = 'Usuário atualizado com sucesso.';
            }

            header('Location: usuarios.php');
            exit;
        } catch (InvalidArgumentException | DomainException $exception) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            $mensagem = $exception->getMessage();
            $erro = true;
        } catch (PDOException $exception) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            error_log($exception->getMessage());
            $mensagem = $exception->getCode() === '23000'
                ? 'Este e-mail já está cadastrado para outro usuário.'
                : 'Não foi possível salvar a alteração no banco de dados.';
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
    $clausulaBusca = ' WHERE nome LIKE :nome OR email LIKE :email OR telefone LIKE :telefone '
        . 'OR endereco LIKE :endereco OR CAST(uid AS CHAR) LIKE :uid';
    $termo = '%' . $busca . '%';
    $parametrosBusca = [
        'nome' => $termo,
        'email' => $termo,
        'telefone' => $termo,
        'endereco' => $termo,
        'uid' => $termo,
    ];
}

try {
    $contagem = $pdo->prepare('SELECT COUNT(*) FROM usuario' . $clausulaBusca);
    $contagem->execute($parametrosBusca);
    $totalUsuarios = (int) $contagem->fetchColumn();
    $totalPaginas = max(1, (int) ceil($totalUsuarios / $porPagina));
    $pagina = min($pagina, $totalPaginas);
    $offset = ($pagina - 1) * $porPagina;

    $consultaUsuarios = $pdo->prepare(
        'SELECT id_usuario, uid, nome, email, telefone, endereco, tipo_usuario FROM usuario'
        . $clausulaBusca
        . ' ORDER BY id_usuario DESC LIMIT ' . $porPagina . ' OFFSET ' . $offset
    );
    $consultaUsuarios->execute($parametrosBusca);
    $usuarios = $consultaUsuarios->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $exception) {
    error_log($exception->getMessage());
    $usuarios = [];
    $totalUsuarios = 0;
    $totalPaginas = 1;
    $pagina = 1;
    $mensagem = 'Não foi possível carregar os usuários.';
    $erro = true;
}

$idEditarEnviado = filter_var($_GET['editar'] ?? null, FILTER_VALIDATE_INT);
if ($idEditarEnviado !== false && $idEditarEnviado !== null && $idEditarEnviado > 0) {
    try {
        $consultaEdicao = $pdo->prepare(
            'SELECT id_usuario, uid, nome, email, telefone, endereco, tipo_usuario FROM usuario WHERE id_usuario = :id_usuario'
        );
        $consultaEdicao->execute(['id_usuario' => $idEditarEnviado]);
        $usuarioEditando = $consultaEdicao->fetch(PDO::FETCH_ASSOC) ?: null;
        if ($usuarioEditando === null && !$erro) {
            $mensagem = 'O usuário solicitado não foi encontrado.';
            $erro = true;
        } elseif ($_SERVER['REQUEST_METHOD'] !== 'POST' || usuarios_post_string('acao') !== 'atualizar') {
            $valoresFormulario = [
                'nome' => (string) ($usuarioEditando['nome'] ?? ''),
                'email' => (string) ($usuarioEditando['email'] ?? ''),
                'telefone' => (string) ($usuarioEditando['telefone'] ?? ''),
                'endereco' => (string) ($usuarioEditando['endereco'] ?? ''),
                'tipo_usuario' => (string) ($usuarioEditando['tipo_usuario'] ?? 'cliente'),
            ];
        }
    } catch (PDOException $exception) {
        error_log($exception->getMessage());
        $mensagem = 'Não foi possível carregar o usuário solicitado.';
        $erro = true;
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && usuarios_post_string('acao') === 'atualizar') {
    $idFormulario = filter_var($_POST['id_usuario'] ?? null, FILTER_VALIDATE_INT);
    if ($idFormulario !== false && $idFormulario !== null && $idFormulario > 0) {
        $usuarioEditando = [
            'id_usuario' => $idFormulario,
            'uid' => (string) ($usuarioEditando['uid'] ?? ''),
        ] + $valoresFormulario;
    }
}

$paginaAnterior = $pagina > 1 ? $pagina - 1 : null;
$proximaPagina = $pagina < $totalPaginas ? $pagina + 1 : null;
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <?php require __DIR__ . '/../src/gtm.php'; ?>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="color-scheme" content="light dark">
    <title>Usuários · Virtual Motors</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Barlow:wght@400;500;600&family=Barlow+Semi+Condensed:wght@600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="admin.css">
</head>
<body class="admin-layout">
<nav class="admin-nav" aria-label="Navegação administrativa">
    <p class="marca">Virtual Motors</p>
    <a href="dashboard.php">Painel</a>
    <a href="produtos.php">Produtos</a>
    <a href="usuarios.php" aria-current="page">Usuários</a>
    <a href="pedidos.php">Pedidos</a>
    <a class="sair" href="../index.php">Voltar à loja</a>
</nav>

<main class="admin-main">
    <div class="admin-page-actions">
        <a class="admin-button secondary" href="../index.php">← Voltar à página inicial</a>
    </div>
    <h1>Gerenciar usuários</h1>
    <p class="admin-sub">Cadastre, pesquise e atualize as contas e permissões da loja.</p>

    <?php if ($mensagem !== '') : ?>
        <p class="admin-message<?= $erro ? ' error' : '' ?>" role="<?= $erro ? 'alert' : 'status' ?>"><?= usuarios_escape($mensagem) ?></p>
    <?php endif; ?>

    <section class="admin-section">
        <div class="admin-section-heading">
            <h2><?= $usuarioEditando !== null ? 'Editar usuário' : 'Cadastrar usuário' ?></h2>
            <?php if ($usuarioEditando !== null) : ?>
                <a href="usuarios.php">Cancelar edição</a>
            <?php endif; ?>
        </div>
        <form class="admin-form" method="post" action="usuarios.php<?= $usuarioEditando !== null ? '?editar=' . (int) $usuarioEditando['id_usuario'] : '' ?>">
            <input type="hidden" name="csrf_token" value="<?= usuarios_escape($csrfToken) ?>">
            <input type="hidden" name="acao" value="<?= $usuarioEditando !== null ? 'atualizar' : 'criar' ?>">
            <?php if ($usuarioEditando !== null) : ?>
                <input type="hidden" name="id_usuario" value="<?= (int) $usuarioEditando['id_usuario'] ?>">
                <div class="admin-field">
                    <label for="uid_usuario">UID</label>
                    <input id="uid_usuario" value="<?= usuarios_escape($usuarioEditando['uid'] ?? '') ?>" readonly>
                    <small>Identificador numérico exclusivo da conta.</small>
                </div>
            <?php endif; ?>
            <div class="admin-field">
                <label for="nome">Nome</label>
                <input id="nome" name="nome" maxlength="100" required value="<?= usuarios_escape($valoresFormulario['nome']) ?>">
            </div>
            <div class="admin-field">
                <label for="email">E-mail</label>
                <input id="email" name="email" type="email" maxlength="100" required value="<?= usuarios_escape($valoresFormulario['email']) ?>">
            </div>
            <div class="admin-field">
                <label for="telefone">Telefone</label>
                <input id="telefone" name="telefone" maxlength="20" value="<?= usuarios_escape($valoresFormulario['telefone']) ?>">
            </div>
            <div class="admin-field">
                <label for="endereco">Endereço</label>
                <input id="endereco" name="endereco" maxlength="150" value="<?= usuarios_escape($valoresFormulario['endereco']) ?>">
            </div>
            <div class="admin-field">
                <label for="tipo_usuario">Perfil</label>
                <select id="tipo_usuario" name="tipo_usuario" required>
                    <option value="cliente"<?= $valoresFormulario['tipo_usuario'] === 'cliente' ? ' selected' : '' ?>>Cliente</option>
                    <option value="admin"<?= $valoresFormulario['tipo_usuario'] === 'admin' ? ' selected' : '' ?>>Administrador</option>
                </select>
            </div>
            <div class="admin-field">
                <label for="senha"><?= $usuarioEditando !== null ? 'Nova senha (opcional)' : 'Senha' ?></label>
                <input id="senha" name="senha" type="password" minlength="8" maxlength="72" autocomplete="new-password"<?= $usuarioEditando !== null ? '' : ' required' ?>>
                <small><?= $usuarioEditando !== null ? 'Deixe em branco para manter a senha atual.' : 'Use entre 8 e 72 caracteres.' ?></small>
            </div>
            <div class="admin-actions">
                <button class="admin-button" type="submit"><?= $usuarioEditando !== null ? 'Salvar alterações' : 'Cadastrar usuário' ?></button>
            </div>
        </form>
    </section>

    <section class="admin-section">
        <div class="admin-section-heading">
            <h2>Usuários cadastrados (<?= $totalUsuarios ?>)</h2>
        </div>
        <form class="admin-form" method="get" action="usuarios.php" role="search">
            <div class="admin-field">
                <label for="busca">Pesquisar por nome, UID, e-mail, telefone ou endereço</label>
                <input id="busca" name="busca" value="<?= usuarios_escape($busca) ?>">
            </div>
            <div class="admin-actions">
                <button class="admin-button" type="submit">Pesquisar</button>
                <?php if ($busca !== '') : ?>
                    <a class="admin-button secondary" href="usuarios.php">Limpar busca</a>
                <?php endif; ?>
            </div>
        </form>

        <?php if ($usuarios === []) : ?>
            <p class="admin-state"><?= $busca !== '' ? 'Nenhum usuário corresponde à pesquisa.' : 'Nenhum usuário cadastrado ainda.' ?></p>
        <?php else : ?>
            <div class="admin-table-wrap">
                <table class="admin-table">
                    <thead>
                        <tr>
                            <th scope="col">ID</th>
                            <th scope="col">UID</th>
                            <th scope="col">Nome</th>
                            <th scope="col">E-mail</th>
                            <th scope="col">Telefone</th>
                            <th scope="col">Endereço</th>
                            <th scope="col">Perfil</th>
                            <th scope="col">Ações</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($usuarios as $usuario) : ?>
                        <tr>
                            <td><?= (int) $usuario['id_usuario'] ?></td>
                            <td><?= usuarios_escape($usuario['uid']) ?></td>
                            <td><?= usuarios_escape($usuario['nome']) ?></td>
                            <td><?= usuarios_escape($usuario['email']) ?></td>
                            <td><?= usuarios_escape($usuario['telefone'] ?? '') ?: '—' ?></td>
                            <td><?= usuarios_escape($usuario['endereco'] ?? '') ?: '—' ?></td>
                            <td><?= $usuario['tipo_usuario'] === 'admin' ? 'Administrador' : 'Cliente' ?></td>
                            <td>
                                <div class="admin-actions">
                                    <a class="admin-button secondary" href="usuarios.php?editar=<?= (int) $usuario['id_usuario'] ?>">Editar</a>
                                    <?php if ((int) $usuario['id_usuario'] !== (int) $_SESSION['usuario_id']) : ?>
                                        <form method="post" action="usuarios.php" onsubmit="return confirm('Tem certeza que deseja excluir este usuário?');">
                                            <input type="hidden" name="csrf_token" value="<?= usuarios_escape($csrfToken) ?>">
                                            <input type="hidden" name="acao" value="excluir">
                                            <input type="hidden" name="id_usuario" value="<?= (int) $usuario['id_usuario'] ?>">
                                            <button class="admin-button danger" type="submit">Excluir</button>
                                        </form>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>

        <?php if ($totalPaginas > 1) : ?>
            <nav class="admin-pagination" aria-label="Paginação de usuários">
                <?php if ($paginaAnterior !== null) : ?>
                    <a class="admin-button secondary" href="usuarios.php?<?= http_build_query(['busca' => $busca, 'pagina' => $paginaAnterior]) ?>">Anterior</a>
                <?php endif; ?>
                <span>Página <?= $pagina ?> de <?= $totalPaginas ?></span>
                <?php if ($proximaPagina !== null) : ?>
                    <a class="admin-button secondary" href="usuarios.php?<?= http_build_query(['busca' => $busca, 'pagina' => $proximaPagina]) ?>">Próxima</a>
                <?php endif; ?>
            </nav>
        <?php endif; ?>
    </section>
</main>
</body>
</html>
