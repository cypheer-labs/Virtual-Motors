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
            'SELECT id_usuario AS id, uid, nome, email, senha, tipo_usuario, auth_version, '
            . 'foto_perfil, banner_perfil '
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

    public function findAccountById(int $id): ?array
    {
        $stmt = $this->connection->prepare(
            'SELECT id_usuario AS id, uid, nome, email, telefone, endereco, tipo_usuario '
            . 'FROM usuario WHERE id_usuario = :id LIMIT 1'
        );
        $stmt->execute(['id' => $id]);
        $conta = $stmt->fetch();

        return $conta ?: null;
    }

    public function updatePersonalData(int $id, string $nome): bool
    {
        $stmt = $this->connection->prepare(
            'UPDATE usuario SET nome = :nome WHERE id_usuario = :id'
        );

        return $stmt->execute(['nome' => $nome, 'id' => $id]);
    }

    public function updateContactData(int $id, string $email, ?string $telefone, ?string $endereco): bool
    {
        $stmt = $this->connection->prepare(
            'UPDATE usuario SET email = :email, telefone = :telefone, endereco = :endereco '
            . 'WHERE id_usuario = :id'
        );

        return $stmt->execute([
            'email' => $email,
            'telefone' => $telefone,
            'endereco' => $endereco,
            'id' => $id,
        ]);
    }

    public function emailExistsForAnotherUser(string $email, int $idUsuario): bool
    {
        $stmt = $this->connection->prepare(
            'SELECT 1 FROM usuario WHERE email = :email AND id_usuario <> :id_usuario LIMIT 1'
        );
        $stmt->execute(['email' => $email, 'id_usuario' => $idUsuario]);

        return (bool) $stmt->fetchColumn();
    }

    public function findPasswordHashById(int $id): ?string
    {
        $stmt = $this->connection->prepare(
            'SELECT senha FROM usuario WHERE id_usuario = :id LIMIT 1'
        );
        $stmt->execute(['id' => $id]);
        $senhaHash = $stmt->fetchColumn();

        return $senhaHash === false ? null : (string) $senhaHash;
    }

    public function updatePassword(int $id, string $senhaHashAtual, string $novaSenhaHash): ?int
    {
        $this->connection->beginTransaction();

        try {
            $stmt = $this->connection->prepare(
                'UPDATE usuario SET senha = :nova_senha, auth_version = auth_version + 1 '
                . 'WHERE id_usuario = :id AND senha = :senha_atual'
            );
            $stmt->execute([
                'nova_senha' => $novaSenhaHash,
                'id' => $id,
                'senha_atual' => $senhaHashAtual,
            ]);

            if ($stmt->rowCount() !== 1) {
                $this->connection->commit();
                return null;
            }

            $consultaVersao = $this->connection->prepare(
                'SELECT auth_version FROM usuario WHERE id_usuario = :id LIMIT 1'
            );
            $consultaVersao->execute(['id' => $id]);
            $versao = $consultaVersao->fetchColumn();
            if ($versao === false) {
                throw new \RuntimeException('Não foi possível confirmar a versão atual da sessão.');
            }

            $this->connection->commit();

            return (int) $versao;
        } catch (\Throwable $erro) {
            if ($this->connection->inTransaction()) {
                $this->connection->rollBack();
            }
            throw $erro;
        }
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
