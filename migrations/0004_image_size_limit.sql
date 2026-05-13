-- =====================================================================
-- 0004_image_size_limit.sql
--
-- Aumenta o limite de file_size em image_metadata de 1 MB (1048576) pra
-- 5 MB (5242880). Decisão tomada no escopo do PR 09.
--
-- SQLite não suporta ALTER de CHECK constraint diretamente — recria a
-- tabela. Conteúdo é preservado integralmente.
-- =====================================================================

BEGIN;

CREATE TABLE image_metadata_new (
    qr_id             INTEGER PRIMARY KEY REFERENCES qrcodes(id) ON DELETE CASCADE,
    file_path         TEXT     NOT NULL,
    thumbnail_path    TEXT     NOT NULL,
    mime_type         TEXT     NOT NULL CHECK (mime_type IN ('image/jpeg', 'image/png', 'image/webp')),
    -- Limite app-side reforçado server-side em ImageUpload::MAX_BYTES
    file_size         INTEGER  NOT NULL CHECK (file_size > 0 AND file_size <= 5242880),
    width             INTEGER,
    height            INTEGER,
    original_filename TEXT,
    uploaded_at       DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
);

INSERT INTO image_metadata_new SELECT * FROM image_metadata;

DROP TABLE image_metadata;
ALTER TABLE image_metadata_new RENAME TO image_metadata;

COMMIT;
