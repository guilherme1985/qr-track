-- =====================================================================
-- 0002 · Auth columns
--
-- Adiciona ao schema do PR 01 os atributos necessários pra autenticação
-- completa, gerenciamento de usuários e reset de senha pelo admin:
--
--   email                  → endereço de contato (não é usado pra auth)
--   role                   → 'admin' | 'curator' (default curator pra novos users)
--   disabled_at            → soft-delete; user inativo não pode logar
--   must_change_password   → 1 quando admin reseta senha; força mudança no
--                            próximo login
--   password_changed_at    → timestamp da última mudança (auditoria)
--
-- Convenção: SQLite ALTER TABLE ADD COLUMN só aceita uma coluna por
-- statement, daí o ALTER repetido. NOT NULL sem DEFAULT só funciona em
-- tabelas vazias — todas as adições aqui têm DEFAULT explícito ou são
-- nullable.
-- =====================================================================

ALTER TABLE users ADD COLUMN email                TEXT;
ALTER TABLE users ADD COLUMN role                 TEXT    NOT NULL DEFAULT 'curator'
                                                  CHECK (role IN ('admin', 'curator'));
ALTER TABLE users ADD COLUMN disabled_at          DATETIME;
ALTER TABLE users ADD COLUMN must_change_password INTEGER NOT NULL DEFAULT 0
                                                  CHECK (must_change_password IN (0, 1));
ALTER TABLE users ADD COLUMN password_changed_at  DATETIME;

-- Índice pra lookup rápido por email (nullable mas indexed; SQLite ignora
-- linhas com NULL nesse índice de forma natural)
CREATE INDEX idx_users_email ON users(email);

-- Índice pra filtrar usuários ativos rápido
CREATE INDEX idx_users_active ON users(disabled_at) WHERE disabled_at IS NULL;
