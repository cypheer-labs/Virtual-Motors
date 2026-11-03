<?php

namespace VirtualMotors\Repository;

use PDO;
use RuntimeException;
use Throwable;
use VirtualMotors\Core\Database;

class PedidoRepository
{
    public const STATUS_VALIDOS = ['Confirmado', 'Em preparação', 'Concluído', 'Cancelado'];

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

    public function buscarTodosParaAdministracao(): array
    {
        $consulta = $this->connection->query(
            'SELECT p.id_pedido, p.id_usuario, p.criado_em, p.valor_total, p.pagamento, '
            . 'p.status_pedido, p.endereco_entrega, u.nome AS nome_usuario, u.uid AS uid_usuario, '
            . 'i.id_carro, i.nome_carro, i.jogo_origem, i.quantidade, i.preco_unitario '
            . 'FROM pedido p JOIN usuario u ON u.id_usuario = p.id_usuario '
            . 'LEFT JOIN item_pedido i ON i.id_pedido = p.id_pedido '
            . 'ORDER BY p.criado_em DESC, p.id_pedido DESC'
        );

        $pedidos = [];
        foreach ($consulta->fetchAll(PDO::FETCH_ASSOC) as $linha) {
            $idPedido = (int) $linha['id_pedido'];
            if (!isset($pedidos[$idPedido])) {
                $pedidos[$idPedido] = [
                    'id_pedido' => $idPedido,
                    'id_usuario' => (int) $linha['id_usuario'],
                    'criado_em' => $linha['criado_em'],
                    'valor_total' => $linha['valor_total'],
                    'pagamento' => $linha['pagamento'],
                    'status_pedido' => $linha['status_pedido'],
                    'endereco_entrega' => $linha['endereco_entrega'],
                    'nome_usuario' => $linha['nome_usuario'],
                    'uid_usuario' => (string) $linha['uid_usuario'],
                    'itens' => [],
                ];
            }

            if ($linha['id_carro'] !== null) {
                $pedidos[$idPedido]['itens'][] = [
                    'nome_carro' => $linha['nome_carro'],
                    'jogo_origem' => $linha['jogo_origem'],
                    'quantidade' => (int) $linha['quantidade'],
                ];
            }
        }

        return array_values($pedidos);
    }

    public function atualizarPelaAdministracao(int $idPedido, string $status, string $enderecoEntrega): bool
    {
        $atualizar = $this->connection->prepare(
            'UPDATE pedido SET status_pedido = :status_pedido, endereco_entrega = :endereco_entrega '
            . 'WHERE id_pedido = :id_pedido'
        );
        $atualizar->execute([
            'status_pedido' => $status,
            'endereco_entrega' => $enderecoEntrega,
            'id_pedido' => $idPedido,
        ]);

        if ($atualizar->rowCount() > 0) {
            return true;
        }

        $verificar = $this->connection->prepare(
            'SELECT 1 FROM pedido WHERE id_pedido = :id_pedido LIMIT 1'
        );
        $verificar->execute(['id_pedido' => $idPedido]);

        return $verificar->fetchColumn() !== false;
    }

    public function cancelarPorUsuario(int $idPedido, int $idUsuario): bool
    {
        $cancelar = $this->connection->prepare(
            "UPDATE pedido SET status_pedido = 'Cancelado' "
            . 'WHERE id_pedido = :id_pedido AND id_usuario = :id_usuario '
            . "AND status_pedido NOT IN ('Concluído', 'Cancelado')"
        );
        $cancelar->execute([
            'id_pedido' => $idPedido,
            'id_usuario' => $idUsuario,
        ]);

        return $cancelar->rowCount() > 0;
    }

    public function excluirPelaAdministracao(int $idPedido): bool
    {
        return $this->excluirPedido($idPedido, null);
    }

    private function excluirPedido(int $idPedido, ?int $idUsuario): bool
    {
        $this->connection->beginTransaction();

        try {
            $condicaoUsuario = $idUsuario === null ? '' : ' AND id_usuario = :id_usuario';
            $buscarPedido = $this->connection->prepare(
                'SELECT id_pedido FROM pedido WHERE id_pedido = :id_pedido'
                . $condicaoUsuario . ' FOR UPDATE'
            );
            $parametros = ['id_pedido' => $idPedido];
            if ($idUsuario !== null) {
                $parametros['id_usuario'] = $idUsuario;
            }
            $buscarPedido->execute($parametros);

            if ($buscarPedido->fetchColumn() === false) {
                $this->connection->rollBack();

                return false;
            }

            $excluirItens = $this->connection->prepare(
                'DELETE FROM item_pedido WHERE id_pedido = :id_pedido'
            );
            $excluirItens->execute(['id_pedido' => $idPedido]);

            $excluirPedido = $this->connection->prepare(
                'DELETE FROM pedido WHERE id_pedido = :id_pedido'
            );
            $excluirPedido->execute(['id_pedido' => $idPedido]);
            if ($excluirPedido->rowCount() !== 1) {
                $this->connection->rollBack();

                return false;
            }

            $this->connection->commit();

            return true;
        } catch (Throwable $erro) {
            if ($this->connection->inTransaction()) {
                $this->connection->rollBack();
            }

            throw $erro;
        }
    }
}
