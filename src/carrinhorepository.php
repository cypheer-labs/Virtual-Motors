<?php

namespace VirtualMotors\Repository;

use InvalidArgumentException;
use PDO;
use Throwable;
use VirtualMotors\Core\Database;

class CarrinhoRepository
{
    private PDO $connection;

    public function __construct(?PDO $connection = null)
    {
        $this->connection = $connection ?? Database::getConnection();
    }

    public function buscar(int $idUsuario): array
    {
        $consulta = $this->connection->prepare(
            'SELECT id_carro, quantidade FROM carrinho_usuario WHERE id_usuario = :id_usuario'
        );
        $consulta->execute(['id_usuario' => $idUsuario]);

        $carrinho = [];
        foreach ($consulta->fetchAll(PDO::FETCH_ASSOC) as $item) {
            $carrinho[(int) $item['id_carro']] = (int) $item['quantidade'];
        }

        return $carrinho;
    }

    public function combinar(array $carrinhoSalvo, array $carrinhoAtual): array
    {
        $carrinhoMesclado = $carrinhoSalvo;
        foreach ($carrinhoAtual as $idCarro => $quantidade) {
            $idCarro = filter_var($idCarro, FILTER_VALIDATE_INT);
            $quantidade = filter_var($quantidade, FILTER_VALIDATE_INT);
            if ($idCarro === false || $idCarro < 1 || $quantidade === false || $quantidade < 1) {
                throw new InvalidArgumentException('O carrinho contém um item inválido.');
            }

            $carrinhoMesclado[$idCarro] = ($carrinhoMesclado[$idCarro] ?? 0) + $quantidade;
        }

        return $carrinhoMesclado;
    }

    public function salvar(int $idUsuario, array $carrinho): void
    {
        $this->connection->beginTransaction();

        try {
            $remover = $this->connection->prepare(
                'DELETE FROM carrinho_usuario WHERE id_usuario = :id_usuario'
            );
            $remover->execute(['id_usuario' => $idUsuario]);

            $inserir = $this->connection->prepare(
                'INSERT INTO carrinho_usuario (id_usuario, id_carro, quantidade) '
                . 'VALUES (:id_usuario, :id_carro, :quantidade)'
            );
            foreach ($carrinho as $idCarro => $quantidade) {
                $idCarro = filter_var($idCarro, FILTER_VALIDATE_INT);
                $quantidade = filter_var($quantidade, FILTER_VALIDATE_INT);
                if ($idCarro === false || $idCarro < 1 || $quantidade === false || $quantidade < 1) {
                    throw new InvalidArgumentException('O carrinho contém um item inválido.');
                }

                $inserir->execute([
                    'id_usuario' => $idUsuario,
                    'id_carro' => $idCarro,
                    'quantidade' => $quantidade,
                ]);
            }

            $this->connection->commit();
        } catch (Throwable $erro) {
            if ($this->connection->inTransaction()) {
                $this->connection->rollBack();
            }

            throw $erro;
        }
    }
}
