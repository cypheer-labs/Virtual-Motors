<?php

namespace VirtualMotors\Core;

use PDO;
use RuntimeException;

class SchemaMigrationRunner
{
    private const MIGRATION_VERSION = '20261102_add_account_uid_and_persistent_cart';
    private const LOCK_NAME = 'virtual_motors_schema_migration';

    public static function run(PDO $connection): void
    {
        if (self::migrationIsApplied($connection)) {
            return;
        }

        $lock = $connection->query("SELECT GET_LOCK('" . self::LOCK_NAME . "', 15)")->fetchColumn();
        if ((int) $lock !== 1) {
            throw new RuntimeException('Não foi possível bloquear a execução simultânea das migrations.');
        }

        try {
            if (!self::tableExists($connection, 'vm_schema_migrations')) {
                $connection->exec(
                    'CREATE TABLE vm_schema_migrations ('
                    . 'version VARCHAR(191) NOT NULL PRIMARY KEY, '
                    . 'applied_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP'
                    . ') ENGINE=InnoDB DEFAULT CHARSET=utf8mb4'
                );
            }

            if (self::migrationIsApplied($connection)) {
                return;
            }

            self::applyAccountUidAndPersistentCart($connection);

            $registrarVersao = $connection->prepare(
                'INSERT INTO vm_schema_migrations (version) VALUES (:version)'
            );
            $registrarVersao->execute(['version' => self::MIGRATION_VERSION]);
        } finally {
            $connection->query("SELECT RELEASE_LOCK('" . self::LOCK_NAME . "')");
        }
    }

    private static function applyAccountUidAndPersistentCart(PDO $connection): void
    {
        if (!self::tableExists($connection, 'usuario') || !self::tableExists($connection, 'carros')) {
            throw new RuntimeException('As tabelas usuario e carros precisam existir antes da migration de UID.');
        }

        if (self::tableEngine($connection, 'carros') !== 'InnoDB') {
            throw new RuntimeException(
                'A tabela carros precisa usar InnoDB. Aplique primeiro a migration 20261101_convert_cars_to_innodb.sql.'
            );
        }

        if (!self::columnExists($connection, 'usuario', 'uid')) {
            $connection->exec('ALTER TABLE usuario ADD COLUMN uid BIGINT UNSIGNED NULL');
        }

        $connection->exec(
            'UPDATE usuario SET uid = CAST(id_usuario AS UNSIGNED) + 10000000 WHERE uid IS NULL'
        );

        $duplicados = $connection->query(
            'SELECT uid FROM usuario GROUP BY uid HAVING COUNT(*) > 1 LIMIT 1'
        )->fetchColumn();
        if ($duplicados !== false) {
            throw new RuntimeException('Há UIDs duplicados na tabela usuario; corrija-os antes de continuar.');
        }

        $connection->exec('ALTER TABLE usuario MODIFY uid BIGINT UNSIGNED NOT NULL');
        if (!self::hasUniqueUidIndex($connection)) {
            $connection->exec('ALTER TABLE usuario ADD UNIQUE KEY uq_usuario_uid (uid)');
        }

        $connection->exec(
            'CREATE TABLE IF NOT EXISTS carrinho_usuario ('
            . 'id_usuario INT NOT NULL, '
            . 'id_carro INT NOT NULL, '
            . 'quantidade INT UNSIGNED NOT NULL DEFAULT 1, '
            . 'PRIMARY KEY (id_usuario, id_carro), '
            . 'CONSTRAINT carrinho_usuario_ibfk_1 '
            . 'FOREIGN KEY (id_usuario) REFERENCES usuario (id_usuario) ON DELETE CASCADE, '
            . 'CONSTRAINT carrinho_usuario_ibfk_2 '
            . 'FOREIGN KEY (id_carro) REFERENCES carros (id_carro) ON DELETE CASCADE'
            . ') ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci'
        );

        self::assertCartTable($connection);
    }

    private static function tableExists(PDO $connection, string $table): bool
    {
        $consulta = $connection->prepare(
            'SELECT 1 FROM information_schema.TABLES '
            . 'WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :table LIMIT 1'
        );
        $consulta->execute(['table' => $table]);

        return $consulta->fetchColumn() !== false;
    }

    private static function migrationIsApplied(PDO $connection): bool
    {
        if (!self::tableExists($connection, 'vm_schema_migrations')) {
            return false;
        }

        $consulta = $connection->prepare(
            'SELECT 1 FROM vm_schema_migrations WHERE version = :version LIMIT 1'
        );
        $consulta->execute(['version' => self::MIGRATION_VERSION]);

        return $consulta->fetchColumn() !== false;
    }

    private static function columnExists(PDO $connection, string $table, string $column): bool
    {
        $consulta = $connection->prepare(
            'SELECT 1 FROM information_schema.COLUMNS '
            . 'WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :table '
            . 'AND COLUMN_NAME = :column LIMIT 1'
        );
        $consulta->execute(['table' => $table, 'column' => $column]);

        return $consulta->fetchColumn() !== false;
    }

    private static function tableEngine(PDO $connection, string $table): ?string
    {
        $consulta = $connection->prepare(
            'SELECT ENGINE FROM information_schema.TABLES '
            . 'WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :table LIMIT 1'
        );
        $consulta->execute(['table' => $table]);
        $engine = $consulta->fetchColumn();

        return is_string($engine) ? $engine : null;
    }

    private static function hasUniqueUidIndex(PDO $connection): bool
    {
        $consulta = $connection->query(
            "SELECT 1 FROM information_schema.STATISTICS "
            . "WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'usuario' "
            . "AND COLUMN_NAME = 'uid' AND NON_UNIQUE = 0 LIMIT 1"
        );

        return $consulta->fetchColumn() !== false;
    }

    private static function assertCartTable(PDO $connection): void
    {
        foreach (['id_usuario', 'id_carro', 'quantidade'] as $column) {
            if (!self::columnExists($connection, 'carrinho_usuario', $column)) {
                throw new RuntimeException('A tabela carrinho_usuario existe, mas está sem a coluna ' . $column . '.');
            }
        }

        $consulta = $connection->query(
            "SELECT COUNT(DISTINCT REFERENCED_TABLE_NAME) "
            . "FROM information_schema.KEY_COLUMN_USAGE "
            . "WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'carrinho_usuario' "
            . "AND REFERENCED_TABLE_NAME IN ('usuario', 'carros')"
        );
        if ((int) $consulta->fetchColumn() !== 2) {
            throw new RuntimeException(
                'A tabela carrinho_usuario precisa referenciar usuario e carros por chaves estrangeiras.'
            );
        }
    }
}
