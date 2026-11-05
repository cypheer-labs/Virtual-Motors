ALTER TABLE usuario
    ADD COLUMN cor_fundo_perfil CHAR(7) NOT NULL DEFAULT '#1c1f22',
    ADD COLUMN bio_perfil TEXT DEFAULT NULL;
