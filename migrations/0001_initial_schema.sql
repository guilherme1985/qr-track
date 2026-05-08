-- =====================================================================
-- ARKHAM FILES · Migration 0001 · Initial schema (v1.0.0)
-- =====================================================================
-- All tables for a fresh install. Subsequent migrations should be
-- additive (new tables, new columns) and avoid destructive changes
-- without explicit data-migration steps.
--
-- Conventions:
--   - INTEGER PRIMARY KEY AUTOINCREMENT for surrogate keys
--   - DATETIME stored as ISO-8601 (CURRENT_TIMESTAMP yields UTC-style)
--   - 0/1 for booleans, with CHECK to enforce
--   - Foreign keys with explicit ON DELETE policy
--   - Indexes named idx_<table>_<columns>
-- =====================================================================


-- =====================================================================
-- users — admin authentication
-- =====================================================================
CREATE TABLE users (
    id              INTEGER PRIMARY KEY AUTOINCREMENT,
    username        TEXT     NOT NULL UNIQUE,
    password_hash   TEXT     NOT NULL,
    -- TOTP secret stored encrypted (AES-GCM with TOTP_ENCRYPTION_KEY)
    totp_secret     TEXT,
    totp_enabled    INTEGER  NOT NULL DEFAULT 0 CHECK (totp_enabled IN (0, 1)),
    -- JSON array of bcrypt-hashed recovery codes
    recovery_codes  TEXT,
    failed_attempts INTEGER  NOT NULL DEFAULT 0,
    locked_until    DATETIME,
    last_login_at   DATETIME,
    created_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
);


-- =====================================================================
-- categories — hierarchical tree, max depth 3
-- =====================================================================
CREATE TABLE categories (
    id         INTEGER PRIMARY KEY AUTOINCREMENT,
    parent_id  INTEGER REFERENCES categories(id) ON DELETE RESTRICT,
    name       TEXT     NOT NULL,
    slug       TEXT     NOT NULL,
    icon       TEXT,
    color      TEXT,
    sort_order INTEGER  NOT NULL DEFAULT 0,
    depth      INTEGER  NOT NULL CHECK (depth BETWEEN 0 AND 3),
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
);

-- Slugs únicos por nível: separamos root e children porque SQLite trata
-- cada NULL em UNIQUE como distinto (não enforça unicidade de root).
CREATE UNIQUE INDEX idx_categories_slug_root
    ON categories(slug)
    WHERE parent_id IS NULL;

CREATE UNIQUE INDEX idx_categories_slug_child
    ON categories(parent_id, slug)
    WHERE parent_id IS NOT NULL;

CREATE INDEX idx_categories_parent ON categories(parent_id);
CREATE INDEX idx_categories_depth  ON categories(depth);


-- =====================================================================
-- qrcodes — main entity
-- =====================================================================
CREATE TABLE qrcodes (
    id          INTEGER PRIMARY KEY AUTOINCREMENT,
    -- Public-facing short ID exposed in URLs (/p/{public_id}).
    -- Format suggestion: 4+4 hex (XXXX-XX), human-readable.
    public_id   TEXT     NOT NULL UNIQUE,
    type        TEXT     NOT NULL CHECK (type IN (
        'url', 'wifi', 'vcard', 'phone', 'sms', 'email',
        'maps', 'social', 'note', 'strain', 'image'
    )),
    title       TEXT     NOT NULL,
    -- JSON payload for simple types (url, wifi, vcard, phone, ...).
    -- Rich types (note, strain, image) use dedicated _metadata tables.
    payload     TEXT,
    category_id INTEGER  REFERENCES categories(id) ON DELETE SET NULL,
    logo_path   TEXT,
    is_disabled INTEGER  NOT NULL DEFAULT 0 CHECK (is_disabled IN (0, 1)),
    is_deleted  INTEGER  NOT NULL DEFAULT 0 CHECK (is_deleted IN (0, 1)),
    expires_at  DATETIME,
    created_by  INTEGER  REFERENCES users(id) ON DELETE SET NULL,
    created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE INDEX idx_qrcodes_category ON qrcodes(category_id);
CREATE INDEX idx_qrcodes_expires
    ON qrcodes(expires_at)
    WHERE expires_at IS NOT NULL;
CREATE INDEX idx_qrcodes_listing  ON qrcodes(is_deleted, is_disabled, type);


-- =====================================================================
-- note_metadata — Markdown notes
-- =====================================================================
CREATE TABLE note_metadata (
    qr_id            INTEGER PRIMARY KEY REFERENCES qrcodes(id) ON DELETE CASCADE,
    -- App-side limit: 64 KB
    markdown_content TEXT     NOT NULL DEFAULT '',
    updated_at       DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
);


-- =====================================================================
-- strain_metadata — cannabis cultivation data
-- =====================================================================
CREATE TABLE strain_metadata (
    qr_id           INTEGER PRIMARY KEY REFERENCES qrcodes(id) ON DELETE CASCADE,
    strain_name     TEXT     NOT NULL,
    source          TEXT     NOT NULL CHECK (source IN ('semente', 'clone')),
    genetics        TEXT     NOT NULL CHECK (genetics IN ('indica', 'sativa', 'hibrida')),
    -- Nullable: applies only to source='semente' (validated app-side)
    seed_type       TEXT     CHECK (seed_type IS NULL OR seed_type IN ('regular', 'feminizada', 'automatica')),
    planting_date   DATE,
    flowering_date  DATE,
    harvest_date    DATE,
    updated_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
);


-- =====================================================================
-- image_metadata — uploaded images (stored outside webroot)
-- =====================================================================
CREATE TABLE image_metadata (
    qr_id             INTEGER PRIMARY KEY REFERENCES qrcodes(id) ON DELETE CASCADE,
    file_path         TEXT     NOT NULL,
    thumbnail_path    TEXT     NOT NULL,
    mime_type         TEXT     NOT NULL CHECK (mime_type IN ('image/jpeg', 'image/png', 'image/webp')),
    file_size         INTEGER  NOT NULL CHECK (file_size > 0 AND file_size <= 1048576),
    width             INTEGER,
    height            INTEGER,
    original_filename TEXT,
    uploaded_at       DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
);


-- =====================================================================
-- scans — tracking (one row per QR scan)
-- =====================================================================
CREATE TABLE scans (
    id          INTEGER PRIMARY KEY AUTOINCREMENT,
    qr_id       INTEGER  NOT NULL REFERENCES qrcodes(id) ON DELETE CASCADE,
    ip_address  TEXT,
    user_agent  TEXT,
    country     TEXT,
    region      TEXT,
    city        TEXT,
    isp         TEXT,
    is_proxy    INTEGER  NOT NULL DEFAULT 0 CHECK (is_proxy IN (0, 1)),
    referer     TEXT,
    -- True if scan happened against an already-expired QR
    was_expired INTEGER  NOT NULL DEFAULT 0 CHECK (was_expired IN (0, 1)),
    scanned_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE INDEX idx_scans_qr      ON scans(qr_id);
CREATE INDEX idx_scans_when    ON scans(scanned_at);
CREATE INDEX idx_scans_qr_when ON scans(qr_id, scanned_at);


-- =====================================================================
-- audit_log — admin actions trail (90-day retention via app cron)
-- =====================================================================
CREATE TABLE audit_log (
    id          INTEGER PRIMARY KEY AUTOINCREMENT,
    user_id     INTEGER REFERENCES users(id) ON DELETE SET NULL,
    -- e.g. 'login_success', 'login_failure', 'qr_created', 'category_deleted'
    event_type  TEXT     NOT NULL,
    target_type TEXT,
    target_id   INTEGER,
    ip_address  TEXT,
    user_agent  TEXT,
    -- JSON with extra context
    metadata    TEXT,
    created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE INDEX idx_audit_user  ON audit_log(user_id);
CREATE INDEX idx_audit_when  ON audit_log(created_at);
CREATE INDEX idx_audit_event ON audit_log(event_type);


-- =====================================================================
-- login_attempts — rate limiting
-- =====================================================================
CREATE TABLE login_attempts (
    id                 INTEGER PRIMARY KEY AUTOINCREMENT,
    ip_address         TEXT     NOT NULL,
    username_attempted TEXT,
    success            INTEGER  NOT NULL CHECK (success IN (0, 1)),
    attempted_at       DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE INDEX idx_login_attempts_ip_when ON login_attempts(ip_address, attempted_at);


-- =====================================================================
-- Triggers — auto-update updated_at columns on UPDATE
-- =====================================================================
-- The WHEN clause prevents an extra UPDATE when updated_at was already
-- set explicitly in the calling statement. SQLite recursive_triggers
-- defaults to OFF so an infinite loop is also impossible.

CREATE TRIGGER trg_users_updated_at
AFTER UPDATE ON users
WHEN OLD.updated_at = NEW.updated_at
BEGIN
    UPDATE users SET updated_at = CURRENT_TIMESTAMP WHERE id = NEW.id;
END;

CREATE TRIGGER trg_categories_updated_at
AFTER UPDATE ON categories
WHEN OLD.updated_at = NEW.updated_at
BEGIN
    UPDATE categories SET updated_at = CURRENT_TIMESTAMP WHERE id = NEW.id;
END;

CREATE TRIGGER trg_qrcodes_updated_at
AFTER UPDATE ON qrcodes
WHEN OLD.updated_at = NEW.updated_at
BEGIN
    UPDATE qrcodes SET updated_at = CURRENT_TIMESTAMP WHERE id = NEW.id;
END;

CREATE TRIGGER trg_note_metadata_updated_at
AFTER UPDATE ON note_metadata
WHEN OLD.updated_at = NEW.updated_at
BEGIN
    UPDATE note_metadata SET updated_at = CURRENT_TIMESTAMP WHERE qr_id = NEW.qr_id;
END;

CREATE TRIGGER trg_strain_metadata_updated_at
AFTER UPDATE ON strain_metadata
WHEN OLD.updated_at = NEW.updated_at
BEGIN
    UPDATE strain_metadata SET updated_at = CURRENT_TIMESTAMP WHERE qr_id = NEW.qr_id;
END;
