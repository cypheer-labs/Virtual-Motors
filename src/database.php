<?php

namespace VirtualMotors\Core;

use PDO;
use PDOException;

class Database
{
    private static ?PDO $connection = null;

    private static function carregarConfig(): array
    {
        $servidorNome = $_SERVER['SERVER_NAME'] ?? '';
        $ambienteLocal = in_array($servidorNome, ['localhost', '127.0.0.1'], true);
        $arquivoConfigPrivada = dirname(__DIR__) . DIRECTORY_SEPARATOR . 'config' . DIRECTORY_SEPARATOR . 'database.config.php';
        $arquivoConfigProducao = dirname(__DIR__) . DIRECTORY_SEPARATOR . 'config' . DIRECTORY_SEPARATOR . 'config.producao.php';

        if (is_file($arquivoConfigPrivada)) {
            $configuracoes = require $arquivoConfigPrivada;
            $ambiente = $ambienteLocal ? 'local' : 'production';

            if (!is_array($configuracoes) || !isset($configuracoes[$ambiente]) || !is_array($configuracoes[$ambiente])) {
                throw new \RuntimeException("A configuração do banco para o ambiente '{$ambiente}' é inválida.");
            }

            $config = $configuracoes[$ambiente];
            foreach (['host', 'database', 'username', 'password'] as $chave) {
                if (!isset($config[$chave]) || !is_string($config[$chave])) {
                    throw new \RuntimeException("A configuração do banco não contém um valor válido para '{$chave}'.");
                }
            }

            $config['port'] = isset($config['port']) ? (int) $config['port'] : 3306;

            return $config;
        }

        if (!$ambienteLocal && is_file($arquivoConfigProducao)) {
            return require $arquivoConfigProducao;
        }

        return [
            'host' => '127.0.0.1',
            'port' => 3306,
            'database' => 'loja_carros',
            'username' => 'root',
            'password' => '',
        ];
    }

    public static function getConnection(): PDO
    {
        if (self::$connection === null) {
            $config = self::carregarConfig();
            $servidor = $config['host'];
            $porta = (int) $config['port'];
            $banco = $config['database'];
            $usuario = $config['username'];
            $senha = $config['password'];

            try {
                $socket = @stream_socket_client(
                    "tcp://{$servidor}:{$porta}",
                    $codigoErro,
                    $mensagemErro,
                    1
                );

                if ($socket === false) {
                    throw new PDOException('O servidor MySQL não está disponível.', $codigoErro);
                }

                stream_set_timeout($socket, 1);
                $saudacaoMySql = fread($socket, 4);
                $tempoEsgotado = stream_get_meta_data($socket)['timed_out'];
                fclose($socket);

                if ($saudacaoMySql === false || strlen($saudacaoMySql) < 4 || $tempoEsgotado) {
                    throw new PDOException('O servidor MySQL não respondeu.', 2002);
                }

                $connection = new PDO(
                    "mysql:host={$servidor};port={$porta};dbname={$banco};charset=utf8mb4;connect_timeout=1",
                    $usuario,
                    $senha,
                    [
                        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                        PDO::ATTR_EMULATE_PREPARES => false,
                        PDO::ATTR_TIMEOUT => 1,
                    ]
                );
            } catch (PDOException $e) {
                error_log($e->getMessage());
                throw new PDOException(
                    'Não foi possível conectar ao banco de dados.',
                    (int) $e->getCode(),
                    $e
                );
            }

            require_once __DIR__ . '/schemamigrationrunner.php';
            try {
                SchemaMigrationRunner::run($connection);
            } catch (PDOException $exception) {
                error_log('Falha ao aplicar migration do banco: ' . $exception->getMessage());
                throw new \RuntimeException(
                    'A conexão funcionou, mas não foi possível aplicar uma atualização necessária do banco.',
                    0,
                    $exception
                );
            } catch (\RuntimeException $exception) {
                error_log('Falha ao preparar o esquema do banco: ' . $exception->getMessage());
                throw $exception;
            }

            self::$connection = $connection;
        }

        return self::$connection;
    }
}
