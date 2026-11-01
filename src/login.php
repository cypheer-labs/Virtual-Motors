<?php

use VirtualMotors\Service\AuthService;

require_once __DIR__ . '/database.php';
require_once __DIR__ . '/userrepository.php';
require_once __DIR__ . '/authservice.php';
require_once __DIR__ . '/carrinhorepository.php';

// Inicia a sessão
session_start();

$mensagem = "";

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $email = trim($_POST['email'] ?? '');
    $senha = trim($_POST['senha'] ?? '');

    if (!empty($email) && !empty($senha)) {
        try {
            $authService = new AuthService();
            $usuario = $authService->validateCredentials($email, $senha);

            if ($usuario) {
                session_regenerate_id(true);
                $_SESSION['usuario_id'] = $usuario['id'];
                $_SESSION['usuario_email'] = $usuario['email'];
                $_SESSION['usuario_nome'] = $usuario['nome'];
                $_SESSION['usuario_uid'] = (string) $usuario['uid'];
                $_SESSION['tipo_usuario'] = $usuario['tipo_usuario'];
                try {
                    $carrinhoRepository = new \VirtualMotors\Repository\CarrinhoRepository();
                    $idUsuario = (int) $usuario['id'];
                    $carrinhoAtual = $_SESSION['virtual_motors_carrinho'] ?? [];
                    $_SESSION['virtual_motors_carrinho'] =
                        (int) ($_SESSION['virtual_motors_carrinho_usuario_id'] ?? 0) === $idUsuario
                            ? $carrinhoAtual
                            : $carrinhoRepository->combinar(
                                $carrinhoRepository->buscar($idUsuario),
                                $carrinhoAtual
                            );
                    $_SESSION['virtual_motors_carrinho_usuario_id'] = (int) $usuario['id'];
                    $carrinhoRepository->salvar($idUsuario, $_SESSION['virtual_motors_carrinho']);
                } catch (Throwable $erroCarrinho) {
                    error_log($erroCarrinho->getMessage());
                    $_SESSION['virtual_motors_carrinho_mensagem'] =
                        'Não foi possível sincronizar seu carrinho. Seus itens desta sessão continuam disponíveis.';
                }
                $destinoLogin = isset($_SESSION['virtual_motors_compra_direta'])
                    || !empty($_SESSION['virtual_motors_pagamento_pendente'])
                    ? 'pagamento.php'
                    : '../index.php';

                header('Location: ' . $destinoLogin);
                exit();
            }

            $mensagem = "E-mail ou senha incorretos.";
        } catch (RuntimeException $erro) {
            error_log($erro->getMessage());
            http_response_code(503);
            $mensagem = 'Não foi possível preparar o banco para o acesso. Verifique as permissões e os logs do servidor.';
        } catch (PDOException $erro) {
            error_log($erro->getMessage());
            http_response_code(503);
            $mensagem = 'Não foi possível conectar ao banco de dados. Verifique a configuração do servidor e tente novamente.';
        }
    } else {
        $mensagem = "Preencha todos os campos.";
    }
}
?>

<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="color-scheme" content="light dark">
    <title>Login - Virtual Motors</title>
    <link rel="icon" href="../assets/vm.png" type="image/png" sizes="2048x2048">
    <link rel="apple-touch-icon" href="../assets/vm.png" type="image/png" sizes="2048x2048">
    <link rel="shortcut icon" href="../assets/vm.png" type="image/png" sizes="2048x2048">
    <link rel="stylesheet" href="../public/style.css">
    <style>
        /* Estilos específicos para a tela de login idêntica à referência */
        .login-card {
            background: var(--surface);
            color: var(--text);
            width: 100%;
            max-width: 440px;
            padding: 40px;
            border-radius: 16px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.05);
            box-sizing: border-box;
            text-align: center;
        }

        .login-logo {
            width: 70px;
            height: 70px;
            margin: 0 auto 20px auto;
            display: flex;
            align-items: center;
            justify-content: center;
            border: 2px solid #E50914;
            border-radius: 50%;
            overflow: hidden;
            background: #fff;
        }

        .login-logo img {
            width: 80%;
            object-fit: contain;
        }

        .login-card h2 {
            font-size: 22px;
            color: var(--text);
            margin-bottom: 6px;
            font-weight: 700;
        }

        .login-card p.subtitle {
            font-size: 13px;
            color: var(--muted);
            margin-bottom: 30px;
        }

        .form-group {
            text-align: left;
            margin-bottom: 20px;
        }

        .form-group label {
            display: block;
            font-size: 11px;
            font-weight: 700;
            color: var(--text);
            letter-spacing: 0.5px;
            margin-bottom: 8px;
        }

        .form-group input {
            width: 100%;
            height: 46px;
            padding: 0 14px;
            border: 1px solid var(--border);
            border-radius: 8px;
            background: var(--field-bg);
            color: var(--text);
            font-size: 14px;
            outline: none;
            box-sizing: border-box;
            transition: border-color 0.2s;
        }

        .form-group input:focus {
            border-color: #0051ff;
            box-shadow: 0 0 0 3px rgba(0, 81, 255, 0.1);
        }

        .forgot-password {
            text-align: right;
            margin-bottom: 24px;
            margin-top: -10px;
        }

        .forgot-password a {
            font-size: 12px;
            color: #0051ff;
            text-decoration: none;
        }

        .forgot-password a:hover {
            text-decoration: underline;
        }

        .btn-submit {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 100%;
            height: 48px;
            background: #0051ff;
            color: white;
            border: none;
            border-radius: 8px;
            font-size: 15px;
            font-weight: 600;
            line-height: 1.2;
            text-align: center;
            cursor: pointer;
            transition: background 0.2s;
        }

        .btn-submit:hover {
            background: #0040cc;
        }

        .register-link {
            margin-top: 24px;
            font-size: 13px;
            color: var(--muted);
        }

        .register-link a {
            color: #0051ff;
            font-weight: 600;
            text-decoration: none;
        }

        .register-link a:hover {
            text-decoration: underline;
        }

        .error-msg {
            color: #E50914;
            font-size: 12px;
            margin-bottom: 15px;
            font-weight: bold;
        }
    </style>
</head>
<body class="auth-page">
<?php
$headerBasePath = '../';
require __DIR__ . '/header.php';
?>

<main class="auth-main">
<div class="login-card">
        <!-- Logotipo Redondo -->
        <a class="login-logo" href="../index.php" aria-label="Voltar para a página inicial">
            <img src="../assets/vm.png" alt="Virtual Motors">
        </a>

        <h2>VM ID</h2>
        <p class="subtitle">Acesse sua conta VM ID para continuar</p>

        <?php if (!empty($mensagem)) : ?>
            <p class="error-msg" role="alert"><?php echo htmlspecialchars($mensagem, ENT_QUOTES, 'UTF-8'); ?></p>
        <?php endif; ?>

        <form method="POST" action="login.php" autocomplete="off">
            <div class="form-group">
                <label for="email">E-MAIL</label>
                <input id="email" type="email" name="email" autocomplete="off" required placeholder="exemplo@email.com">
            </div>

            <div class="form-group">
                <label for="senha">SENHA</label>
                <div class="password-field">
                    <input
                        id="senha"
                        type="password"
                        name="senha"
                        autocomplete="current-password"
                        required
                        placeholder="••••••••••••"
                    >
                    <button
                        class="password-toggle"
                        type="button"
                        data-password-toggle
                        aria-controls="senha"
                        aria-pressed="false"
                    >Mostrar</button>
                </div>
            </div>

            <div class="forgot-password">
                <a href="#">Esqueceu a senha?</a>
            </div>

            <button type="submit" class="btn-submit">Entrar</button>
        </form>

        <div class="register-link">
            Não tem uma conta? <a href="cadastro.php">Criar conta</a>
        </div>
        <div style="margin-top: 15px;">
            <a href="../index.php" style="font-size: 13px; color: var(--muted); text-decoration: none; display: inline-flex; align-items: center; gap: 5px;">
                ← Voltar para a página inicial
            </a>
        </div>
    </div>
    </main>

    <script src="../public/script.js" defer></script>
</body>
</html>