<?php

declare(strict_types=1);

use VirtualMotors\Repository\UserRepository;

require_once __DIR__ . '/database.php';
require_once __DIR__ . '/userrepository.php';

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

$idUsuario = filter_var($_SESSION['usuario_id'] ?? null, FILTER_VALIDATE_INT);
if ($idUsuario === false || $idUsuario === null || $idUsuario < 1) {
    header('Location: login.php');
    exit;
}

$usuarioRepository = new UserRepository();
$perfil = null;
$erroBanco = false;
$mensagemErro = '';

try {
    $perfil = $usuarioRepository->findProfileById($idUsuario);
} catch (PDOException $erro) {
    error_log($erro->getMessage());
    http_response_code(503);
    $erroBanco = true;
}

if ($perfil === null && !$erroBanco) {
    http_response_code(404);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$erroBanco && $perfil !== null) {
    $tokenSessao = $_SESSION['csrf_perfil'] ?? '';
    $tokenFormulario = $_POST['csrf_token'] ?? '';
    if (!is_string($tokenSessao) || !is_string($tokenFormulario) || !hash_equals($tokenSessao, $tokenFormulario)) {
        $mensagemErro = 'Sua sessão expirou. Atualize a página e tente novamente.';
    } else {
        $nomeEnviado = $_POST['nome'] ?? '';
        $bioEnviada = $_POST['bio_perfil'] ?? '';
        $nome = is_string($nomeEnviado) ? trim($nomeEnviado) : '';
        $comprimentoNome = is_string($nomeEnviado) ? mb_strlen($nome, 'UTF-8') : 0;
        if (
            !is_string($nomeEnviado)
            || !is_string($bioEnviada)
        ) {
            $mensagemErro = 'Preencha os campos do perfil corretamente.';
        } elseif ($nome === '' || $comprimentoNome > 100) {
            $mensagemErro = 'Informe um nome com até 100 caracteres.';
        } elseif (mb_strlen($bioEnviada, 'UTF-8') > 500) {
            $mensagemErro = 'A bio pode ter no máximo 500 caracteres.';
        } else {
            $bio = trim($bioEnviada);
            $arquivosPreparados = [];
            $arquivosMovidos = [];
            $configuracoesUpload = [
                'foto_perfil' => ['nome' => 'foto de perfil', 'limite' => 5 * 1024 * 1024],
                'banner_perfil' => ['nome' => 'banner', 'limite' => 8 * 1024 * 1024],
            ];
            $tiposPermitidos = [
                'image/jpeg' => 'jpg',
                'image/png' => 'png',
                'image/webp' => 'webp',
            ];

            foreach ($configuracoesUpload as $campo => $configuracao) {
                $arquivo = $_FILES[$campo] ?? null;
                if (!is_array($arquivo) || ($arquivo['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
                    continue;
                }

                $erroUpload = $arquivo['error'] ?? UPLOAD_ERR_NO_FILE;
                $temporario = $arquivo['tmp_name'] ?? '';
                $tamanho = $arquivo['size'] ?? 0;
                if (
                    $erroUpload !== UPLOAD_ERR_OK
                    || !is_string($temporario)
                    || !is_uploaded_file($temporario)
                    || !is_int($tamanho)
                    || $tamanho < 1
                    || $tamanho > $configuracao['limite']
                ) {
                    $mensagemErro = 'Não foi possível receber a ' . $configuracao['nome'] . '. Verifique o arquivo e o limite de tamanho.';
                    break;
                }

                $informacoesImagem = getimagesize($temporario);
                $mime = (new finfo(FILEINFO_MIME_TYPE))->file($temporario);
                if (
                    $informacoesImagem === false
                    || !is_string($mime)
                    || !isset($tiposPermitidos[$mime])
                    || $informacoesImagem[0] > 6000
                    || $informacoesImagem[1] > 6000
                ) {
                    $mensagemErro = 'A ' . $configuracao['nome'] . ' deve ser uma imagem JPG, PNG ou WEBP de até 6000 × 6000 pixels.';
                    break;
                }

                $arquivosPreparados[$campo] = [
                    'temporario' => $temporario,
                    'nome' => bin2hex(random_bytes(16)) . '.' . $tiposPermitidos[$mime],
                ];
            }

            if ($mensagemErro === '') {
                $diretorioUploads = dirname(__DIR__) . DIRECTORY_SEPARATOR . 'public'
                    . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . 'perfis';
                if (!is_dir($diretorioUploads) && !mkdir($diretorioUploads, 0755, true) && !is_dir($diretorioUploads)) {
                    $mensagemErro = 'Não foi possível preparar o armazenamento das imagens. Tente novamente.';
                } else {
                    foreach ($arquivosPreparados as $campo => $arquivo) {
                        $destino = $diretorioUploads . DIRECTORY_SEPARATOR . $arquivo['nome'];
                        if (!move_uploaded_file($arquivo['temporario'], $destino)) {
                            $mensagemErro = 'Não foi possível salvar as imagens enviadas. Tente novamente.';
                            break;
                        }
                        $arquivosMovidos[] = $destino;
                        $arquivosPreparados[$campo]['caminho'] = 'uploads/perfis/' . $arquivo['nome'];
                    }
                }
            }

            if ($mensagemErro === '') {
                try {
                    $caminhoFoto = $arquivosPreparados['foto_perfil']['caminho'] ?? null;
                    $caminhoBanner = $arquivosPreparados['banner_perfil']['caminho'] ?? null;
                    if (!$usuarioRepository->updateProfile(
                        $idUsuario,
                        $nome,
                        $bio,
                        $caminhoFoto,
                        $caminhoBanner
                    )) {
                        throw new RuntimeException('Não foi possível atualizar o perfil.');
                    }
                    $_SESSION['usuario_nome'] = $nome;
                    $_SESSION['usuario_perfil_carregado'] = true;
                    if ($caminhoFoto !== null) {
                        $_SESSION['usuario_foto_perfil'] = $caminhoFoto;
                    }
                    if ($caminhoBanner !== null) {
                        $_SESSION['usuario_banner_perfil'] = $caminhoBanner;
                    }
                    $_SESSION['perfil_sucesso'] = 'Seu perfil foi atualizado com sucesso.';
                    header('Location: perfil.php');
                    exit;
                } catch (PDOException $erro) {
                    error_log($erro->getMessage());
                    http_response_code(503);
                    $mensagemErro = 'Não foi possível salvar seu perfil no momento. Tente novamente em instantes.';
                } catch (RuntimeException $erro) {
                    error_log($erro->getMessage());
                    $mensagemErro = 'Não foi possível salvar seu perfil. Tente novamente.';
                }
            }

            if ($mensagemErro !== '') {
                foreach ($arquivosMovidos as $arquivoMovido) {
                    if (is_file($arquivoMovido)) {
                        unlink($arquivoMovido);
                    }
                }
            } else {
                $perfil['nome'] = $nome;
                $perfil['bio_perfil'] = $bio;
                if ($caminhoFoto !== null) {
                    $perfil['foto_perfil'] = $caminhoFoto;
                }
                if ($caminhoBanner !== null) {
                    $perfil['banner_perfil'] = $caminhoBanner;
                }
            }
        }
    }
}

if (!isset($_SESSION['csrf_perfil']) || !is_string($_SESSION['csrf_perfil'])) {
    $_SESSION['csrf_perfil'] = bin2hex(random_bytes(32));
}
$tokenCsrf = $_SESSION['csrf_perfil'];
$nomePerfil = $perfil['nome'] ?? '';
$bioPerfil = is_string($perfil['bio_perfil'] ?? null) ? $perfil['bio_perfil'] : '';
$fotoPerfil = is_string($perfil['foto_perfil'] ?? null) ? $perfil['foto_perfil'] : null;
$bannerPerfil = is_string($perfil['banner_perfil'] ?? null) ? $perfil['banner_perfil'] : null;
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $mensagemErro !== '') {
    $nomeEnviado = $_POST['nome'] ?? null;
    if (is_string($nomeEnviado)) {
        $nomePerfil = trim($nomeEnviado);
    }
    $bioEnviada = $_POST['bio_perfil'] ?? null;
    if (is_string($bioEnviada)) {
        $bioPerfil = $bioEnviada;
    }
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="color-scheme" content="light dark">
    <title>Personalizar perfil · Virtual Motors</title>
    <link rel="stylesheet" href="../public/style.css">
</head>
<body>
    <?php
    $headerBasePath = '../';
    require __DIR__ . '/header.php';
    ?>
    <main class="purchase-history-wrapper">
        <div class="purchase-history-heading">
            <span class="section-tag">ÁREA DO CLIENTE</span>
            <h1>Personalizar perfil</h1>
            <p>Altere seu nome, foto, banner e bio do perfil.</p>
            <a class="button button-outline profile-back-button" href="perfil.php">Voltar ao meu perfil</a>
        </div>

        <?php if ($mensagemErro !== '') : ?>
            <p class="cart-message cart-error" role="alert"><?= htmlspecialchars($mensagemErro, ENT_QUOTES, 'UTF-8') ?></p>
        <?php endif; ?>
        <?php if ($erroBanco || $perfil === null) : ?>
            <p class="cart-message cart-error" role="alert">Não foi possível carregar seu perfil agora. Tente novamente em instantes.</p>
        <?php else : ?>
            <section class="profile-customization" aria-labelledby="profileCustomizationTitle">
                <div class="profile-cover">
                    <?php if ($bannerPerfil !== null && $bannerPerfil !== '') : ?>
                        <img src="<?= htmlspecialchars('../public/' . $bannerPerfil, ENT_QUOTES, 'UTF-8') ?>" alt="">
                    <?php endif; ?>
                </div>
                <div class="profile-customization-content">
                    <div class="profile-customization-heading">
                        <div class="profile-preview-avatar">
                            <?php if ($fotoPerfil !== null && $fotoPerfil !== '') : ?>
                                <img src="<?= htmlspecialchars('../public/' . $fotoPerfil, ENT_QUOTES, 'UTF-8') ?>" alt="Foto atual do perfil">
                            <?php else : ?>
                                <span aria-hidden="true"><?= htmlspecialchars(strtoupper(substr($nomePerfil, 0, 1)), ENT_QUOTES, 'UTF-8') ?></span>
                            <?php endif; ?>
                        </div>
                        <div>
                            <span class="section-tag">PERSONALIZAÇÃO</span>
                            <h2 id="profileCustomizationTitle">Ajuste seu perfil</h2>
                            <p>Escolha como seu perfil aparece para você.</p>
                        </div>
                    </div>

                    <form class="profile-customization-form" method="post" action="personalizar-perfil.php" enctype="multipart/form-data">
                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($tokenCsrf, ENT_QUOTES, 'UTF-8') ?>">
                        <div class="profile-form-field">
                            <label for="profileName">Nome</label>
                            <input id="profileName" name="nome" type="text" maxlength="100" required value="<?= htmlspecialchars($nomePerfil, ENT_QUOTES, 'UTF-8') ?>">
                        </div>
                        <div class="profile-form-field profile-bio-field">
                            <label for="profileBio">Bio</label>
                            <textarea id="profileBio" name="bio_perfil" maxlength="500" rows="5" placeholder="Escreva sobre você. Links começando com https:// serão clicáveis."><?= htmlspecialchars($bioPerfil, ENT_QUOTES, 'UTF-8') ?></textarea>
                            <small>Até 500 caracteres. Links https:// serão exibidos como links clicáveis.</small>
                        </div>
                        <div class="profile-form-field">
                            <label for="profilePhoto">Foto de perfil</label>
                            <input id="profilePhoto" name="foto_perfil" type="file" accept="image/jpeg,image/png,image/webp">
                            <small>JPG, PNG ou WEBP; até 5 MB.</small>
                        </div>
                        <div class="profile-form-field">
                            <label for="profileBanner">Banner</label>
                            <input id="profileBanner" name="banner_perfil" type="file" accept="image/jpeg,image/png,image/webp">
                            <small>JPG, PNG ou WEBP; até 8 MB.</small>
                        </div>
                        <button class="button button-primary profile-save-button" type="submit">Salvar personalização</button>
                    </form>
                </div>
            </section>
        <?php endif; ?>
    </main>
    <script src="../public/script.js" defer></script>
</body>
</html>
