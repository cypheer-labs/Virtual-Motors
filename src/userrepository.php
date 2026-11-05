<?php

namespace VirtualMotors\Repository;

use PDO;
use VirtualMotors\Core\Database;

class UserRepository
{
    private PDO $connection;

    public function __construct(?PDO $connection = null)
    {
        $this->connection = $connection ?? Database::getConnection();
    }

    public function findByEmail(string $email): ?array
    {
        $stmt = $this->connection->prepare(
            'SELECT id_usuario AS id, nome, email, senha, tipo_usuario, foto_perfil, banner_perfil '
            . 'FROM usuario WHERE email = :email LIMIT 1'
        );
        $stmt->execute(['email' => $email]);

        $usuario = $stmt->fetch();

        return $usuario ?: null;
    }

    public function findProfileById(int $id): ?array
    {
        $stmt = $this->connection->prepare(
            'SELECT nome, foto_perfil, banner_perfil, bio_perfil '
            . 'FROM usuario WHERE id_usuario = :id LIMIT 1'
        );
        $stmt->execute(['id' => $id]);

        $perfil = $stmt->fetch();

        return $perfil ?: null;
    }

    public function updateProfile(
        int $id,
        string $nome,
        string $bioPerfil,
        ?string $fotoPerfil,
        ?string $bannerPerfil
    ): bool {
        $stmt = $this->connection->prepare(
            'UPDATE usuario SET nome = :nome, bio_perfil = :bio_perfil, '
            . 'foto_perfil = COALESCE(:foto_perfil, foto_perfil), '
            . 'banner_perfil = COALESCE(:banner_perfil, banner_perfil) '
            . 'WHERE id_usuario = :id'
        );

        return $stmt->execute([
            'nome' => $nome,
            'bio_perfil' => $bioPerfil,
            'foto_perfil' => $fotoPerfil,
            'banner_perfil' => $bannerPerfil,
            'id' => $id,
        ]);
    }

    public function emailExists(string $email): bool
    {
        $stmt = $this->connection->prepare('SELECT 1 FROM usuario WHERE email = :email LIMIT 1');
        $stmt->execute(['email' => $email]);

        return (bool) $stmt->fetchColumn();
    }

    public function create(
        string $nome,
        string $email,
        string $senhaHash,
        string $tipoUsuario = 'cliente',
        string $telefone = ''
    ): bool {
        $stmt = $this->connection->prepare(
            'INSERT INTO usuario (nome, email, senha, telefone, tipo_usuario) '
            . 'VALUES (:nome, :email, :senha, :telefone, :tipo_usuario)'
        );

        return $stmt->execute([
            'nome' => $nome,
            'email' => $email,
            'senha' => $senhaHash,
            'telefone' => $telefone,
            'tipo_usuario' => $tipoUsuario,
        ]);
    }
}
