ALTER TABLE usuario
    ADD COLUMN uid BIGINT UNSIGNED NULL;

UPDATE usuario
SET uid = CAST(id_usuario AS UNSIGNED) + 10000000
WHERE uid IS NULL;

ALTER TABLE usuario
    MODIFY uid BIGINT UNSIGNED NOT NULL,
    ADD UNIQUE KEY uq_usuario_uid (uid);

CREATE TABLE carrinho_usuario (
    id_usuario INT NOT NULL,
    id_carro INT NOT NULL,
    quantidade INT UNSIGNED NOT NULL DEFAULT 1,
    PRIMARY KEY (id_usuario, id_carro),
    CONSTRAINT carrinho_usuario_ibfk_1
        FOREIGN KEY (id_usuario) REFERENCES usuario (id_usuario) ON DELETE CASCADE,
    CONSTRAINT carrinho_usuario_ibfk_2
        FOREIGN KEY (id_carro) REFERENCES carros (id_carro) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
