<?php

declare(strict_types=1);

use PHPMailer\PHPMailer\Exception as MailException;
use PHPMailer\PHPMailer\PHPMailer;

require __DIR__ . '/vendor/autoload.php';

// This standalone page is intentionally restricted to local access.
$remoteAddress = $_SERVER['REMOTE_ADDR'] ?? '';
$hostHeader = $_SERVER['HTTP_HOST'] ?? '';
if (
    !in_array($remoteAddress, ['127.0.0.1', '::1'], true)
    || !preg_match('/\A(?:localhost|127\.0\.0\.1|\[::1\])(?::([0-9]{1,5}))?\z/i', $hostHeader, $hostMatches)
    || (isset($hostMatches[1]) && (int) $hostMatches[1] > 65535)
) {
    http_response_code(404);
    exit('Este protótipo só pode ser acessado localmente.');
}

$localConfigPath = __DIR__ . '/config/email-test.config.php';
$localConfig = is_file($localConfigPath) ? require $localConfigPath : [];
if (!is_array($localConfig)) {
    http_response_code(500);
    exit('A configuração local de e-mail é inválida.');
}
$config = static function (string $key, mixed $default = '') use ($localConfig): mixed {
    $environmentValue = getenv($key);
    if ($environmentValue !== false && $environmentValue !== '') {
        return $environmentValue;
    }
    $localValue = $localConfig[$key] ?? null;
    return $localValue !== null && $localValue !== '' ? $localValue : $default;
};

session_start();
$escape = static fn (string $value): string => htmlspecialchars(
    $value,
    ENT_QUOTES | ENT_SUBSTITUTE,
    'UTF-8'
);
if (!isset($_SESSION['email_test_csrf'])) {
    $_SESSION['email_test_csrf'] = bin2hex(random_bytes(32));
}

/** Read/write the standalone token store under an exclusive lock. */
$withTokenStore = static function (callable $callback) use ($config): mixed {
    $storagePath = (string) $config('EMAIL_TEST_STORAGE_PATH', sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'virtual-motors-email-test');
    if (!is_dir($storagePath) && !mkdir($storagePath, 0700, true) && !is_dir($storagePath)) {
        throw new RuntimeException('Não foi possível criar a pasta privada dos tokens.');
    }
    $realStoragePath = realpath($storagePath);
    if ($realStoragePath === false || !is_writable($realStoragePath)) {
        throw new RuntimeException('A pasta privada dos tokens não pode ser gravada.');
    }
    $storePath = $realStoragePath . DIRECTORY_SEPARATOR . 'tokens.json';
    $handle = fopen($storePath, 'c+');
    if ($handle === false || !flock($handle, LOCK_EX)) {
        if (is_resource($handle)) {
            fclose($handle);
        }
        throw new RuntimeException('Não foi possível abrir o armazenamento de tokens.');
    }
    @chmod($storePath, 0600);
    try {
        rewind($handle);
        $contents = stream_get_contents($handle);
        $records = $contents === false || trim($contents) === '' ? [] : json_decode($contents, true);
        if (!is_array($records)) {
            throw new RuntimeException('O armazenamento de tokens está inválido.');
        }
        $result = $callback($records);
        rewind($handle);
        if (!ftruncate($handle, 0)) {
            throw new RuntimeException('Não foi possível atualizar o armazenamento de tokens.');
        }
        $encoded = json_encode($records, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);
        if (fwrite($handle, $encoded) === false || !fflush($handle)) {
            throw new RuntimeException('Não foi possível salvar o armazenamento de tokens.');
        }
        return $result;
    } finally {
        flock($handle, LOCK_UN);
        fclose($handle);
    }
};

$message = '';
$messageType = 'info';
$email = '';
$dnsResult = '';

if (isset($_GET['token'])) {
    $token = $_GET['token'];
    if (!is_string($token) || !preg_match('/\A[a-f0-9]{64}\z/', $token)) {
        $validRecord = null;
    } else {
        $verificationSecret = (string) $config('EMAIL_TEST_SECRET');
        $tokenHash = hash_hmac('sha256', $token, $verificationSecret);
        try {
            $validRecord = $withTokenStore(static function (array &$records) use ($tokenHash): ?array {
                $now = time();
                foreach ($records as $hash => $record) {
                    if (!is_array($record) || !isset($record['expires']) || !is_int($record['expires']) || $record['expires'] < $now) {
                        unset($records[$hash]);
                    }
                }
                $record = $records[$tokenHash] ?? null;
                if (!is_array($record) || !isset($record['email']) || !is_string($record['email'])) {
                    return null;
                }
                unset($records[$tokenHash]);
                return $record;
            });
        } catch (Throwable $exception) {
            error_log('Falha ao validar token de e-mail: ' . $exception->getMessage());
            $validRecord = null;
        }
    }
    if (is_array($validRecord)) {
        $email = $validRecord['email'];
        $message = 'Link confirmado com sucesso. O endereço ' . $email . ' foi acessado.';
        $messageType = 'success';
    } else {
        $message = 'Link inválido, expirado ou já utilizado. Solicite um novo teste.';
        $messageType = 'error';
    }
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $submittedEmail = $_POST['email'] ?? '';
    $submittedCsrf = $_POST['csrf_token'] ?? '';
    $email = is_string($submittedEmail) ? trim($submittedEmail) : '';

    if (!is_string($submittedCsrf) || !hash_equals($_SESSION['email_test_csrf'], $submittedCsrf)) {
        $message = 'Sessão expirada ou formulário inválido. Atualize a página e tente novamente.';
        $messageType = 'error';
    } elseif ($email === '' || strlen($email) > 254 || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $message = 'Informe um endereço de e-mail válido (até 254 caracteres).';
        $messageType = 'error';
    } else {
        $domain = substr(strrchr($email, '@'), 1);
        $hasMailRecord = checkdnsrr($domain, 'MX') || checkdnsrr($domain, 'A') || checkdnsrr($domain, 'AAAA');
        $dnsResult = $hasMailRecord
            ? 'O domínio possui registro MX ou endereço DNS. Isso é apenas um indicativo, não comprova que a caixa postal existe.'
            : 'Não foi encontrado MX/A/AAAA para o domínio. A confirmação ainda será tentada; a consulta DNS não é conclusiva.';

        $smtpHost = (string) $config('SMTP_HOST');
        $smtpUser = (string) $config('SMTP_USER');
        $smtpPassword = (string) $config('SMTP_PASS');
        $fromEmail = (string) $config('SMTP_FROM_EMAIL');
        $smtpSecurity = strtolower((string) $config('SMTP_SECURITY', 'tls'));
        $smtpPortValue = (string) $config('SMTP_PORT', $smtpSecurity === 'ssl' ? '465' : '587');
        $verificationSecret = (string) $config('EMAIL_TEST_SECRET');

        if ($smtpHost === '' || $smtpUser === '' || $smtpPassword === '' || $fromEmail === '') {
            $message = 'Configure SMTP_HOST, SMTP_USER, SMTP_PASS e SMTP_FROM_EMAIL em config/email-test.config.php ou no ambiente.';
            $messageType = 'error';
        } elseif (strlen($verificationSecret) < 32) {
            $message = 'Configure EMAIL_TEST_SECRET com pelo menos 32 caracteres aleatórios.';
            $messageType = 'error';
        } elseif (
            !in_array($smtpSecurity, ['tls', 'ssl'], true)
            || filter_var($smtpPortValue, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 65535]]) === false
        ) {
            $message = 'Configuração SMTP inválida. Use SMTP_SECURITY=tls ou ssl e uma porta entre 1 e 65535.';
            $messageType = 'error';
        } elseif (!filter_var($fromEmail, FILTER_VALIDATE_EMAIL)) {
            $message = 'SMTP_FROM_EMAIL precisa ser um endereço válido.';
            $messageType = 'error';
        } else {
            $token = bin2hex(random_bytes(32));
            $tokenHash = hash_hmac('sha256', $token, $verificationSecret);
            $expiresAt = time() + 1800;
            try {
                $baseUrl = rtrim((string) $config('EMAIL_TEST_BASE_URL'), '/');
                if ($baseUrl === '') {
                    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https://' : 'http://';
                    $baseUrl = $scheme . $hostHeader . ($_SERVER['SCRIPT_NAME'] ?? '/test.php');
                }
                if (!preg_match('/\Ahttps?:\/\/[^\s?#]+\z/i', $baseUrl)) {
                    throw new RuntimeException('EMAIL_TEST_BASE_URL deve ser uma URL HTTP ou HTTPS válida, terminando em test.php.');
                }
                $verificationUrl = $baseUrl . '?token=' . rawurlencode($token);
                $withTokenStore(static function (array &$records) use ($tokenHash, $email, $expiresAt): void {
                    foreach ($records as $hash => $record) {
                        if (!is_array($record) || !isset($record['expires']) || !is_int($record['expires']) || $record['expires'] < time()) {
                            unset($records[$hash]);
                        }
                    }
                    $records[$tokenHash] = ['email' => $email, 'expires' => $expiresAt];
                });

                $mail = new PHPMailer(true);
                $mail->isSMTP();
                $mail->Host = $smtpHost;
                $mail->SMTPAuth = true;
                $mail->Username = $smtpUser;
                $mail->Password = $smtpPassword;
                $mail->SMTPSecure = $smtpSecurity === 'ssl' ? PHPMailer::ENCRYPTION_SMTPS : PHPMailer::ENCRYPTION_STARTTLS;
                $mail->Port = (int) $smtpPortValue;
                $mail->CharSet = PHPMailer::CHARSET_UTF8;
                $mail->setFrom($fromEmail, (string) $config('SMTP_FROM_NAME', 'Virtual Motors - teste'));
                $mail->addAddress($email);
                $mail->isHTML(true);
                $mail->Subject = 'Confirme seu e-mail - Virtual Motors (teste)';
                $mail->Body = '<p>Este é um teste isolado de verificação de e-mail da Virtual Motors.</p>'
                    . '<p><a href="' . $escape($verificationUrl) . '">Confirmar endereço de e-mail</a></p>'
                    . '<p>O link expira em 30 minutos e só pode ser usado uma vez.</p>';
                $mail->AltBody = "Este é um teste isolado de verificação de e-mail da Virtual Motors.\n\n"
                    . "Acesse o link para confirmar: {$verificationUrl}\n\nO link expira em 30 minutos e só pode ser usado uma vez.";
                $mail->send();
                $message = 'E-mail de confirmação enviado. Abra o link recebido; ele funciona em qualquer navegador que consiga acessar este endereço.';
                $messageType = 'success';
            } catch (MailException | RuntimeException $exception) {
                try {
                    $withTokenStore(static function (array &$records) use ($tokenHash): void {
                        unset($records[$tokenHash]);
                    });
                } catch (Throwable $storageException) {
                    error_log('Falha ao limpar token após erro SMTP: ' . $storageException->getMessage());
                }
                error_log('Falha no envio do e-mail de teste: ' . $exception->getMessage());
                $message = 'Não foi possível enviar a confirmação. Confira a configuração SMTP e o log do PHP.';
                $messageType = 'error';
            } catch (Throwable $exception) {
                error_log('Falha no teste de e-mail: ' . $exception->getMessage());
                $message = 'Não foi possível concluir o teste. Confira o armazenamento privado e o log do PHP.';
                $messageType = 'error';
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <?php require __DIR__ . '/src/gtm.php'; ?>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Teste isolado de verificação de e-mail - Virtual Motors</title>
    <style>
        body { font: 16px/1.5 Arial, sans-serif; margin: 0; padding: 32px 16px; background: #f4f5f7; color: #20242a; }
        main { max-width: 620px; margin: 0 auto; padding: 28px; background: #fff; border-radius: 12px; box-shadow: 0 4px 18px #00000012; }
        h1 { margin-top: 0; font-size: 1.6rem; }
        label { display: block; margin: 20px 0 6px; font-weight: 700; }
        input, button { box-sizing: border-box; width: 100%; padding: 12px; font: inherit; }
        button { margin-top: 14px; border: 0; border-radius: 6px; background: #d71920; color: #fff; cursor: pointer; font-weight: 700; }
        .message, .notice { margin: 18px 0; padding: 12px; border-radius: 6px; overflow-wrap: anywhere; }
        .success { background: #e8f6ed; color: #155c2d; }
        .error { background: #fdebea; color: #8e1c17; }
        .info, .notice { background: #eef2f7; }
        code { overflow-wrap: anywhere; }
    </style>
</head>
<body>
<main>
    <h1>Protótipo isolado: verificação de e-mail</h1>
    <p>Este teste não acessa o cadastro, o banco de dados nem o fluxo do site. Informe um endereço que você possa acessar.</p>
    <div class="notice">
        <strong>Como funciona:</strong> a sintaxe é validada primeiro; DNS é apenas um diagnóstico. A confirmação ocorre quando o link enviado é aberto.
        O link vale por 30 minutos, é de uso único e pode ser aberto em outro navegador. Para funcionar, esse navegador precisa alcançar a URL configurada.
    </div>
    <?php if ($message !== '') : ?>
        <div class="message <?= $escape($messageType) ?>" role="status"><?= $escape($message) ?></div>
    <?php endif; ?>
    <?php if ($dnsResult !== '') : ?>
        <div class="message info"><?= $escape($dnsResult) ?></div>
    <?php endif; ?>
    <form method="post" action="">
        <input type="hidden" name="csrf_token" value="<?= $escape($_SESSION['email_test_csrf']) ?>">
        <label for="email">E-mail para testar</label>
        <input id="email" name="email" type="email" maxlength="254" autocomplete="email" required
               value="<?= $escape($email) ?>" placeholder="voce@exemplo.com">
        <button type="submit">Enviar confirmação</button>
    </form>
    <h2>Configuração necessária</h2>
    <p>Copie <code>config/email-test.config.example.php</code> para <code>config/email-test.config.php</code> e preencha os dados SMTP e o segredo de confirmação. Consulte <code>docs/EMAIL_TEST_SETUP.md</code>.</p>
</main>
</body>
</html>
