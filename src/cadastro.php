<?php

use VirtualMotors\Service\AuthService;

session_start();

require_once __DIR__ . '/database.php';
require_once __DIR__ . '/userrepository.php';
require_once __DIR__ . '/authservice.php';

$mensagem = "";

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $nomeEnviado = $_POST['nome'] ?? '';
    $emailEnviado = $_POST['email'] ?? '';
    $telefoneEnviado = $_POST['telefone'] ?? '';
    $senhaEnviada = $_POST['senha'] ?? '';
    $confirmacaoEnviada = $_POST['confirma_senha'] ?? '';
    $nome = is_string($nomeEnviado) ? trim($nomeEnviado) : '';
    $email = is_string($emailEnviado) ? trim($emailEnviado) : '';
    $telefone = is_string($telefoneEnviado) ? trim($telefoneEnviado) : '';
    $senha = is_string($senhaEnviada) ? trim($senhaEnviada) : '';
    $confirma_senha = is_string($confirmacaoEnviada) ? trim($confirmacaoEnviada) : '';

    if (!empty($nome) && !empty($email) && !empty($telefone) && !empty($senha) && !empty($confirma_senha)) {
        if (strlen($nome) > 100) {
            $mensagem = "O nome deve ter no máximo 100 caracteres.";
        } elseif (strlen($telefone) > 20) {
            $mensagem = "O telefone deve ter no máximo 20 caracteres.";
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $mensagem = "Informe um e-mail válido.";
        } elseif (strlen($email) > 100) {
            $mensagem = "O e-mail deve ter no máximo 100 caracteres.";
        } elseif ($senha !== $confirma_senha) {
            $mensagem = "As senhas não coincidem.";
        } else {
            try {
                $authService = new AuthService();

                if (!$authService->register($nome, $email, $senha, 'cliente', $telefone)) {
                    $mensagem = "Este e-mail já está cadastrado.";
                } else {
                    echo "<script>
                            window.location.href = 'login.php';
                          </script>";
                    exit();
                }
            } catch (PDOException $erro) {
                $mensagem = $erro->getCode() === '23000'
                    ? "Este e-mail já está cadastrado."
                    : "Não foi possível concluir o cadastro. Tente novamente.";
            }
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
    <title>Cadastro - Virtual Motors</title>
    <link rel="icon" href="../assets/vm.png" type="image/png" sizes="2048x2048">
    <link rel="apple-touch-icon" href="../assets/vm.png" type="image/png" sizes="2048x2048">
    <link rel="shortcut icon" href="../assets/vm.png" type="image/png" sizes="2048x2048">
    <link rel="stylesheet" href="../public/style.css">
    <style>
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

        .btn-submit {
            width: 100%;
            height: 48px;
            background: #0051ff;
            color: white;
            border: none;
            border-radius: 8px;
            font-size: 15px;
            font-weight: 600;
            cursor: pointer;
            transition: background 0.2s;
            margin-top: 10px;
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

        <h2>Criar VM ID</h2>
        <p class="subtitle">Crie sua conta para acessar o e-commerce</p>

        <?php if (!empty($mensagem)) : ?>
            <p class="error-msg"><?php echo htmlspecialchars($mensagem, ENT_QUOTES, 'UTF-8'); ?></p>
        <?php endif; ?>

        <form method="POST" action="cadastro.php" autocomplete="off">
            <div class="form-group">
                <label for="nome">NOME</label>
                <input type="text" id="nome" name="nome" maxlength="100" autocomplete="name" required placeholder="Seu nome completo">
            </div>

            <div class="form-group">
                <label for="email">E-MAIL</label>
                <input type="email" id="email" name="email" maxlength="100" autocomplete="email" required placeholder="exemplo@email.com">
            </div>

            <div class="form-group">
                <label for="telefone">TELEFONE</label>
                <input type="tel" id="telefone" name="telefone" maxlength="20" autocomplete="tel" required placeholder="(00) 00000-0000">
            </div>

            <div class="form-group">
                <label for="senha">SENHA</label>
                <div class="password-field">
                    <input
                        type="password"
                        id="senha"
                        name="senha"
                        autocomplete="new-password"
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

            <div class="form-group">
                <label for="confirma_senha">CONFIRMAR SENHA</label>
                <div class="password-field">
                    <input
                        type="password"
                        id="confirma_senha"
                        name="confirma_senha"
                        autocomplete="new-password"
                        required
                        placeholder="••••••••••••"
                    >
                    <button
                        class="password-toggle"
                        type="button"
                        data-password-toggle
                        aria-controls="confirma_senha"
                        aria-pressed="false"
                    >Mostrar</button>
                </div>
            </div>

            <button type="submit" class="btn-submit">Cadastrar</button>
        </form>

        <div class="register-link">
            Já tem uma conta? <a href="login.php">Faça login</a>
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