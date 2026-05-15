# 🦇 Arkham Files

> **QR Code generator and tracker** com identidade visual Arkham Asylum.
> Self-hosted. PHP 8.3 + SQLite. Sem cloud, sem ads, sem analytics de terceiros.

---

## Heritage

Este projeto é um **fork de [tuxxin/qr-track](https://github.com/tuxxin/qr-track)**, licenciado sob GPL-3.0. A partir do tag `v1.0.0` o código foi substancialmente reescrito; do upstream restou apenas a ideia conceitual de "QR público com tracking de acessos".

O que mudou:

- Identidade visual nova (tema **Arkham Files** — verde-fósforo + ouro vitoriano sobre preto)
- Hash de senha **Argon2id** + autenticação **2FA TOTP** obrigatória para admins
- **Categorias hierárquicas** (árvore com profundidade até 3) com ícones e cores
- Tipos de QR **estruturados**: Notas (Markdown), Strain (perfil de cultivo), Imagem (upload com thumbnail)
- **Expiração** configurável (30/60/90 dias ou nunca)
- **Geração de QR Code** própria (SVG vetorial + PNG nos 3 tamanhos) com logo da categoria no centro
- **Modo manutenção** via arquivo flag
- Dashboard com contadores reais, audit log e listagem de acessos
- Localização **PT-BR** completa
- Arquitetura PSR-4, Composer, migrations versionadas

Licença permanece **GPL-3.0-or-later** (ver [LICENSE](LICENSE)).

---

## Stack

| Camada | Tecnologia |
|---|---|
| Linguagem | PHP 8.3 |
| Banco | SQLite 3 (WAL mode) |
| Web server | nginx + PHP-FPM |
| Roteador | bramus/router |
| QR público | endroid/qr-code (v5.1) |
| QR do 2FA | bacon/bacon-qr-code |
| 2FA TOTP | pragmarx/google2fa |
| Markdown | erusev/parsedown-extra |
| Deploy típico | LXC no Proxmox + Cloudflare Tunnel |

---

## Documentação

Documentação completa em [`docs/`](docs/):

- [`00-provisionamento-infra.md`](docs/00-provisionamento-infra.md) — Criação do LXC, instalação de dependências, configuração de nginx, php-fpm, Cloudflare Tunnel e Access
- [`01-deploy.md`](docs/01-deploy.md) — Deploy da aplicação no servidor já provisionado
- [`02-arquitetura.md`](docs/02-arquitetura.md) — Visão geral de componentes, camadas, modelos e fluxos
- [`03-troubleshooting.md`](docs/03-troubleshooting.md) — Problemas comuns e como diagnosticá-los

---

## Quick start (desenvolvimento local)

```bash
git clone https://github.com/guilherme1985/qr-track.git arkham-files
cd arkham-files
composer install
cp .env.example .env
# Para dev local, ajuste DB_PATH, STORAGE_PATH, UPLOAD_PATH para caminhos locais
php bin/migrate.php
php bin/create-user.php  # cria primeiro admin (interativo)
composer serve           # http://127.0.0.1:8080
```

Para deploy em produção, ver [`docs/01-deploy.md`](docs/01-deploy.md).

### Migrations

```bash
php bin/migrate.php          # aplica pendentes
php bin/migrate.php status   # mostra aplicadas/pendentes
```

Migrations vivem em `migrations/NNNN_descricao.sql`. Sequenciais, rodadas em transação. **Sempre use `bin/migrate.php`** (jamais `sqlite3 < migrations/...`) — o script registra a versão na tabela `_migrations`, que o healthz consulta.

### Scripts CLI úteis

```bash
php bin/create-user.php          # cria usuário (admin ou curator) interativamente
php bin/disable-2fa.php <user>   # desativa 2FA de um usuário (recovery)
php bin/seed-qrs.php             # popula QRs de exemplo (--clear remove)
php bin/build-qr-icons.php       # gera PNGs dos ícones pra usar no centro dos QRs
php bin/migrate.php              # aplica migrations pendentes
```

### Estrutura

```
.
├── public/         # Webroot (única coisa exposta no nginx)
│   ├── index.php
│   └── assets/     # CSS, fontes, ícones, qr-icons
├── src/            # Código PHP (PSR-4 namespace ArkhamFiles\)
├── migrations/     # Schema versionado
├── templates/      # Views PHP
├── bin/            # Scripts CLI
├── docs/           # Documentação
├── data/           # SQLite DB + maintenance.flag (gitignored)
├── uploads/        # Imagens enviadas (gitignored, fora do webroot)
└── storage/        # Sessions, cache (gitignored)
```

---

## Roadmap

| Versão | Tema | Status |
|---|---|---|
| v1.0.0 | Base · estrutura, migrations, schema, identidade visual inicial | ✅ |
| v1.1.0 | Identidade visual completa (Cinzel, Cormorant, Tabler icons, sidebar) | ✅ |
| v1.2.0 | Auth refatorado (Argon2id, sessions, CSRF, rate limit, audit) | ✅ |
| v1.3.0 | 2FA TOTP obrigatório para admins + deleção de usuários | ✅ |
| v1.4.0 | Categorias hierárquicas + ícones/cores | ✅ |
| v1.4.1/1.4.2 | Hotfixes seletores visuais e sidebar | ✅ |
| v1.5.0 | Expiração de QR + endpoint público + tracking de scans | ✅ |
| v1.6.0 | Tipo Nota (Markdown) | ✅ |
| v1.7.0 | Tipo Strain (perfil de cultivo) | ✅ |
| v1.8.0 | Tipo Imagem (upload com thumbnail) | ✅ |
| v1.8.5/1.8.6 | Geração própria de QR Code (SVG/PNG) + endpoints públicos | ✅ |
| v1.9.0 | Páginas de erro temáticas + landing pública + dashboard real + modo manutenção | ✅ |
| **v1.10.0** | **Documentação consolidada + polish final** | **🎯 atual** |
| futuro | Outros tipos de QR (vcard, wifi, location), API pública, exportação | 📋 |

---

## Licença

GNU General Public License v3.0 ou posterior. Trabalho derivado de [tuxxin/qr-track](https://github.com/tuxxin/qr-track) (GPL-3.0). Ver [LICENSE](LICENSE).
