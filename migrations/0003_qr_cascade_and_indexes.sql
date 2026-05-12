-- =====================================================================
-- 0003_qr_cascade_and_indexes.sql
--
-- Preparação pro PR 07 (Tipo Nota — primeiro CRUD de QRs):
--
-- 1. Muda `qrcodes.created_by` de ON DELETE SET NULL para CASCADE.
--    Quando um curador é excluído (PR 04), suas notas/strains/imagens
--    também desaparecem. Decisão tomada lá atrás: limpeza completa em
--    cascata mantém o acervo coeso (sem QRs órfãos perdidos).
--
-- 2. Índice em `qrcodes.type` pra filtros rápidos no admin
--    (listagem por tipo: "todas as notas", "todas as strains").
--
-- 3. Índice em `qrcodes.created_by` (existia FK mas sem índice; queries
--    de "todas as notas do curador X" agora são instantâneas).
--
-- SQLite não suporta ALTER de FK constraint diretamente — precisa
-- recriar a tabela. O conteúdo é preservado integralmente.
-- =====================================================================

BEGIN;

-- 1. Cria tabela com o novo schema
CREATE TABLE qrcodes_new (
    id          INTEGER PRIMARY KEY AUTOINCREMENT,
    public_id   TEXT     NOT NULL UNIQUE,
    type        TEXT     NOT NULL CHECK (type IN (
        'url', 'wifi', 'vcard', 'phone', 'sms', 'email',
        'maps', 'social', 'note', 'strain', 'image'
    )),
    title       TEXT     NOT NULL,
    payload     TEXT,
    category_id INTEGER  REFERENCES categories(id) ON DELETE SET NULL,
    logo_path   TEXT,
    is_disabled INTEGER  NOT NULL DEFAULT 0 CHECK (is_disabled IN (0, 1)),
    is_deleted  INTEGER  NOT NULL DEFAULT 0 CHECK (is_deleted IN (0, 1)),
    expires_at  DATETIME,
    -- MUDANÇA: SET NULL → CASCADE
    created_by  INTEGER  REFERENCES users(id) ON DELETE CASCADE,
    created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
);

-- 2. Copia o conteúdo (preserva tudo)
INSERT INTO qrcodes_new
    SELECT * FROM qrcodes;

-- 3. Substitui a tabela antiga
DROP TABLE qrcodes;
ALTER TABLE qrcodes_new RENAME TO qrcodes;

-- 4. Recria índices que existiam na tabela original
CREATE INDEX idx_qrcodes_category ON qrcodes(category_id);
CREATE INDEX idx_qrcodes_expires
    ON qrcodes(expires_at)
    WHERE expires_at IS NOT NULL AND is_deleted = 0;

-- 5. Novos índices pro PR 07
CREATE INDEX idx_qrcodes_type        ON qrcodes(type) WHERE is_deleted = 0;
CREATE INDEX idx_qrcodes_created_by  ON qrcodes(created_by);

COMMIT;
