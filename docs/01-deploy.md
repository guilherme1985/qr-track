# 01 · Deploy

Como instalar e configurar o Arkham Files em um servidor **já provisionado** (ver [`00-provisionamento-infra.md`](00-provisionamento-infra.md)).

> Assumindo a topologia padrão (qrtrack LXC em `192.168.15.50`, gateway em `192.168.15.10`, hostname `qrtrack.arkhamcloud.net`).

---

## 1. Clone do repositório

Logado como `qrtrack` no LXC do app:

```bash
sudo -u qrtrack -H bash
cd /var/www/qrtrack

# Repo público, então clone simples basta
git clone https://github.com/guilherme1985/qr-track.git .
```

> Se for via SSH (deploy key configurada), use `git@github.com:guilherme1985/qr-track.git`.

---

## 2. Dependências do Composer

```bash
composer install --no-dev --optimize-autoloader
```

A flag `--no-dev` evita instalar pacotes de desenvolvimento (PHPUnit, etc.). A `--optimize-autoloader` gera autoload por classmap (mais rápido em produção).

---

## 3. Configuração do `.env`

```bash
cp .env.example .env
```

Edite `.env` e preencha:

```bash
# Gere uma chave de 64 chars hex para criptografia dos TOTP secrets
TOTP_ENCRYPTION_KEY=$(php -r 'echo bin2hex(random_bytes(32));')
echo "TOTP_ENCRYPTION_KEY=$TOTP_ENCRYPTION_KEY"
# Cole o valor no .env
```

Verifique também:
- `APP_URL` = `https://qrtrack.arkhamcloud.net`
- `DB_PATH` = `/var/www/qrtrack/data/qrtrack.sqlite`
- `DATA_DIR` = `/var/www/qrtrack/data`
- `UPLOAD_PATH` = `/var/www/qrtrack/uploads`
- `STORAGE_PATH` = `/var/www/qrtrack/storage`
- `TRUSTED_PROXIES` = `192.168.15.10,127.0.0.1`

> ⚠️ **Backup do `TOTP_ENCRYPTION_KEY`**: se você perder ou trocar essa chave, **todos os 2FA setados ficam inválidos** e os usuários precisam refazer o setup. Anote em um cofre.

---

## 4. Migrations

```bash
php bin/migrate.php status   # lista pendentes
php bin/migrate.php          # aplica
```

> ⚠️ **NUNCA use `sqlite3 < migrations/XXXX.sql`** direto. O `bin/migrate.php` registra a versão na tabela `_migrations`. Sem isso, o `/healthz` vai acusar migrations pendentes mesmo o schema estando correto. Se isso acontecer, ver [`03-troubleshooting.md`](03-troubleshooting.md).

---

## 5. Permissões

Confira que `qrtrack:qrtrack` é dono de tudo:

```bash
exit  # sai do shell do qrtrack se ainda estiver dentro
chown -R qrtrack:qrtrack /var/www/qrtrack
find /var/www/qrtrack -type d -exec chmod 755 {} \;
find /var/www/qrtrack -type f -exec chmod 644 {} \;
chmod 770 /var/www/qrtrack/data /var/www/qrtrack/storage
chmod 600 /var/www/qrtrack/.env
chmod +x /var/www/qrtrack/bin/*.php
```

Pra que o nginx (rodando como `www-data`) consiga ler os assets:

```bash
usermod -aG qrtrack www-data
systemctl reload nginx php8.3-fpm
```

---

## 6. Gerar PNGs dos ícones de QR

Necessário pra o logo da categoria aparecer no centro dos QRs:

```bash
sudo -u qrtrack -H bash -c 'cd /var/www/qrtrack && php bin/build-qr-icons.php'
```

Esperado: `✓ Gerados 36 ícones em /var/www/qrtrack/public/assets/qr-icons/`

---

## 7. Primeiro admin

```bash
sudo -u qrtrack -H bash -c 'cd /var/www/qrtrack && php bin/create-user.php'
```

Siga o prompt interativo:
- Username
- Email
- Role: `admin`
- Senha: deixe vazio pra gerar uma temporária

O script imprime a senha gerada. Faça login com ela e o sistema vai forçar a troca + setup do 2FA na primeira sessão.

---

## 8. Verificação

```bash
# Aplicação responde via nginx local
curl -sI http://192.168.15.50 | head -3
# Esperado: HTTP/1.1 200 OK

# Healthz check
curl -s http://192.168.15.50/healthz | grep -E "✓|✗" | head -10
# Esperado: todas as linhas com ✓

# Via Cloudflare Tunnel
curl -sI https://qrtrack.arkhamcloud.net
# Esperado: HTTP/2 200

# Landing pública
curl -s https://qrtrack.arkhamcloud.net | grep "INSTITUIÇÃO ARKHAM"
# Esperado: linha contendo "INSTITUIÇÃO ARKHAM · DIVISÃO DE QR"

# /admin/* requer Cloudflare Access
curl -sI https://qrtrack.arkhamcloud.net/admin/login
# Esperado: redirect (302/303) pra cloudflareaccess.com
```

---

## 9. Atualizações posteriores

Pra atualizar pra uma versão nova depois:

```bash
sudo -u qrtrack -H bash -c '
    cd /var/www/qrtrack && \
    cp data/qrtrack.sqlite data/qrtrack.sqlite.bak.$(date +%Y%m%d-%H%M%S) && \
    git pull origin main && \
    composer install --no-dev --optimize-autoloader && \
    php bin/migrate.php
'
```

O backup do SQLite antes do pull é **obrigatório** — se uma migration falhar no meio, você restaura o `.bak` e investiga.

---

## 10. Logs úteis

| Origem | Caminho |
|---|---|
| nginx do qrtrack | `/var/log/nginx/qrtrack.{access,error}.log` |
| PHP-FPM do qrtrack | `/var/log/php-fpm/qrtrack.error.log` |
| Audit log do app | DB `audit_log` table (acessível em `/admin/dashboard`) |
| cloudflared (gateway) | `journalctl -u cloudflared -f` |

Pra um tail consolidado em tempo real:

```bash
tail -F /var/log/nginx/qrtrack.error.log \
        /var/log/php-fpm/qrtrack.error.log
```

---

## 11. Troubleshooting

Problemas comuns durante deploy → [`03-troubleshooting.md`](03-troubleshooting.md).
