# CLAUDE.md — Arkham Files

> Arquivo de contexto para o Claude Code. Compilado e revisado em junho de 2026.
> Cobre histórico completo de decisões, arquitetura, estado atual e roadmap.

---

## 1. Visão Geral do Projeto

**Arkham Files** é uma plataforma self-hosted de rastreamento por QR code e gestão de conteúdo, com tema visual institucional gótico inspirado no Arkham Asylum. Derivado do projeto open-source `tuxxin/qr-track` (licença GPL-3.0), foi completamente reescrito e expandido como projeto pessoal de homelab.

- **Repositório:** `github.com/guilherme1985/qr-track` (branch: `main`)
- **URL pública:** `https://qrtrack.arkhamcloud.net`
- **Versão atual:** `v1.10.0` (roadmap inicial concluído, PR 12 de operações mergeado)
- **Idioma da UI:** Português do Brasil (PT-BR) exclusivamente — arquivo de strings em `templates/strings/pt-br.php`

---

## 2. Arquitetura de Infraestrutura

### Topologia

```
Internet
   │
   ▼
Cloudflare (DNS + Tunnel + Zero Trust Access)
   │  Zero Trust Access protege /admin/*
   ▼
Gateway LXC — 192.168.15.10
   ├─ cloudflared (tunnel para Cloudflare)
   └─ nginx (reverse proxy interno)
         │
         ▼
   App LXC 202 — 192.168.15.50
   ├─ nginx (servidor web, client_max_body_size 6M para uploads)
   ├─ PHP 8.3-FPM (socket: php8.3-fpm-qrtrack.sock)
   │   └─ extensões: pdo_sqlite, gd, finfo, mbstring, opcache
   ├─ SQLite (WAL mode) em data/qrtrack.sqlite
   └─ webroot: /var/www/qrtrack/public
         │
         ▼ (NFS bind mount — mp0)
   NAS — 192.168.15.69 (Umbrel)
   └─ /mnt/nas/backups/qrtrack-backups
         (montado no host Proxmox em /mnt/pve/nas-backups via NFS,
          bind-mounted no LXC como /mnt/backups)
```

### Componentes

| Componente | Detalhe |
|---|---|
| Proxmox host | 192.168.15.42 |
| Gateway LXC | 192.168.15.10 — cloudflared + nginx |
| App LXC 202 | 192.168.15.50 — Debian 12, nginx, PHP 8.3-FPM, SQLite |
| NAS | 192.168.15.69 — Umbrel, disco externo montado via NFS |
| App user | `qrtrack` |
| Webroot | `/var/www/qrtrack/public` |
| PHP socket | `php8.3-fpm-qrtrack.sock` |
| Backup no LXC | `/mnt/backups` (bind mount do NFS do host) |
| Backup destino | `/mnt/nas/backups/qrtrack-backups` (no NAS) |

### Exposição à Internet

- **Cloudflare Tunnel** — tráfego entra via túnel criptografado, sem portas abertas no firewall
- **Zero Trust Access** — protege rotas `/admin/*` com autenticação Cloudflare Access (primeira camada)
- **Login da aplicação + TOTP** — segunda camada de auth dentro do `/admin/*`
- **Sem IP público exposto** — servidor não tem porta 80/443 acessível diretamente
- **IP real dos visitantes** — obtido via header `CF-Connecting-IP` (não `REMOTE_ADDR`)

---

## 3. Stack Técnica

| Camada | Tecnologia |
|---|---|
| Runtime | PHP 8.3-FPM |
| Banco de dados | SQLite (WAL mode) |
| Web server | nginx |
| OS | Debian 12 |
| Router | `bramus/router` |
| QR Code | `endroid/qr-code` v5.1.0 |
| Auth hashing | Argon2id (senhas), bcrypt (recovery codes TOTP) |
| TOTP secrets | AES-256-GCM via OpenSSL (chave derivada de `TOTP_ENCRYPTION_KEY`) |
| Image processing | GD (`php8.3-gd`) — thumbnails 300×300, background `#101010` |
| Gerenciador de deps | Composer (`composer.lock` sempre commitado) |
| Infra | Proxmox + LXC + Cloudflare Tunnel |
| Backup | `sqlite3 .backup` → tarball → NFS/NAS |

---

## 4. Estrutura de Diretórios Completa

```
/var/www/qrtrack/
├── public/                        # webroot (nginx document root)
│   └── index.php                  # entry point (bramus/router)
├── bin/
│   ├── migrate.php                # runner de migrations (USAR SEMPRE)
│   ├── build-qr-icons.php         # gera 36 PNGs de ícones via rsvg-convert
│   ├── create-user.php            # CLI para criar usuários
│   ├── disable-2fa.php            # CLI para resetar TOTP de um usuário
│   ├── backup.sh                  # script de backup diário (PR 12)
│   └── restore.sh                 # script de restore com dry-run + double-confirm
├── data/
│   ├── qrtrack.sqlite             # banco SQLite (WAL mode)
│   └── maintenance.flag           # existe = modo manutenção ativo; conteúdo = mensagem
├── docs/
│   ├── 00-provisionamento-infra.md
│   ├── 01-deploy.md
│   ├── 02-arquitetura.md
│   └── 03-troubleshooting.md      # 1.279 linhas no total (PR 11)
├── migrations/                    # arquivos SQL numerados (0001, 0002…)
├── src/                           # código da aplicação (namespace ArkhamFiles\)
├── templates/
│   ├── strings/
│   │   └── pt-br.php              # todas as strings da UI em PT-BR
│   ├── layouts/
│   │   └── admin.php              # layout admin com sidebar de categorias
│   ├── admin/                     # views da área admin
│   └── public/                    # viewers públicos (note-viewer, strain, image)
├── uploads/                       # FORA do webroot — servido via PHP
│   ├── originals/                 # arquivos originais: {qr_id}_{hash}.{ext}
│   └── thumbs/                    # thumbnails 300×300 cover qualidade 80
├── storage/
│   └── logs/
│       └── backup.log             # log de execução do cron de backup
├── vendor/                        # Composer (não commitado)
├── composer.json
├── composer.lock                  # SEMPRE commitado
└── .env                           # secrets (não commitado)
```

---

## 5. Features Implementadas (v1.10.0)

### Conteúdo e tipos de QR

| Tipo | Viewer público | Comportamento |
|---|---|---|
| `url` | — | 302 redirect para a URL destino |
| `note` | Markdown renderizado server-side, sanitizado | `GET /p/{id}` → note-viewer |
| `strain` | Dossier card com dados genéticos + timeline | `GET /p/{id}` → strain-viewer |
| `image` | Lightbox JS puro, fullscreen, botão download | `GET /p/{id}` → image-viewer; arquivo em `GET /p/{id}/file` |

**Strain** — domínio específico com campos:
- `genetics`: Indica / Sativa / Híbrida
- `source`: Semente / Clone
- `seed_type`: Regular / Feminizada / Automática

**Image** — pipeline de segurança de 8 camadas:
- Limite 5MB no PHP (`MAX_UPLOAD_SIZE` no `.env`) + nginx `client_max_body_size 6M`
- Validação dupla: extensão permitida + MIME real via `finfo_file()` + `getimagesize()` com dimensões válidas (rejeita shells PHP renomeados)
- Filename gerado: `{qr_id}_{hash_aleatorio}.{ext}` — nunca usa nome original
- Armazenamento em `uploads/originals/` e `uploads/thumbs/` — fora do webroot
- Thumbnail 300×300 cover via GD, background `#101010`
- Servido via PHP com `Content-Disposition: inline`, cache headers, `X-Content-Type-Options: nosniff`
- Edit não permite trocar arquivo (URLs estáveis — para mudar, exclui e cria novo)
- Hard delete cascata: `ImageQr::hardDeleteWithFiles()` apaga registro no banco + arquivos físicos (originals + thumbs)

**Expira em:** on-access check sem cron (volume ~300 QRs)
- Campo `expires_at` no banco
- Presets no form: Não expira / 30d / 60d / 90d / Personalizado (datepicker)
- Dashboard: badge "expira em X dias" (amarelo ≤7d, vermelho ≤1d, cinza = expirado)
- Registra scan com `was_expired=1` quando escaneado expirado

### Autenticação e Segurança

- Hash de senha: Argon2id
- CSRF protection em todos os forms
- Rate limiting no login
- TOTP 2FA apenas para admin:
  - Secrets cifrados com AES-256-GCM (chave = SHA-256 de `TOTP_ENCRYPTION_KEY`, IV aleatório de 12 bytes)
  - 10 recovery codes one-shot, formato `XXXXX-XXXXX`, bcrypt, alfabeto sem ambiguidade visual
  - `bin/disable-2fa.php --username=admin` para reset via CLI

### Controle de Acesso (Roles)

| Ação | admin | curator |
|---|---|---|
| Gerenciar categorias | ✅ | ❌ |
| Criar/editar QRs | ✅ | ✅ (próprios) |
| Hard delete | ✅ (confirmação por digitação do título) | ❌ |
| Soft delete | ✅ | ✅ |
| Gerenciar usuários | ✅ | ❌ |
| Configurar 2FA | ✅ | ❌ |
| Bypass modo manutenção | ✅ (banner de aviso) | ❌ |

### Categorias

- Árvore com depth máxima ≤ 3
- Compartilhadas globalmente — apenas admin gerencia (curadores só selecionam)
- Slug auto-gerado com resolução de colisões
- Delete bloqueado por FKs se tem filhas (RESTRICT) ou QRs associados
- Sidebar do admin renderiza árvore real do banco (não mock)

### Operacional

- **Modo manutenção:** `data/maintenance.flag` — existência = ativo; conteúdo = mensagem customizada; admin tem bypass com banner
- **Dashboard analytics:** SUM CASE queries, JOIN scans+qrcodes para acessos recentes, audit_log filtrado para log institucional
- **Audit log:** registra criação, edição, delete de QRs e ações admin; `user_id = NULL` quando usuário é excluído (histórico preservado)
- **Backup automático (PR 12):** cron `03:00 AM` diário como `qrtrack`, script `bin/backup.sh`
  - `sqlite3 .backup` (atômico em WAL), bundle `.env`, tarball de `uploads/`
  - Entregue no NAS via `/mnt/backups`, retenção 7 dias, ~6.8MB validado
  - `bin/restore.sh` com dry-run + double-confirm para restore destrutivo
  - Log em `storage/logs/backup.log`
- **Documentação:** 4 arquivos em `docs/`, 1.279 linhas (PR 11)
- **Migrations:** sempre via `php bin/migrate.php` (nunca `sqlite3 < arquivo.sql` diretamente)

### QR Code Generation

- Endpoints: `GET /p/{id}.svg` e `GET /p/{id}.png`
- Página admin: `/admin/{type}s/{id}/qr` com 4 botões de download (SVG + PNG 300/600/1200px)
- Logo central: ícone da categoria (PNG 22% da área, punchout, error correction High)
- Pipeline de ícones: `bin/build-qr-icons.php` + `rsvg-convert` + Tabler sprite → 36 PNGs

---

## 6. Rotas da Aplicação

### Públicas (sem auth)

| Rota | Método | Função |
|---|---|---|
| `/` | GET | Landing pública (welcome.php) |
| `/p/{public_id}` | GET | Viewer / redirect conforme tipo; registra scan |
| `/p/{public_id}/file` | GET | Serve arquivo de imagem (type=image apenas) |
| `/p/{public_id}.svg` | GET | QR code em SVG |
| `/p/{public_id}.png` | GET | QR code em PNG (300px padrão) |
| `/healthz` | GET | Health check (bypassa manutenção) |

### Admin (Cloudflare Access + login + TOTP)

| Rota | Método | Função |
|---|---|---|
| `/admin/login` | GET/POST | Login usuário+senha |
| `/admin/login/2fa` | GET/POST | Verificação TOTP |
| `/admin/logout` | POST | |
| `/admin/2fa/setup` | GET/POST | Ativar TOTP (primeiro acesso) |
| `/admin/dashboard` | GET | Lista QRs com filtros + analytics |
| `/admin/urls` | GET | Listagem de Links |
| `/admin/urls/new` | GET/POST | Criar Link |
| `/admin/urls/{id}/edit` | GET/POST | Editar |
| `/admin/urls/{id}/qr` | GET | Página QR com downloads |
| `/admin/urls/{id}/delete` | POST | Delete |
| `/admin/notes` | GET | Listagem de Notas |
| `/admin/notes/new` | GET/POST | Criar Nota |
| `/admin/notes/{id}/edit` | GET/POST | Editar |
| `/admin/notes/{id}/qr` | GET | Página QR |
| `/admin/notes/{id}/delete` | POST | Delete |
| `/admin/strains` | GET | Listagem de Strains |
| `/admin/strains/new` | GET/POST | Criar Strain |
| `/admin/strains/{id}/edit` | GET/POST | Editar |
| `/admin/strains/{id}/qr` | GET | Página QR |
| `/admin/strains/{id}/delete` | POST | Delete |
| `/admin/images` | GET | Listagem de Imagens |
| `/admin/images/new` | GET/POST | Upload novo |
| `/admin/images/{id}/edit` | GET/POST | Editar metadados |
| `/admin/images/{id}/qr` | GET | Página QR |
| `/admin/images/{id}/delete` | POST | Delete |
| `/admin/categories` | GET | Gerenciar árvore |
| `/admin/categories/new` | POST | Criar categoria |
| `/admin/categories/{id}/edit` | GET/POST | Editar |
| `/admin/categories/{id}/move` | POST | Mover na árvore |
| `/admin/categories/{id}/delete` | POST | Excluir |
| `/admin/users` | GET | Listar curadores (admin only) |
| `/admin/users/new` | GET/POST | Criar curador |
| `/admin/users/{id}/edit` | GET/POST | Editar curador |
| `/admin/users/{id}/delete` | POST | Excluir curador |
| `/admin/settings` | GET | Configurações |
| `/admin/settings/maintenance` | GET/POST | Toggle modo manutenção |
| `/admin/audit-log` | GET | Trilha de auditoria |

---

## 7. Decisões de Arquitetura — Histórico

### Por que flag de arquivo para manutenção?
Zero-dependência: `data/maintenance.flag` é verificado pelo middleware PHP antes do router processar. Não depende de SQLite nem `.env`. Toggle via UI admin ou via shell — ambos funcionam.

### Por que SQLite?
Volume (~300 QRs) e escala não justificam PostgreSQL/MySQL. WAL mode oferece leituras concorrentes suficientes. `sqlite3 .backup` é atômico.

### Por que on-access para expiração (sem cron)?
Volume pequeno torna o overhead de verificação por request desprezível. Elimina dependência de cron job adicional.

### Por que não trocar arquivo em Image edit?
URLs de imagem são estáticas e podem estar cacheadas pelo Cloudflare. Trocar o arquivo mantendo a URL quebraria o cache de forma silenciosa. Para mudar a imagem: delete + novo QR.

### Categorias compartilhadas vs. por-usuário
Escolha: compartilhadas globalmente, somente admin gerencia a taxonomia. Curador escolhe a categoria, não cria. Schema mais simples, sem ambiguidade de permissões.

### Proteção de rotas admin
Dupla camada: Cloudflare Zero Trust Access (primeira — antes de chegar ao PHP) + autenticação da aplicação com TOTP (segunda). `/admin/*` como prefixo único simplifica a regra no Cloudflare Access.

### NFS para backup
NAS Umbrel disponível no homelab. `all_squash` no NFS causa `chown` a falhar — isso é comportamento esperado, não erro.

---

## 8. Armadilhas Conhecidas (Lessons Learned)

| Problema | Causa | Solução |
|---|---|---|
| URIs com ponto (ex: `/p/xxxx.svg`) retornam 404 no `php -S` mas funcionam em nginx | `bramus/router` calcula `basePath` de `$_SERVER['SCRIPT_NAME']` | Forçar `SCRIPT_NAME=/index.php` ao testar localmente |
| Migrations não aplicadas corretamente | Usar `sqlite3 < arquivo.sql` direto bypassa o tracking | Sempre `php bin/migrate.php` |
| Templates em múltiplos níveis de pasta com `dirname()` errado | Contagem incorreta de níveis | Contar: `dirname(__DIR__, 3)` para três níveis acima |
| `chown` falha no NFS | `all_squash` no export do NFS | Comportamento esperado — ignorar o erro |
| `endroid/qr-code` API errada | v5.1.0 tem API diferente da v5.0 e v4 | Usar `Builder::create()` fluent + `ErrorCorrectionLevel::High` enum (não named constructor nem classe `ErrorCorrectionLevelHigh`) |
| Hotfix 1 endroid (v5.0) | Named constructor parameters | Refatorar para fluent API |
| Hotfix 2 endroid (v4) | Classe `ErrorCorrectionLevelHigh` | Usar enum `ErrorCorrectionLevel::High` |
| Hard delete de imagem deixa arquivo órfão | Só deletar o registro no banco | Usar `ImageQr::hardDeleteWithFiles()` que limpa banco + disco |
| Upload rejeitado silenciosamente | MIME real (finfo) diverge da extensão | Erro exibido como "Formato não permitido" — verificar logs PHP |

---

## 9. Auditoria de Segurança — Fase 1 (Concluída)

Realizada em seis dimensões:

| Dimensão | Status | Detalhe |
|---|---|---|
| Edge / Cloudflare | ✅ | Tunnel + Zero Trust bem configurados |
| nginx / PHP-FPM hardening | ✅ | Pool dedicado como `qrtrack`, dotfiles bloqueados |
| Camada de dados | ✅ | WAL mode, FKs habilitadas via código, secrets minimizados (1 chave) |
| Gestão de secrets | ✅ | `.env` fora do webroot, `TOTP_ENCRYPTION_KEY` única |
| Observabilidade | ⚠️ | Logs sem monitoramento automatizado de anomalias |
| Cadência de updates | ⚠️ | Dependências Composer nunca atualizadas |
| Logrotate | ✅ | Confirmado — gerenciado pelo Debian por padrão |
| Snapshot Proxmox | ✅ | LXC 202 com snapshot local agendado (suplementar ao backup NAS) |

### Itens pendentes (backlog priorizado)

- [ ] **MÉDIO** — Monitoramento de logs para anomalias (fail2ban ou similar)
- [ ] **MÉDIO** — Definir cadência de `composer update` e revisar dependências
- [ ] **BAIXO** — Documentar plano de DR (Disaster Recovery)
- [ ] **BAIXO** — Revisar política de rotação do backup do `.env`

---

## 10. Changelog por PR

| PR | Versão | Tema | Conteúdo |
|---|---|---|---|
| 01 | v1.0.0 | Base | PHP + nginx + migration inicial (`0001_initial_schema.sql`), Config, Database, Http, Migrations |
| 02 | v1.1.0 | Visual | Identidade visual Arkham, admin shell (mock), layout admin com sidebar, fontes/CSS/ícones |
| 03 | v1.2.0 | Auth | Autenticação Argon2id, CSRF, rate limiting, gerenciamento de usuários, strings PT-BR |
| 04 | v1.3.0 | 2FA + Delete | TOTP admin (AES-256-GCM), 10 recovery codes, hard delete curador com confirmação |
| 05 | v1.4.0 | Categorias | CRUD em árvore depth ≤ 3, slug auto-gerado, sidebar real, compartilhadas/admin-only |
| 06 | v1.5.0 | Expiração | On-access check sem cron, `expires_at`, presets no form, badges no dashboard |
| 07 | v1.6.0 | Tipo Note | Markdown viewer público, server-side render sanitizado, CRUD admin |
| 08 | v1.7.0 | Tipo Strain | Dossier card com genetics/source/seed_type, timeline, viewer público |
| 09 | v1.8.0 | Tipo Image | Upload 8 camadas de validação, GD thumbnail 300×300, lightbox JS puro, fora do webroot |
| 9.5 | v1.9.0 | QR Code | `endroid/qr-code` v5.1.0, endpoints `.svg`/`.png`, página QR admin, ícones via rsvg-convert |
| 10 | v1.9.5 | UX/Erros | Modo manutenção, landing `/`, erros temáticos 404/410/403/500, dashboard real, hotfix VER QR |
| 11 | v1.10.0 | Docs | 4 arquivos docs, README atualizado, correção `.env.example` MAX_UPLOAD_SIZE, LICENSE GPL-3.0 |
| 12 | v1.10.0+ | Operacional | Backup automático diário NAS, `bin/backup.sh` + `bin/restore.sh`, retenção 7 dias |

---

## 11. Fluxo de Trabalho (Regras do Projeto)

1. **Discussão primeiro** — toda feature começa com discussão de arquitetura antes de qualquer código
2. **Mockup antes de implementação visual** — especialmente para Fase 3 (redesign)
3. **Validação entre fases** — cada etapa é validada antes da próxima começar
4. **PRs atômicos** — uma feature/fix por PR
5. **Sem código desnecessário** — não gerar código que não foi validado
6. **Migrations apenas via `php bin/migrate.php`** — nunca `sqlite3 < arquivo.sql` direto
7. **`composer.lock` sempre commitado**
8. **UI sempre em PT-BR** — strings em `templates/strings/pt-br.php`

---

## 12. Roadmap — Próximas Fases

### Fase 1 — Segurança / Operacional (em andamento)
> Concluir itens pendentes da auditoria listados na seção 9.

### Fase 2 — Novas Features (planejamento pendente)
> Brainstorm a definir. Fluxo: **discussão → validação de arquitetura → implementação → PR**.

### Fase 3 — Redesign Visual (planejamento pendente)
> Revisão de fontes, cores, componentes visuais com tema Arkham.
> Fluxo: **discussão → mockup visual para validação → implementação**.
> ⚠️ Nenhum código gerado antes de mockup aprovado.

---

## 13. Comandos Úteis

```bash
# Aplicar migrations
php bin/migrate.php

# Testar localmente (forçar SCRIPT_NAME para evitar bug do router com dots)
SCRIPT_NAME=/index.php php -S localhost:8080 -t public/

# Verificar integridade do SQLite
sqlite3 /var/www/qrtrack/data/qrtrack.sqlite "PRAGMA integrity_check;"

# Backup manual (mesmo script do cron)
sudo -u qrtrack /var/www/qrtrack/bin/backup.sh

# Confirmar que cron está ativo
sudo -u qrtrack crontab -l | grep backup

# Log do backup
tail -f /var/www/qrtrack/storage/logs/backup.log

# Verificar logs nginx
tail -f /var/log/nginx/qrtrack-error.log

# Resetar TOTP de um usuário
php bin/disable-2fa.php --username=admin

# Criar usuário via CLI
php bin/create-user.php

# Reconstruir ícones QR
php bin/build-qr-icons.php

# Verificar bind mount do NAS no LXC
mountpoint /mnt/backups || echo "FAIL: bind mount inativo"
```

---

## 14. Referências

- Repositório: `https://github.com/guilherme1985/qr-track`
- Upstream original: `https://github.com/tuxxin/qr-track` (GPL-3.0)
- URL pública: `https://qrtrack.arkhamcloud.net`
- endroid/qr-code v5.1.0: `https://github.com/endroid/qr-code`
- bramus/router: `https://github.com/bramus/router`
