# 🦇 Arkham Files

> **QR Code generator and tracker** com identidade visual Arkham Asylum.
> Self-hosted. PHP 8.3 + SQLite. Sem cloud, sem ads, sem analytics de terceiros.

---

## Heritage

Este projeto é um **fork de [tuxxin/qr-track](https://github.com/tuxxin/qr-track)**, licenciado sob GPL-3.0. A partir do tag `v1.0.0` o código foi substancialmente reescrito:

- Identidade visual nova (tema **Arkham Files** — verde-fósforo + ouro vitoriano sobre preto)
- Hash de senha **Argon2id** + autenticação **2FA TOTP**
- **Categorias hierárquicas** (árvore com profundidade até 3)
- Novos tipos de QR: **Notas** (Markdown), **Strain** (perfil de cultivo), **Imagem** (upload)
- **Expiração** configurável (30/60/90 dias ou nunca)
- Localização **PT-BR**
- Arquitetura moderna: PSR-4, Composer, sistema de migrations versionadas

A geração e tracking de QR são herança conceitual do projeto original. Licença permanece **GPL-3.0-or-later**.

---

## Stack

| Camada | Tecnologia |
|---|---|
| Linguagem | PHP 8.3 |
| Banco | SQLite 3 (WAL mode) |
| Web server | nginx + PHP-FPM |
| Roteador | bramus/router |
| QR | endroid/qr-code |
| 2FA | pragmarx/google2fa |
| Markdown | erusev/parsedown |
| Deploy típico | LXC no Proxmox + Cloudflare Tunnel |

---

## Instalação (produção)

Provisionamento completo da infra está em [`docs/00-provisionamento-infra.md`](docs/00-provisionamento-infra.md). Resumo:

```bash
# Dentro do LXC qrtrack
cd /var/www/qrtrack
git clone https://github.com/guilherme1985/qr-track.git .
composer install --no-dev --optimize-autoloader
cp .env.example .env
# edite .env conforme seu ambiente
php bin/migrate.php
chown -R qrtrack:qrtrack .
```

Após isso, o nginx já configurado no LXC serve a aplicação a partir de `/var/www/qrtrack/public`.

---

## Desenvolvimento local

```bash
git clone https://github.com/guilherme1985/qr-track.git arkham-files
cd arkham-files
composer install
cp .env.example .env
# Para dev local, ajuste DB_PATH, STORAGE_PATH, UPLOAD_PATH para caminhos locais
php bin/migrate.php
composer serve  # http://127.0.0.1:8080
```

### Migrations

```bash
php bin/migrate.php          # aplica pendentes
php bin/migrate.php status   # mostra aplicadas/pendentes
```

Migrations vivem em `migrations/NNNN_descricao.sql`. Sequenciais, rodadas em transação.

### Estrutura

```
.
├── public/         # Webroot (única coisa exposta no nginx)
├── src/            # Código PHP (PSR-4 namespace ArkhamFiles\)
├── migrations/     # Schema versionado
├── templates/      # Views PHP
├── bin/            # Scripts CLI
├── data/           # SQLite DB (gitignored)
├── uploads/        # Arquivos enviados (gitignored, fora do webroot)
└── storage/        # Sessions, cache (gitignored)
```

---

## Roadmap (planejado)

| Versão | Conteúdo |
|---|---|
| v1.0.0 | **Base** · estrutura, migrations, schema, identidade visual |
| v1.1.0 | Identidade visual completa, layout admin |
| v1.2.0 | Auth refatorado (Argon2id, sessions, rate limit) |
| v1.3.0 | 2FA TOTP |
| v1.4.0 | Categorias hierárquicas |
| v1.5.0 | Expiração de QR |
| v1.6.0 | Tipo Nota (Markdown) |
| v1.7.0 | Tipo Strain |
| v1.8.0 | Tipo Imagem |
| v1.9.0 | Páginas de erro temáticas + cleanup |

---

## Licença

GNU General Public License v3.0 ou posterior. Veja [LICENSE](LICENSE).

Trabalho derivado de [tuxxin/qr-track](https://github.com/tuxxin/qr-track) (GPL-3.0).
