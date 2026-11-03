<?php

namespace VirtualMotors\Repository;

use PDO;
use RuntimeException;
use Throwable;
use VirtualMotors\Core\Database;

class PedidoRepository
{
    private PDO $connection;

    public function __construct(?PDO $connection = null)
    {
        $this->connection = $connection ?? Database::getConnection();
    }

    public function criar(array $pedido): int
    {
        $this->connection->beginTransaction();

        try {
            $consultaUid = $this->connection->prepare(
                'SELECT uid FROM usuario WHERE id_usuario = :id_usuario FOR UPDATE'
            );
            $consultaUid->execute(['id_usuario' => $pedido['id_usuario']]);
            $uidUsuario = $consultaUid->fetchColumn();
            if ($uidUsuario === false || !ctype_digit((string) $uidUsuario) || strlen((string) $uidUsuario) < 8) {
                throw new RuntimeException('A conta não possui um UID válido para associar ao pedido.');
            }

            $inserirPedido = $this->connection->prepare(
                'INSERT INTO pedido (id_usuario, valor_total, pagamento, status_pedido, endereco_entrega) '
                . 'VALUES (:id_usuario, :valor_total, :pagamento, :status_pedido, :endereco_entrega)'
            );
            $inserirPedido->execute([
                'id_usuario' => $pedido['id_usuario'],
                'valor_total' => $pedido['valor_total'],
                'pagamento' => $pedido['pagamento'],
                'status_pedido' => 'Confirmado',
                'endereco_entrega' => $pedido['endereco_entrega'],
            ]);

            $idPedido = (int) $this->connection->lastInsertId();
            $inserirItem = $this->connection->prepare(
                'INSERT INTO item_pedido '
                . '(id_pedido, id_carro, quantidade, preco_unitario, nome_carro, jogo_origem) '
                . 'VALUES (:id_pedido, :id_carro, :quantidade, :preco_unitario, :nome_carro, :jogo_origem)'
            );

            foreach ($pedido['itens'] as $item) {
                $inserirItem->execute([
                    'id_pedido' => $idPedido,
                    'id_carro' => $item['id_carro'],
                    'quantidade' => $item['quantidade'],
                    'preco_unitario' => $item['preco_unitario'],
                    'nome_carro' => $item['nome_carro'],
                    'jogo_origem' => $item['jogo_origem'],
                ]);
            }

            $atualizarCarrinho = $this->connection->prepare(
                'UPDATE carrinho_usuario SET quantidade = quantidade - :quantidade_subtrair '
                . 'WHERE id_usuario = :id_usuario AND id_carro = :id_carro '
                . 'AND quantidade > :quantidade_limite'
            );
            $removerCarrinho = $this->connection->prepare(
                'DELETE FROM carrinho_usuario '
                . 'WHERE id_usuario = :id_usuario AND id_carro = :id_carro '
                . 'AND quantidade <= :quantidade_limite'
            );
            foreach ($pedido['itens'] as $item) {
                $atualizarCarrinho->execute([
                    'id_usuario' => $pedido['id_usuario'],
                    'id_carro' => $item['id_carro'],
                    'quantidade_subtrair' => $item['quantidade'],
                    'quantidade_limite' => $item['quantidade'],
                ]);
                $removerCarrinho->execute([
                    'id_usuario' => $pedido['id_usuario'],
                    'id_carro' => $item['id_carro'],
                    'quantidade_limite' => $item['quantidade'],
                ]);
            }

            $this->connection->commit();

            return $idPedido;
        } catch (Throwable $erro) {
            if ($this->connection->inTransaction()) {
                $this->connection->rollBack();
            }

            throw $erro;
        }
    }

    public function buscarPorUsuario(int $idUsuario): array
    {
        $consulta = $this->connection->prepare(
            'SELECT p.id_pedido, p.criado_em, p.valor_total, p.pagamento, p.status_pedido, '
            . 'p.endereco_entrega, u.uid AS uid_usuario, i.id_carro, i.nome_carro, i.jogo_origem, '
            . 'i.quantidade, i.preco_unitario '
            . 'FROM pedido p JOIN usuario u ON u.id_usuario = p.id_usuario '
            . 'LEFT JOIN item_pedido i ON i.id_pedido = p.id_pedido '
            . 'WHERE p.id_usuario = :id_usuario ORDER BY p.criado_em DESC, p.id_pedido DESC'
        );
        $consulta->execute(['id_usuario' => $idUsuario]);

        $pedidos = [];
        foreach ($consulta->fetchAll() as $linha) {
            $idPedido = (int) $linha['id_pedido'];
            if (!isset($pedidos[$idPedido])) {
                $pedidos[$idPedido] = [
                    'id_pedido' => $idPedido,
                    'criado_em' => $linha['criado_em'],
                    'valor_total' => $linha['valor_total'],
                    'pagamento' => $linha['pagamento'],
                    'status_pedido' => $linha['status_pedido'],
                    'endereco_entrega' => $linha['endereco_entrega'],
                    'uid_usuario' => (string) $linha['uid_usuario'],
                    'itens' => [],
                ];
            }

            if ($linha['id_carro'] !== null) {
                $pedidos[$idPedido]['itens'][] = [
                    'id_carro' => (int) $linha['id_carro'],
                    'nome_carro' => $linha['nome_carro'],
                    'jogo_origem' => $linha['jogo_origem'],
                    'quantidade' => (int) $linha['quantidade'],
                    'preco_unitario' => $linha['preco_unitario'],
                ];
            }
        }

        return array_values($pedidos);
    }

    public function buscarRecentesPorUsuario(int $idUsuario, int $limite = 3): array
    {
        $limite = max(1, min($limite, 10));
        $consulta = $this->connection->prepare(
            'SELECT p.id_pedido, p.criado_em, p.valor_total, p.status_pedido, u.uid AS uid_usuario '
            . 'FROM pedido p JOIN usuario u ON u.id_usuario = p.id_usuario '
            . 'WHERE p.id_usuario = :id_usuario '
            . 'ORDER BY p.criado_em DESC, p.id_pedido DESC LIMIT ' . $limite
        );
        $consulta->execute(['id_usuario' => $idUsuario]);

        return $consulta->fetchAll();
    }
}
