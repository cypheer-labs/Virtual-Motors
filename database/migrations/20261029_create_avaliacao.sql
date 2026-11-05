CREATE TABLE avaliacao (
    id_avaliacao INT NOT NULL AUTO_INCREMENT,
    id_pedido INT NOT NULL,
    id_usuario INT NOT NULL,
    estrelas TINYINT UNSIGNED NOT NULL,
    descricao VARCHAR(300) NOT NULL,
    criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id_avaliacao),
    UNIQUE KEY uq_avaliacao_pedido (id_pedido),
    KEY idx_avaliacao_usuario (id_usuario),
    CONSTRAINT fk_avaliacao_pedido
        FOREIGN KEY (id_pedido) REFERENCES pedido (id_pedido)
        ON DELETE CASCADE,
    CONSTRAINT fk_avaliacao_usuario
        FOREIGN KEY (id_usuario) REFERENCES usuario (id_usuario)
        ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;