<?php

namespace VirtualMotors\Repository;

use VirtualMotors\Core\Database;

class CarroRepository
{
    private static ?bool $colunaNomeExiste = null;

    public function buscarPorIds(array $ids): array
    {
        $ids = array_values(array_unique(array_filter(
            array_map('intval', $ids),
            static fn (int $id): bool => $id > 0
        )));

        if ($ids === []) {
            return [];
        }

        if (!$this->colunaNomeExiste()) {
            return array_values(array_filter(
                $this->veiculosDemonstracao(),
                static fn (array $veiculo): bool => in_array((int) $veiculo['id_carro'], $ids, true)
            ));
        }

        $marcadores = implode(', ', array_fill(0, count($ids), '?'));
        $consulta = Database::getConnection()->prepare(
            "SELECT id_carro, nome, cor, preco, jogo_origem, imagem_url FROM carros WHERE id_carro IN ({$marcadores}) ORDER BY nome"
        );
        $consulta->execute($ids);

        return $this->incluirImagens($consulta->fetchAll());
    }

    public function buscar(string $busca, ?float $precoMaximo = null): array
    {
        if (!$this->colunaNomeExiste()) {
            return array_values(array_filter(
                $this->veiculosDemonstracao(),
                static function (array $veiculo) use ($busca, $precoMaximo): bool {
                    $texto = implode(' ', [
                        $veiculo['nome'],
                        (string) $veiculo['cor'],
                        $veiculo['jogo_origem'],
                    ]);

                    return ($busca === '' || stripos($texto, $busca) !== false)
                        && ($precoMaximo === null || (float) $veiculo['preco'] <= $precoMaximo);
                }
            ));
        }

        $condicoes = [];
        $parametros = [];

        if ($busca !== '') {
            $condicoes[] = '(c.nome LIKE :nome OR c.cor LIKE :cor OR c.jogo_origem LIKE :jogo_origem)';
            $termo = '%' . $busca . '%';
            $parametros['nome'] = $termo;
            $parametros['cor'] = $termo;
            $parametros['jogo_origem'] = $termo;
        }

        if ($precoMaximo !== null) {
            $condicoes[] = 'c.preco <= :preco_maximo';
            $parametros['preco_maximo'] = $precoMaximo;
        }

        $sql = 'SELECT c.id_carro, c.nome, c.cor, c.preco, c.jogo_origem, c.imagem_url, '
            . 'COALESCE(avaliacoes.total_avaliacoes, 0) AS total_avaliacoes, '
            . 'avaliacoes.media_avaliacao '
            . 'FROM carros c '
            . 'LEFT JOIN ('
            . 'SELECT ip.id_carro, COUNT(a.id_avaliacao) AS total_avaliacoes, AVG(a.estrelas) AS media_avaliacao '
            . 'FROM item_pedido ip INNER JOIN avaliacao a ON a.id_pedido = ip.id_pedido '
            . 'GROUP BY ip.id_carro'
            . ') avaliacoes ON avaliacoes.id_carro = c.id_carro';
        if ($condicoes !== []) {
            $sql .= ' WHERE ' . implode(' AND ', $condicoes);
        }
        $sql .= ' ORDER BY c.nome';

        $consulta = Database::getConnection()->prepare($sql);
        $consulta->execute($parametros);

        return $this->incluirImagens($consulta->fetchAll());
    }

    private function colunaNomeExiste(): bool
    {
        if (self::$colunaNomeExiste === null) {
            $consulta = Database::getConnection()->query("SHOW COLUMNS FROM carros LIKE 'nome'");
            self::$colunaNomeExiste = $consulta->fetch() !== false;
        }

        return self::$colunaNomeExiste;
    }

    private function veiculosDemonstracao(): array
    {
        return $this->incluirImagens([
            ['id_carro' => 1, 'nome' => 'Ferrari 488 GTB', 'cor' => 'Vermelho', 'preco' => 2500000, 'jogo_origem' => 'Forza Horizon'],
            ['id_carro' => 2, 'nome' => 'Lamborghini Huracán', 'cor' => 'Amarelo', 'preco' => 3000000, 'jogo_origem' => 'Forza Horizon'],
            ['id_carro' => 3, 'nome' => 'Porsche 911', 'cor' => 'Preto', 'preco' => 1800000, 'jogo_origem' => 'Need for Speed'],
            ['id_carro' => 4, 'nome' => 'Nissan Skyline GT-R', 'cor' => 'Azul', 'preco' => 900000, 'jogo_origem' => 'Need for Speed'],
            ['id_carro' => 5, 'nome' => 'BMW M4', 'cor' => 'Branco', 'preco' => 1200000, 'jogo_origem' => 'Forza Horizon'],
            ['id_carro' => 6, 'nome' => 'McLaren 720S', 'cor' => 'Laranja', 'preco' => 2800000, 'jogo_origem' => 'Forza Horizon'],
        ]);
    }

    private function incluirImagens(array $veiculos): array
    {
        $imagens = [
            'ferrari' => 'https://images.unsplash.com/photo-1552519507-da3b142c6e3d?auto=format&fit=crop&w=1200&q=85',
            'lamborghini' => 'https://images.unsplash.com/photo-1614200187524-dc4b892acf16?auto=format&fit=crop&w=1200&q=85',
            'porsche' => 'https://images.unsplash.com/photo-1503376780353-7e6692767b70?auto=format&fit=crop&w=1200&q=85',
            'nissan' => 'https://images.unsplash.com/photo-1549317661-bd32c8ce0db2?auto=format&fit=crop&w=1200&q=85',
            'bmw' => 'https://images.unsplash.com/photo-1555215695-3004980ad54e?auto=format&fit=crop&w=1200&q=85',
            'mclaren' => 'https://images.unsplash.com/photo-1492144534655-ae79c964c9d7?auto=format&fit=crop&w=1200&q=85',
        ];

        foreach ($veiculos as &$veiculo) {
            $veiculo['total_avaliacoes'] = (int) ($veiculo['total_avaliacoes'] ?? 0);
            $veiculo['media_avaliacao'] = isset($veiculo['media_avaliacao'])
                ? (float) $veiculo['media_avaliacao']
                : null;
            $nome = strtolower((string) $veiculo['nome']);
            $veiculo['imagem'] = $imagens['ferrari'];
            foreach ($imagens as $modelo => $imagem) {
                if (str_contains($nome, $modelo)) {
                    $veiculo['imagem'] = $imagem;
                    break;
                }
            }
            $imagemUrl = $veiculo['imagem_url'] ?? null;
            if (is_string($imagemUrl) && filter_var($imagemUrl, FILTER_VALIDATE_URL)) {
                $esquema = strtolower((string) parse_url($imagemUrl, PHP_URL_SCHEME));
                if (in_array($esquema, ['http', 'https'], true)) {
                    $veiculo['imagem'] = $imagemUrl;
                }
            }
        }
        unset($veiculo);

        return $veiculos;
    }
}
