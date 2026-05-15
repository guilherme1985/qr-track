# 02 · Arquitetura

Overview de componentes, camadas, modelos e fluxos do Arkham Files.

---

## 1. Topologia de infraestrutura

```
                         Internet
                            │
                            ▼
                ┌───────────────────────┐
                │  Cloudflare CDN/Edge  │
                │  + Cloudflare Access  │  ← bloqueia /admin/* antes de chegar no servidor
                └───────────┬───────────┘
                            │ Cloudflare Tunnel (saída do datacenter, sem porta aberta)
                            ▼
                ┌───────────────────────┐
                │  Gateway LXC          │
                │  192.168.15.10        │
                │  cloudflared + nginx  │  ← reverse proxy, injeta X-Forwarded-For
                └───────────┬───────────┘
                            │ HTTP interno (LAN)
                            ▼
                ┌───────────────────────┐
                │  qrtrack LXC          │
                │  192.168.15.50        │
                │  nginx + PHP-FPM      │
                │  /var/www/qrtrack     │
                │  ┌─────────────────┐  │
                │  │  SQLite WAL     │  │
                │  │  data/*.sqlite  │  │
                │  └─────────────────┘  │
                └───────────────────────┘
```

**Por que dois LXCs?** Isolamento. Se o gateway cair, o app continua acessível via LAN para debug. Se o app pega RCE, o atacante não tem acesso direto ao tunnel/credenciais Cloudflare.

---

## 2. Camadas da aplicação (request lifecycle)

```
HTTP request → nginx → PHP-FPM → public/index.php
                                       │
                                       ▼
                                 ┌─────────────┐
                                 │ Bootstrap   │  ← carrega .env, init session config, DB PDO
                                 └──────┬──────┘
                                        ▼
                          ┌─────────────────────────┐
                          │ Middleware Manutenção   │  ← checa data/maintenance.flag
                          └──────┬──────────────────┘
                                 │ (bypass: admin logado, /healthz, /admin/login, /assets/, /uploads/)
                                 ▼
                          ┌─────────────────────────┐
                          │ Router (bramus/router)  │  ← casa pattern → handler
                          └──────┬──────────────────┘
                                 ▼
                  ┌──────────────┴──────────────┐
                  │                             │
                  ▼                             ▼
        ┌────────────────┐            ┌──────────────────┐
        │ Rotas públicas │            │ Rotas /admin/*   │
        │ /              │            │ Auth::requireAuth│
        │ /healthz       │            │ ↓                │
        │ /p/{id}        │            │ enforce password │
        │ /p/{id}.svg    │            │ enforce 2FA      │
        │ /p/{id}.png    │            │ require role     │
        └────────┬───────┘            └──────────┬───────┘
                 │                                │
                 └─────────────┬──────────────────┘
                               ▼
                  ┌───────────────────────────┐
                  │ Handler (closure no       │
                  │ public/index.php)         │
                  │ ↓                         │
                  │ Models (src/*.php)        │  ← QrCode, Note, Strain, ImageQr, Category, User…
                  │ ↓                         │
                  │ Template (templates/*)    │
                  │ ↓                         │
                  │ HTML response             │
                  └───────────────────────────┘
                               │
                               ▼
                          (Audit::log)         ← eventos sensíveis vão pro audit_log
```

---

## 3. Modelos (camada `src/`)

### Auth

| Classe | Responsabilidade |
|---|---|
| `Auth\Auth` | Login, logout, currentUser, requireAuth, requireRole, enforce* |
| `Auth\Session` | Wrapper de session_*, CSRF token + verify |
| `Auth\User` | Modelo. Roles: `ROLE_ADMIN`, `ROLE_CURATOR` |
| `Auth\PasswordPolicy` | Validação (min 12 chars, 3 de 4 classes, não comum) |
| `Auth\PasswordGenerator` | Gera senhas temporárias |
| `Auth\RateLimit` | Limita login por IP + username (escalada exponencial) |
| `Auth\TwoFactor` | Setup/verify TOTP, recovery codes, QR de provisionamento |
| `Auth\Crypto` | AES-256-GCM para cifrar TOTP secrets em repouso |
| `Auth\Audit` | Log de eventos (login, mudança, deleção, manutenção, etc.) |
| `Auth\LoginResult` | Enum de resultados (success, failed, locked, requires2FA, requiresPasswordChange) |

### Domínio

| Classe | Responsabilidade |
|---|---|
| `QrCode` | Modelo central. `public_id`, `type`, `expires_at`, `is_deleted`, `is_disabled` |
| `Category` | Árvore (até 3 níveis), ícone, cor |
| `CategoryAttributes` | Atributos específicos por categoria (extensível) |
| `Note` | Conteúdo Markdown |
| `Strain` | Perfil de cultivo (timeline com cálculos derivados) |
| `ImageQr` | Metadados de imagem (referencia arquivo em `uploads/`) |
| `ImageUpload` | Pipeline de upload (8 camadas de validação) |
| `Markdown` | Wrapper sobre Parsedown Extra + sanitização HTML |
| `QrRenderer` | Wrapper sobre `endroid/qr-code` v5.1 |

### Infraestrutura

| Classe | Responsabilidade |
|---|---|
| `Bootstrap` | Setup inicial: .env, autoload, DB, session, locale |
| `Config` | Acesso ao .env tipado |
| `Database` | PDO singleton SQLite com WAL mode |
| `Migrations` | Runner versionado (tabela `_migrations`) |
| `Http` | Helpers: clientIp (respeita proxy), userAgent |
| `I18n` | Strings PT-BR (`templates/strings/pt-br.php`) com `t('chave.sub')` |
| `Icon` | Renderiza ícones SVG inline (do sprite Tabler) |
| `View` | Helpers de render (não muito usado — preferência por `require` direto) |
| `Maintenance` | Toggle do modo manutenção (arquivo `data/maintenance.flag`) |
| `helpers.php` | Funções globais: `e()`, `t()`, `icon()` |

---

## 4. Schema do banco

Resumo das tabelas (ver `migrations/*.sql` para definições completas):

```
users
├── id, username, email, password_hash, role
├── totp_secret_enc, totp_enabled
├── recovery_codes_enc (JSON array de hashes)
├── must_change_password
├── created_at, updated_at, last_login_at

categories
├── id, parent_id (FK auto-referente, ON DELETE SET NULL)
├── name, slug
├── icon (nome do ícone Tabler), color
├── sort_order, depth (cache do nível)
├── created_at, updated_at

category_attributes
├── id, category_id (FK), key, value
└── (UNIQUE category_id+key)

qrcodes
├── id, public_id (UNIQUE), type
├── title, category_id (FK ON DELETE SET NULL)
├── created_by (FK users ON DELETE CASCADE)
├── expires_at (NULL = sem expiração)
├── is_deleted, is_disabled
├── created_at, updated_at

note_metadata          → 1:1 com qrcodes (type=note)
├── qr_id (FK ON DELETE CASCADE), content (Markdown até 64KB)

strain_metadata        → 1:1 com qrcodes (type=strain)
├── qr_id (FK), 20+ campos de cultivo

image_metadata         → 1:1 com qrcodes (type=image)
├── qr_id (FK), file_path, thumbnail_path
├── mime_type, file_size, width, height
└── CHECK file_size <= 5242880

scans
├── id, qr_id (FK ON DELETE CASCADE)
├── ip_address, user_agent, referer, scanned_at

audit_log
├── id, event_type, user_id (FK ON DELETE SET NULL)
├── target_type, target_id, metadata (JSON)
├── ip_address, user_agent, created_at

login_attempts          → rate limit
├── id, username, ip_address, success, attempted_at

_migrations             → controle de versão do schema
├── version, applied_at
```

---

## 5. Sistema de tipos (extensibilidade)

QRs têm um campo `type` discriminador. Cada tipo tem:
- Tabela de metadados própria (`*_metadata`) com 1:1 ao `qrcodes`
- Modelo PHP dedicado (`Note`, `Strain`, `ImageQr`)
- Viewer público (`templates/public/{tipo}-viewer.php`)
- CRUD admin (`templates/admin/qrcodes/{tipo}s/*.php`)
- Strings em `templates/strings/pt-br.php` sob `admin.{tipo}s`

**Para adicionar um tipo novo** (ex.: `vcard`):
1. Migration nova criando `vcard_metadata`
2. `src/Vcard.php` com create/update/findByQrId/etc.
3. Templates `admin/qrcodes/vcards/index.php`, `new.php`, `edit.php`, `delete.php`
4. `templates/public/vcard-viewer.php`
5. 10 handlers em `public/index.php` (listar/criar/editar/deletar/restore/hard-delete)
6. Item de sidebar em `templates/layouts/admin.php`
7. Strings novas em PT-BR
8. Ícone do tipo no array `$typeIcons` do dashboard

Os 3 tipos atuais (`note`, `strain`, `image`) servem de template.

---

## 6. Segurança em camadas

```
1. Cloudflare WAF              ← bloqueia bots, ataques conhecidos
2. Cloudflare Access           ← bloqueia /admin/* sem email autorizado
3. Cloudflare Tunnel           ← sem porta aberta na rede pública
4. Gateway LXC                 ← isolamento de processo (cloudflared não roda no app)
5. nginx headers + .file deny  ← SAMEORIGIN, nosniff, .git/.env bloqueados
6. App: rate limit             ← 5 tentativas em 15min, escalada exponencial
7. App: CSRF tokens            ← todo POST checa _csrf
8. App: Argon2id               ← password hashing forte
9. App: 2FA TOTP obrigatório   ← pra admins
10. App: audit log             ← rastreabilidade de todos eventos
11. SQLite WAL + FK            ← integridade referencial
12. AES-256-GCM em TOTP secrets ← criptografia em repouso
13. Upload: 8 camadas de validação ← MIME real via finfo, anti-PHP-shell renomeado
14. nginx no /uploads/         ← serve estático, .php → 403
```

---

## 7. Fluxos importantes

### Login completo

```
POST /admin/login (username, password, _csrf)
  ↓
RateLimit::check
  ↓ (ok)
Auth::authenticate
  ├─ User::findByUsername
  ├─ password_verify (Argon2id)
  ├─ Audit::log(login_success | login_failed)
  ↓
LoginResult::RequiresPasswordChange  ← se must_change_password=1
  → redirect /admin/change-password
LoginResult::Requires2FASetup       ← admin sem 2FA configurado
  → redirect /admin/2fa/setup
LoginResult::Requires2FAVerification ← admin com 2FA configurado
  → redirect /admin/2fa/verify
LoginResult::Success
  → redirect /admin/dashboard
```

### Scan de QR público

```
GET /p/{public_id}
  ↓
QrCode::findByPublicId
  ↓
QrCode is_deleted?         → 404
QrCode is_disabled?        → 404
QrCode is expired?         → 410 (template error.php)
  ↓
INSERT em scans (ip, user_agent, referer)
  ↓
match type:
  case 'note':   render templates/public/note-viewer.php
  case 'strain': render templates/public/strain-viewer.php
  case 'image':  render templates/public/image-viewer.php
```

### Geração de QR (PR 9.5)

```
GET /p/{public_id}.svg|.png?size=...&plain=...
  ↓
[mesmas validações do viewer público]
  ↓
QrRenderer::render
  ├─ Builder::create (endroid v5.1 fluent API)
  ├─ writer SvgWriter | PngWriter
  ├─ errorCorrectionLevel High (30%)
  ├─ logoPath (ícone da categoria, se houver e formato=PNG)
  ↓
Response com Cache-Control: public, max-age=3600
```

### Modo manutenção

```
QUALQUER request
  ↓
public/index.php (linhas 53-72)
  ↓
Maintenance::isActive()  ← file_exists(data/maintenance.flag)
  ↓ (true)
Maintenance::shouldBypass($uri)
  ├─ /healthz                     → bypass
  ├─ /admin/login                 → bypass
  ├─ /admin/2fa/*                 → bypass
  ├─ /admin/forgot-password       → bypass
  ├─ /assets/*                    → bypass
  ├─ /uploads/*                   → bypass
  ↓ (false)
try Session::start + Auth::currentUser
  ↓
admin logado? → bypass com banner
  ↓ (não)
Maintenance::render → HTTP 503 + tela 503 temática + Retry-After: 3600
```

---

## 8. Convenções de código

- **PSR-4 namespace** `ArkhamFiles\` mapeado em `src/`
- **`declare(strict_types=1);`** no topo de todo arquivo PHP
- **Imports explícitos** (`use Foo\Bar;`), nunca `\Foo\Bar` inline
- **DB**: queries com PDO prepared statements (anti-SQL injection)
- **Output**: sempre via `e($var)` helper (escapa HTML)
- **i18n**: nunca strings hardcoded em PT-BR — sempre via `t('chave.sub')`
- **Templates**: PHP puro (sem template engine), com `ob_start()` + `$bodyContent = ob_get_clean()` no padrão
- **Audit**: eventos sensíveis SEMPRE chamam `Audit::log(...)`
- **CSRF**: todo POST verifica via helper `$verifyCsrf()` em `public/index.php`
- **Rotas**: ordem importa no `bramus/router` — mais específico antes do mais genérico
