<?php

declare(strict_types=1);

use VirtualMotors\Core\Database;

function validar_sessao_usuario(): bool
{
    $idUsuario = filter_var($_SESSION['usuario_id'] ?? null, FILTER_VALIDATE_INT);
    $possuiDadosAutenticados = isset($_SESSION['usuario_email'])
        || ($idUsuario !== false && $idUsuario !== null && $idUsuario > 0);

    if (!$possuiDadosAutenticados) {
        return true;
    }

    if ($idUsuario === false || $idUsuario === null || $idUsuario < 1) {
        invalidar_sessao_usuario();
        return false;
    }

    require_once __DIR__ . '/database.php';
    $consulta = Database::getConnection()->prepare(
        'SELECT auth_version FROM usuario WHERE id_usuario = :id_usuario LIMIT 1'
    );
    $consulta->execute(['id_usuario' => $idUsuario]);
    $versaoAtual = $consulta->fetchColumn();

    if (
        $versaoAtual === false
        || !isset($_SESSION['auth_version'])
        || (int) $_SESSION['auth_version'] !== (int) $versaoAtual
    ) {
        invalidar_sessao_usuario();
        return false;
    }

    return true;
}

function invalidar_sessao_usuario(): void
{
    $_SESSION = [];
    if (headers_sent()) {
        session_destroy();
        return;
    }

    session_regenerate_id(true);
}
