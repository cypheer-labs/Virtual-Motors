<?php

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

require_once __DIR__ . '/authsession.php';
validar_sessao_usuario();

$headerBasePath = $headerBasePath ?? '../';
$headerPaginaInicial = $headerPaginaInicial ?? false;
$headerEmail = $_SESSION['usuario_email'] ?? null;
$headerTipoUsuario = $_SESSION['tipo_usuario'] ?? null;
$headerNome = $_SESSION['usuario_nome'] ?? null;
if (
    $headerEmail !== null
    && ((!is_string($headerNome) || $headerNome === '') || !isset($_SESSION['usuario_uid']))
) {
    $headerIdUsuario = filter_var($_SESSION['usuario_id'] ?? null, FILTER_VALIDATE_INT);
    if ($headerIdUsuario !== false && $headerIdUsuario !== null && $headerIdUsuario > 0) {
        try {
            require_once __DIR__ . '/database.php';
            $consultaNome = \VirtualMotors\Core\Database::getConnection()->prepare(
                'SELECT nome, uid FROM usuario WHERE id_usuario = :id_usuario LIMIT 1'
            );
            $consultaNome->execute(['id_usuario' => $headerIdUsuario]);
            $usuarioEncontrado = $consultaNome->fetch(PDO::FETCH_ASSOC);
            if (is_array($usuarioEncontrado)) {
                if (is_string($usuarioEncontrado['nome']) && $usuarioEncontrado['nome'] !== '') {
                    $headerNome = $usuarioEncontrado['nome'];
                    $_SESSION['usuario_nome'] = $usuarioEncontrado['nome'];
                }
                $_SESSION['usuario_uid'] = (string) $usuarioEncontrado['uid'];
            }
        } catch (\PDOException $exception) {
            error_log($exception->getMessage());
        }
    }
}
$headerNome = is_string($headerNome) && $headerNome !== ''
    ? $headerNome
    : ($headerEmail !== null ? explode('@', (string) $headerEmail)[0] : null);
$headerCarrinho = $_SESSION['virtual_motors_carrinho'] ?? [];
$headerPaginaSobreNos = $headerPaginaSobreNos ?? false;
$headerQuantidadeCarrinho = is_array($headerCarrinho)
    ? array_sum(array_map('intval', $headerCarrinho))
    : 0;
$headerInicio = $headerPaginaInicial ? '#inicio' : $headerBasePath . 'index.php#inicio';
$headerVeiculos = $headerPaginaInicial ? '#veiculos' : $headerBasePath . 'index.php#veiculos';
$headerSobre = $headerBasePath . 'src/sobre-nos.php';
$headerContato = $headerPaginaInicial ? '#contato' : $headerBasePath . 'index.php#contato';
$headerCarrinhoHref = $headerPaginaInicial || $headerPaginaSobreNos
    ? $headerBasePath . 'src/carrinho.php'
    : 'carrinho.php';
$headerLogin = $headerBasePath . 'src/login.php';
$headerCadastro = $headerBasePath . 'src/cadastro.php';
$headerPerfil = $headerBasePath . 'src/perfil.php';
$headerLogout = $headerBasePath . 'src/logout.php';
$headerCompras = $headerBasePath . 'src/minhas-compras.php';
?>
<header class="header">
    <div class="container header-content">
        <a href="<?= htmlspecialchars($headerInicio, ENT_QUOTES, 'UTF-8') ?>" class="logo">
            <img src="<?= htmlspecialchars($headerBasePath, ENT_QUOTES, 'UTF-8') ?>assets/virtual-motors.png" alt="Virtual Motors">
        </a>

        <nav class="nav" id="mainNav">
            <a href="<?= htmlspecialchars($headerInicio, ENT_QUOTES, 'UTF-8') ?>">Início</a>
            <a href="<?= htmlspecialchars($headerVeiculos, ENT_QUOTES, 'UTF-8') ?>">Veículos</a>
            <a
                href="<?= htmlspecialchars($headerSobre, ENT_QUOTES, 'UTF-8') ?>"
                <?= $headerPaginaSobreNos ? 'aria-current="page"' : '' ?>
            >Sobre nós</a>
            <a href="<?= htmlspecialchars($headerContato, ENT_QUOTES, 'UTF-8') ?>">Contato</a>
            <a href="<?= htmlspecialchars($headerCarrinhoHref, ENT_QUOTES, 'UTF-8') ?>">Carrinho (<?= $headerQuantidadeCarrinho ?>)</a>
        </nav>

        <div class="user-menu-right">
            <?php if ($headerEmail !== null) : ?>
                <details class="user-menu">
                    <summary class="user-profile" aria-label="Abrir menu do perfil">
                        <svg class="user-avatar" viewBox="0 0 24 24" aria-hidden="true">
                            <circle cx="12" cy="12" r="10"></circle>
                            <circle cx="12" cy="9" r="3"></circle>
                            <path d="M6.5 19a5.5 5.5 0 0 1 11 0"></path>
                        </svg>
                        <span class="welcome-text">Olá, <?= htmlspecialchars($headerNome, ENT_QUOTES, 'UTF-8') ?></span>
                        <span class="user-menu-caret" aria-hidden="true">▾</span>
                    </summary>
                    <div class="user-menu-dropdown">
                        <a href="<?= htmlspecialchars($headerPerfil, ENT_QUOTES, 'UTF-8') ?>">Meu perfil</a>
                        <a
                            href="<?= htmlspecialchars($headerCompras, ENT_QUOTES, 'UTF-8') ?>"
                            <?= !empty($headerPaginaCompras) ? 'aria-current="page"' : '' ?>
                        >Minhas compras</a>
                        <a class="logout-link" href="<?= htmlspecialchars($headerLogout, ENT_QUOTES, 'UTF-8') ?>">Sair</a>
                    </div>
                </details>
                <button class="theme-toggle" id="themeToggle" type="button" aria-label="Ativar modo escuro" title="Ativar modo escuro">☾</button>
                <?php if ($headerTipoUsuario === 'admin') : ?>
                    <a class="header-button" href="<?= htmlspecialchars($headerBasePath, ENT_QUOTES, 'UTF-8') ?>admin/dashboard.php">Administração</a>
                <?php endif; ?>
            <?php else : ?>
                <a class="header-button" href="<?= htmlspecialchars($headerLogin, ENT_QUOTES, 'UTF-8') ?>">Entrar</a>
                <a class="header-button header-button-secondary" href="<?= htmlspecialchars($headerCadastro, ENT_QUOTES, 'UTF-8') ?>">Criar conta</a>
                <button class="theme-toggle" id="themeToggle" type="button" aria-label="Ativar modo escuro" title="Ativar modo escuro">☾</button>
            <?php endif; ?>
        </div>

        <button class="menu-button" id="menuButton" aria-label="Abrir menu" type="button">☰</button>
    </div>
</header>
