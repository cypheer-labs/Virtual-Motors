<?php

declare(strict_types=1);

use VirtualMotors\Core\Database;

require_once __DIR__ . '/authsession.php';

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

function admin_pdo(): PDO
{
    require_once __DIR__ . '/database.php';

    return Database::getConnection();
}

function exigir_admin(): void
{
    if (!validar_sessao_usuario()) {
        header('Location: ../src/login.php');
        exit;
    }

    $idUsuario = filter_var($_SESSION['usuario_id'] ?? null, FILTER_VALIDATE_INT);

    if ($idUsuario === false || $idUsuario === null || $idUsuario < 1) {
        header('Location: ../src/login.php');
        exit;
    }

    $consulta = admin_pdo()->prepare(
        'SELECT tipo_usuario FROM usuario WHERE id_usuario = :id_usuario LIMIT 1'
    );
    $consulta->execute(['id_usuario' => $idUsuario]);
    $tipoUsuario = $consulta->fetchColumn();

    if ($tipoUsuario !== 'admin') {
        unset($_SESSION['tipo_usuario']);
        http_response_code(403);
        exit('Acesso negado.');
    }

    $_SESSION['tipo_usuario'] = 'admin';
}
