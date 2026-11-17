<?php

declare(strict_types=1);

use VirtualMotors\Repository\UserRepository;

require_once __DIR__ . '/database.php';
require_once __DIR__ . '/userrepository.php';
require_once __DIR__ . '/authsession.php';

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

try {
    $sessaoValida = validar_sessao_usuario();
} catch (PDOException | RuntimeException $erro) {
    error_log($erro->getMessage());
    http_response_code(503);
    exit('Não foi possível validar sua sessão agora. Tente novamente em instantes.');
}

if (!$sessaoValida) {
    header('Location: login.php');
    exit;
}

$idUsuario = filter_var($_SESSION['usuario_id'] ?? null, FILTER_VALIDATE_INT);
if ($idUsuario === false || $idUsuario === null || $idUsuario < 1) {
    header('Location: login.php');
    exit;
}

$usuarioRepository = null;
$conta = null;
$erroBanco = false;
try {
    $usuarioRepository = new UserRepository();
    $conta = $usuarioRepository->findAccountById($idUsuario);
} catch (PDOException | RuntimeException $erro) {
    error_log($erro->getMessage());
    http_response_code(503);
    $erroBanco = true;
}

if ($conta === null && !$erroBanco) {
    http_response_code(404);
}

$mensagemSucesso = $_SESSION['conta_sucesso'] ?? '';
unset($_SESSION['conta_sucesso']);
$mensagemErro = '';
$tokenSessao = $_SESSION['csrf_conta'] ?? null;
if (!is_string($tokenSessao) || $tokenSessao === '') {
    $tokenSessao = bin2hex(random_bytes(32));
    $_SESSION['csrf_conta'] = $tokenSessao;
}

$nomeFormulario = is_array($conta) ? (string) $conta['nome'] : '';
$emailFormulario = is_array($conta) ? (string) $conta['email'] : '';
$telefoneFormulario = is_array($conta) ? (string) ($conta['telefone'] ?? '') : '';
$enderecoFormulario = is_array($conta) ? (string) ($conta['endereco'] ?? '') : '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$erroBanco && $conta !== null && $usuarioRepository !== null) {
    $tokenFormulario = $_POST['csrf_token'] ?? '';
    $acaoEnviada = $_POST['acao'] ?? '';

    if (!is_string($tokenFormulario) || !hash_equals($tokenSessao, $tokenFormulario)) {
        http_response_code(400);
        $mensagemErro = 'Sua sessão expirou. Atualize a página e tente novamente.';
    } elseif (!is_string($acaoEnviada)) {
        http_response_code(400);
        $mensagemErro = 'Ação inválida.';
    } else {
        try {
            if ($acaoEnviada === 'atualizar_dados_pessoais') {
                $nomeEnviado = $_POST['nome'] ?? '';
                $nomeFormulario = is_string($nomeEnviado) ? trim($nomeEnviado) : '';
                if (!is_string($nomeEnviado) || $nomeFormulario === '' || mb_strlen($nomeFormulario, 'UTF-8') > 100) {
                    $mensagemErro = 'Informe um nome com até 100 caracteres.';
                } elseif (!$usuarioRepository->updatePersonalData($idUsuario, $nomeFormulario)) {
                    throw new RuntimeException('Não foi possível atualizar os dados pessoais.');
                } else {
                    $_SESSION['usuario_nome'] = $nomeFormulario;
                    $_SESSION['conta_sucesso'] = 'Seus dados pessoais foram atualizados.';
                    header('Location: minha-conta.php');
                    exit;
                }
            } elseif ($acaoEnviada === 'atualizar_contato') {
                $emailEnviado = $_POST['email'] ?? '';
                $telefoneEnviado = $_POST['telefone'] ?? '';
                $enderecoEnviado = $_POST['endereco'] ?? '';
                $emailFormulario = is_string($emailEnviado) ? trim($emailEnviado) : '';
                $telefoneFormulario = is_string($telefoneEnviado) ? trim($telefoneEnviado) : '';
                $enderecoFormulario = is_string($enderecoEnviado) ? trim($enderecoEnviado) : '';

                if (!is_string($emailEnviado) || !filter_var($emailFormulario, FILTER_VALIDATE_EMAIL)) {
                    $mensagemErro = 'Informe um e-mail válido.';
                } elseif (strlen($emailFormulario) > 100) {
                    $mensagemErro = 'O e-mail deve ter no máximo 100 caracteres.';
                } elseif (!is_string($telefoneEnviado) || strlen($telefoneFormulario) > 20) {
                    $mensagemErro = 'O telefone deve ter no máximo 20 caracteres.';
                } elseif (!is_string($enderecoEnviado) || mb_strlen($enderecoFormulario, 'UTF-8') > 150) {
                    $mensagemErro = 'O endereço deve ter no máximo 150 caracteres.';
                } elseif ($usuarioRepository->emailExistsForAnotherUser($emailFormulario, $idUsuario)) {
                    $mensagemErro = 'Este e-mail já está sendo usado por outra conta.';
                } else {
                    try {
                        $atualizado = $usuarioRepository->updateContactData(
                            $idUsuario,
                            $emailFormulario,
                            $telefoneFormulario !== '' ? $telefoneFormulario : null,
                            $enderecoFormulario !== '' ? $enderecoFormulario : null
                        );
                    } catch (PDOException $erro) {
                        if ($erro->getCode() !== '23000') {
                            throw $erro;
                        }
                        $mensagemErro = 'Este e-mail já está sendo usado por outra conta.';
                        $atualizado = false;
                    }

                    if ($mensagemErro === '' && !$atualizado) {
                        throw new RuntimeException('Não foi possível atualizar as informações de contato.');
                    }
                    if ($mensagemErro === '') {
                        $_SESSION['usuario_email'] = $emailFormulario;
                        $_SESSION['conta_sucesso'] = 'Suas informações de contato foram atualizadas.';
                        header('Location: minha-conta.php');
                        exit;
                    }
                }
            } elseif ($acaoEnviada === 'alterar_senha') {
                $senhaAtualEnviada = $_POST['senha_atual'] ?? '';
                $novaSenhaEnviada = $_POST['nova_senha'] ?? '';
                $confirmacaoEnviada = $_POST['confirmar_senha'] ?? '';
                $senhaAtual = is_string($senhaAtualEnviada) ? $senhaAtualEnviada : '';
                $novaSenha = is_string($novaSenhaEnviada) ? $novaSenhaEnviada : '';
                $confirmacao = is_string($confirmacaoEnviada) ? $confirmacaoEnviada : '';

                if (!is_string($senhaAtualEnviada) || !is_string($novaSenhaEnviada) || !is_string($confirmacaoEnviada)) {
                    $mensagemErro = 'Preencha os campos de senha corretamente.';
                } elseif (mb_strlen($novaSenha, 'UTF-8') < 8 || strlen($novaSenha) > 255) {
                    $mensagemErro = 'A nova senha deve ter pelo menos 8 caracteres e no máximo 255 bytes.';
                } elseif ($novaSenha !== $confirmacao) {
                    $mensagemErro = 'A confirmação da nova senha não corresponde.';
                } else {
                    $senhaHashAtual = $usuarioRepository->findPasswordHashById($idUsuario);
                    if ($senhaHashAtual === null || !password_verify($senhaAtual, $senhaHashAtual)) {
                        $mensagemErro = 'A senha atual está incorreta.';
                    } elseif (password_verify($novaSenha, $senhaHashAtual)) {
                        $mensagemErro = 'Escolha uma senha diferente da senha atual.';
                    } else {
                        $novaSenhaHash = password_hash($novaSenha, PASSWORD_DEFAULT);
                        $novaVersao = $usuarioRepository->updatePassword(
                            $idUsuario,
                            $senhaHashAtual,
                            $novaSenhaHash
                        );
                        if ($novaVersao === null) {
                            $mensagemErro = 'A senha foi alterada em outra sessão. Atualize a página e tente novamente.';
                        } else {
                            $_SESSION['auth_version'] = $novaVersao;
                            $_SESSION['conta_sucesso'] = 'Sua senha foi alterada. As outras sessões foram encerradas.';
                            header('Location: minha-conta.php');
                            exit;
                        }
                    }
                }
            } else {
                http_response_code(400);
                $mensagemErro = 'Ação inválida.';
            }
        } catch (PDOException $erro) {
            error_log($erro->getMessage());
            http_response_code(503);
            $mensagemErro = 'Não foi possível salvar as alterações agora. Tente novamente em instantes.';
        } catch (RuntimeException $erro) {
            error_log($erro->getMessage());
            $mensagemErro = 'Não foi possível salvar as alterações. Tente novamente.';
        }
    }
}

$tipoConta = ($conta['tipo_usuario'] ?? '') === 'admin' ? 'Administrador' : 'Cliente';
$headerBasePath = '../';
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <?php require __DIR__ . '/gtm.php'; ?>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="color-scheme" content="light dark">
    <meta name="description" content="Consulte e gerencie seus dados pessoais e informações da conta Virtual Motors.">
    <title>Minha conta · Virtual Motors</title>
    <link rel="stylesheet" href="../public/style.css?v=profile-header-layout">
</head>
<body>
    <?php require __DIR__ . '/header.php'; ?>
    <main class="purchase-history-wrapper">
        <div class="purchase-history-heading">
            <span class="section-tag">ÁREA DO CLIENTE</span>
            <h1>Minha conta</h1>
            <p>Consulte seus dados cadastrais e gerencie suas informações de acesso.</p>
            <nav class="account-page-actions" aria-label="Opções da conta">
                <a class="button button-outline" href="perfil.php">Meu perfil</a>
                <a class="button button-outline" href="minhas-compras.php">Minhas compras</a>
            </nav>
        </div>

        <?php if ($mensagemSucesso !== '') : ?>
            <p class="cart-message" role="status"><?= htmlspecialchars((string) $mensagemSucesso, ENT_QUOTES, 'UTF-8') ?></p>
        <?php endif; ?>
        <?php if ($mensagemErro !== '') : ?>
            <p class="cart-message cart-error" role="alert"><?= htmlspecialchars($mensagemErro, ENT_QUOTES, 'UTF-8') ?></p>
        <?php endif; ?>
        <?php if ($erroBanco || $conta === null) : ?>
            <p class="cart-message cart-error" role="alert">Não foi possível carregar sua conta agora. Tente novamente em instantes.</p>
        <?php else : ?>
            <section class="account-management" aria-label="Gerenciamento da conta">
                <article class="account-management-card">
                    <h2>Dados cadastrais</h2>
                    <dl class="account-data-list">
                        <div>
                            <dt>Nome</dt>
                            <dd><?= htmlspecialchars((string) $conta['nome'], ENT_QUOTES, 'UTF-8') ?></dd>
                        </div>
                        <div>
                            <dt>E-mail</dt>
                            <dd><?= htmlspecialchars((string) $conta['email'], ENT_QUOTES, 'UTF-8') ?></dd>
                        </div>
                        <div>
                            <dt>Telefone</dt>
                            <dd><?= htmlspecialchars((string) ($conta['telefone'] ?: 'Não informado'), ENT_QUOTES, 'UTF-8') ?></dd>
                        </div>
                        <div>
                            <dt>Endereço</dt>
                            <dd><?= htmlspecialchars((string) ($conta['endereco'] ?: 'Não informado'), ENT_QUOTES, 'UTF-8') ?></dd>
                        </div>
                    </dl>
                </article>

                <article class="account-management-card">
                    <h2>Informações da conta</h2>
                    <dl class="account-data-list">
                        <div>
                            <dt>UID da conta</dt>
                            <dd><?= htmlspecialchars((string) $conta['uid'], ENT_QUOTES, 'UTF-8') ?></dd>
                        </div>
                        <div>
                            <dt>Tipo de acesso</dt>
                            <dd><?= htmlspecialchars($tipoConta, ENT_QUOTES, 'UTF-8') ?></dd>
                        </div>
                    </dl>
                    <a class="account-purchases-link" href="minhas-compras.php">Consultar histórico de compras</a>
                </article>

                <article class="account-management-card">
                    <h2>Dados pessoais</h2>
                    <form class="account-form" method="post" action="minha-conta.php">
                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($tokenSessao, ENT_QUOTES, 'UTF-8') ?>">
                        <input type="hidden" name="acao" value="atualizar_dados_pessoais">
                        <div class="profile-form-field">
                            <label for="accountName">Nome completo</label>
                            <input id="accountName" name="nome" type="text" maxlength="100" autocomplete="name" required value="<?= htmlspecialchars($nomeFormulario, ENT_QUOTES, 'UTF-8') ?>">
                        </div>
                        <button class="button button-primary profile-save-button" type="submit">Salvar dados pessoais</button>
                    </form>
                </article>

                <article class="account-management-card">
                    <h2>Informações de contato</h2>
                    <form class="account-form" method="post" action="minha-conta.php">
                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($tokenSessao, ENT_QUOTES, 'UTF-8') ?>">
                        <input type="hidden" name="acao" value="atualizar_contato">
                        <div class="profile-form-field">
                            <label for="accountEmail">E-mail</label>
                            <input id="accountEmail" name="email" type="email" maxlength="100" autocomplete="email" required value="<?= htmlspecialchars($emailFormulario, ENT_QUOTES, 'UTF-8') ?>">
                        </div>
                        <div class="profile-form-field">
                            <label for="accountPhone">Telefone</label>
                            <input id="accountPhone" name="telefone" type="tel" maxlength="20" autocomplete="tel" value="<?= htmlspecialchars($telefoneFormulario, ENT_QUOTES, 'UTF-8') ?>">
                        </div>
                        <div class="profile-form-field account-form-field-wide">
                            <label for="accountAddress">Endereço</label>
                            <input id="accountAddress" name="endereco" type="text" maxlength="150" autocomplete="street-address" value="<?= htmlspecialchars($enderecoFormulario, ENT_QUOTES, 'UTF-8') ?>">
                        </div>
                        <button class="button button-primary profile-save-button" type="submit">Salvar contato</button>
                    </form>
                </article>

                <article class="account-management-card">
                    <h2>Alterar senha</h2>
                    <p class="account-card-description">Por segurança, confirme sua senha atual. A alteração encerra as outras sessões da sua conta.</p>
                    <form class="account-form" method="post" action="minha-conta.php">
                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($tokenSessao, ENT_QUOTES, 'UTF-8') ?>">
                        <input type="hidden" name="acao" value="alterar_senha">
                        <div class="profile-form-field">
                            <label for="currentPassword">Senha atual</label>
                            <input id="currentPassword" name="senha_atual" type="password" autocomplete="current-password" required>
                        </div>
                        <div class="profile-form-field">
                            <label for="newPassword">Nova senha</label>
                            <input id="newPassword" name="nova_senha" type="password" minlength="8" maxlength="255" autocomplete="new-password" required>
                            <small>Pelo menos 8 caracteres.</small>
                        </div>
                        <div class="profile-form-field">
                            <label for="confirmPassword">Confirmar nova senha</label>
                            <input id="confirmPassword" name="confirmar_senha" type="password" minlength="8" maxlength="255" autocomplete="new-password" required>
                        </div>
                        <button class="button button-primary profile-save-button" type="submit">Alterar senha</button>
                    </form>
                </article>
            </section>
        <?php endif; ?>
    </main>
    <script src="../public/script.js" defer></script>
</body>
</html>
