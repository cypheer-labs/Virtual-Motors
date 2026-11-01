<?php

namespace VirtualMotors\Repository;

use PDO;
use PDOException;
use RuntimeException;
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
            'SELECT id_usuario AS id, uid, nome, email, senha, tipo_usuario FROM usuario WHERE email = :email LIMIT 1'
        );
        $stmt->execute(['email' => $email]);

        $usuario = $stmt->fetch();

        return $usuario ?: null;
    }

    public function emailExists(string $email): bool
    {
        $stmt = $this->connection->prepare('SELECT 1 FROM usuario WHERE email = :email LIMIT 1');
        $stmt->execute(['email' => $email]);

        return (bool) $stmt->fetchColumn();
    }

    public function findUidById(int $idUsuario): ?string
    {
        $stmt = $this->connection->prepare('SELECT uid FROM usuario WHERE id_usuario = :id_usuario LIMIT 1');
        $stmt->execute(['id_usuario' => $idUsuario]);
        $uid = $stmt->fetchColumn();

        return $uid === false ? null : (string) $uid;
    }

    public function create(
        string $nome,
        string $email,
        string $senhaHash,
        string $tipoUsuario = 'cliente',
        string $telefone = '',
        ?string $endereco = null
    ): ?string {
        for ($tentativa = 0; $tentativa < 5; $tentativa++) {
            // O índice UNIQUE no banco é a proteção final contra colisões simultâneas.
            $uid = (string) random_int(10_000_000, 99_999_999);
            $stmt = $this->connection->prepare(
                'INSERT INTO usuario (uid, nome, email, senha, telefone, endereco, tipo_usuario) '
                . 'VALUES (:uid, :nome, :email, :senha, :telefone, :endereco, :tipo_usuario)'
            );

            try {
                $stmt->execute([
                    'uid' => $uid,
                    'nome' => $nome,
                    'email' => $email,
                    'senha' => $senhaHash,
                    'telefone' => $telefone !== '' ? $telefone : null,
                    'endereco' => $endereco,
                    'tipo_usuario' => $tipoUsuario,
                ]);

                return $uid;
            } catch (PDOException $exception) {
                if ($exception->getCode() !== '23000') {
                    throw $exception;
                }
                if ($this->emailExists($email)) {
                    return null;
                }

                $consultaUid = $this->connection->prepare('SELECT 1 FROM usuario WHERE uid = :uid LIMIT 1');
                $consultaUid->execute(['uid' => $uid]);
                if ($consultaUid->fetchColumn() === false) {
                    throw $exception;
                }
            }
        }

        throw new RuntimeException('Não foi possível gerar um UID exclusivo após várias tentativas.');
    }
}
