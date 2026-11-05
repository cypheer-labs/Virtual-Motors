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
            'SELECT a.estrelas, a.descricao, a.criado_em, u.nome, p.criado_em AS data_compra, '
            . '(SELECT GROUP_CONCAT(DISTINCT COALESCE(NULLIF(ip.nome_carro, \'\'), c.nome) '
            . 'ORDER BY COALESCE(NULLIF(ip.nome_carro, \'\'), c.nome) SEPARATOR \', \') '
            . 'FROM item_pedido ip LEFT JOIN carros c ON c.id_carro = ip.id_carro '
            . 'WHERE ip.id_pedido = p.id_pedido) AS produtos '
            . 'FROM avaliacao a '
            . 'INNER JOIN usuario u ON u.id_usuario = a.id_usuario '
            . 'INNER JOIN pedido p ON p.id_pedido = a.id_pedido '
            . 'ORDER BY a.criado_em DESC, a.id_avaliacao DESC'
        );

        return $consulta->fetchAll();
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