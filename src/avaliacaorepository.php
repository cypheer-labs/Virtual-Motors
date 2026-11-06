<?php

namespace VirtualMotors\Repository;

use PDO;
use VirtualMotors\Core\Database;

class AvaliacaoRepository
{
    private PDO $connection;

    public function __construct(?PDO $connection = null)
    {
        $this->connection = $connection ?? Database::getConnection();
    }

    public function listarTodas(): array
    {
        $consulta = $this->connection->query(
            'SELECT a.id_avaliacao, a.estrelas, a.descricao, a.criado_em, u.id_usuario, u.nome, u.foto_perfil, '
            . 'p.criado_em AS data_compra, '
            . 'ip.id_carro, COALESCE(NULLIF(ip.nome_carro, \'\'), c.nome) AS nome_produto '
            . 'FROM avaliacao a '
            . 'INNER JOIN usuario u ON u.id_usuario = a.id_usuario '
            . 'INNER JOIN pedido p ON p.id_pedido = a.id_pedido '
            . 'LEFT JOIN item_pedido ip ON ip.id_pedido = p.id_pedido '
            . 'LEFT JOIN carros c ON c.id_carro = ip.id_carro '
            . 'ORDER BY a.criado_em DESC, a.id_avaliacao DESC, nome_produto'
        );

        $avaliacoes = [];
        foreach ($consulta->fetchAll(PDO::FETCH_ASSOC) as $linha) {
            $idAvaliacao = (int) $linha['id_avaliacao'];
            if (!isset($avaliacoes[$idAvaliacao])) {
                $avaliacoes[$idAvaliacao] = [
                    'estrelas' => (int) $linha['estrelas'],
                    'descricao' => $linha['descricao'],
                    'criado_em' => $linha['criado_em'],
                    'id_usuario' => (int) $linha['id_usuario'],
                    'nome' => $linha['nome'],
                    'foto_perfil' => $linha['foto_perfil'],
                    'data_compra' => $linha['data_compra'],
                    'produtos' => [],
                    'veiculos' => [],
                ];
            }

            $nomeProduto = $linha['nome_produto'];
            if (!is_string($nomeProduto) || $nomeProduto === '') {
                continue;
            }

            $idCarro = $linha['id_carro'] === null ? null : (int) $linha['id_carro'];
            $chaveProduto = $idCarro !== null && $idCarro > 0
                ? 'carro-' . $idCarro
                : 'produto-' . $nomeProduto;
            $avaliacoes[$idAvaliacao]['produtos'][$chaveProduto] = $nomeProduto;

            if ($idCarro !== null && $idCarro > 0) {
                $avaliacoes[$idAvaliacao]['veiculos'][$idCarro] = [
                    'id_carro' => $idCarro,
                    'nome' => $nomeProduto,
                ];
            }
        }

        foreach ($avaliacoes as &$avaliacao) {
            $avaliacao['produtos'] = implode(', ', array_values($avaliacao['produtos']));
            $avaliacao['veiculos'] = array_values($avaliacao['veiculos']);
        }
        unset($avaliacao);

        return array_values($avaliacoes);
    }

    public function buscarPedidoConfirmado(int $idPedido, int $idUsuario): ?array
    {
        $consulta = $this->connection->prepare(
            "SELECT p.id_pedido, p.criado_em AS data_compra, "
            . "(SELECT GROUP_CONCAT(DISTINCT COALESCE(NULLIF(ip.nome_carro, ''), c.nome) "
            . "ORDER BY COALESCE(NULLIF(ip.nome_carro, ''), c.nome) SEPARATOR ', ') "
            . "FROM item_pedido ip LEFT JOIN carros c ON c.id_carro = ip.id_carro "
            . "WHERE ip.id_pedido = p.id_pedido) AS produtos "
            . "FROM pedido p "
            . "WHERE p.id_pedido = :id_pedido AND p.id_usuario = :id_usuario "
            . "AND p.status_pedido LIKE 'Confirmado%' LIMIT 1"
        );
        $consulta->execute([
            'id_pedido' => $idPedido,
            'id_usuario' => $idUsuario,
        ]);

        $pedido = $consulta->fetch();

        return $pedido ?: null;
    }

    public function buscarPorPedido(int $idPedido): ?array
    {
        $consulta = $this->connection->prepare(
            'SELECT estrelas, descricao, criado_em FROM avaliacao WHERE id_pedido = :id_pedido LIMIT 1'
        );
        $consulta->execute(['id_pedido' => $idPedido]);

        $avaliacao = $consulta->fetch();

        return $avaliacao ?: null;
    }

    public function criar(int $idPedido, int $idUsuario, int $estrelas, string $descricao): void
    {
        $consulta = $this->connection->prepare(
            'INSERT INTO avaliacao (id_pedido, id_usuario, estrelas, descricao) '
            . 'VALUES (:id_pedido, :id_usuario, :estrelas, :descricao)'
        );
        $consulta->execute([
            'id_pedido' => $idPedido,
            'id_usuario' => $idUsuario,
            'estrelas' => $estrelas,
            'descricao' => $descricao,
        ]);
    }
}
