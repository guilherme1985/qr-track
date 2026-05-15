# 03 · Troubleshooting

Problemas comuns que aconteceram durante os PRs deste projeto, com diagnóstico passo-a-passo. Se você bate em algum desses, este documento poupa horas.

---

## 1. `/healthz` mostra "Migrations: N aplicadas, M pendentes" em vermelho

**Sintoma**: A landing pública (`/healthz`) acusa migrations pendentes. Mas o app funciona normalmente — listagens, criação, viewer público, tudo OK.

**Causa**: Você rodou alguma migration via `sqlite3 < migrations/000X.sql` em vez de `php bin/migrate.php`. O SQL foi aplicado no schema, mas a tabela `_migrations` (que o `/healthz` consulta) não foi atualizada.

**Diagnóstico**:

```bash
# Conferir o que está registrado vs o que existe
sudo -u qrtrack sqlite3 /var/www/qrtrack/data/qrtrack.sqlite \
  "SELECT version, applied_at FROM _migrations ORDER BY version;"

ls migrations/
```

Se o `ls` mostra migrations que não estão no `SELECT`, é esse problema.

**Fix** (sem perder dados):

```bash
# Para cada migration que está no disco mas não no _migrations,
# registra manualmente (NÃO roda o SQL — o schema já está aplicado)
sudo -u qrtrack sqlite3 /var/www/qrtrack/data/qrtrack.sqlite <<EOF
INSERT OR IGNORE INTO _migrations (version, applied_at) VALUES
  ('0003', datetime('now')),
  ('0004', datetime('now'));
EOF
```

> **⚠️ NÃO rode `php bin/migrate.php`** — vai tentar aplicar 0003 e 0004 de novo, falhar com "table already exists", e potencialmente corromper o estado.

**Prevenção**: Use SEMPRE `php bin/migrate.php` para aplicar migrations. Esse comando registra a versão automaticamente.

---

## 2. HTTP 500 em `/p/{id}.svg` ou `.png` com mensagem `Internal error` (14 bytes)

**Sintoma**: A página `/admin/notes/{id}/qr` carrega mas o QR aparece quebrado. Os 4 botões de download falham.

**Diagnóstico**:

```bash
sudo tail -30 /var/log/nginx/qrtrack.error.log | grep "QR render"
```

A linha tem o motivo real. Possibilidades:

### 2.1. `Unknown named parameter $writer` ou `Class ErrorCorrectionLevelHigh not found`

**Causa**: Versão do `endroid/qr-code` no `vendor/` é diferente da que o código espera. O projeto roda contra a **v5.1.0** especificamente.

**Diagnóstico**:

```bash
sudo -u qrtrack -H bash -c 'cd /var/www/qrtrack && composer show endroid/qr-code | head -5'
```

Se `versions: * X.X.X` não for `5.1.0`:

```bash
sudo -u qrtrack -H bash -c '
    cd /var/www/qrtrack && \
    composer require endroid/qr-code:5.1.0 --update-with-dependencies --no-dev
'
```

### 2.2. `Cannot load Endroid\QrCode\Builder\Builder`

**Causa**: Autoload desatualizado.

**Fix**:

```bash
sudo -u qrtrack -H bash -c 'cd /var/www/qrtrack && composer dump-autoload --optimize'
```

---

## 3. Upload de imagem rejeitado mesmo sendo JPEG/PNG/WebP válido

**Sintoma**: O form mostra "Formato não permitido. Aceitos: JPEG, PNG, WebP." para arquivos que claramente são imagens.

**Diagnóstico**: `ImageUpload::process` usa `finfo` para detectar o **MIME real** (lê magic bytes), não o `$_FILES[type]` enviado pelo cliente. Imagens corrompidas, com bytes alterados, ou trafegadas via proxy que reescreve podem falhar a detecção.

```bash
# Confere o MIME que o servidor enxerga
file /caminho/do/seu/arquivo.jpg
# Deve mostrar: JPEG image data, JFIF standard...
```

Se `file` reportar `text/x-php`, ASCII, ou outro tipo: o arquivo não é uma imagem válida (independente da extensão).

---

## 4. Imagem aparece quebrada no admin grid ou viewer público

**Sintoma**: Listagem `/admin/images` mostra ícones quebrados. URL da imagem `/uploads/originals/xxx.jpg` retorna 404.

**Diagnóstico**:

```bash
# Os arquivos existem fisicamente?
sudo -u qrtrack ls -la /var/www/qrtrack/uploads/originals/
sudo -u qrtrack ls -la /var/www/qrtrack/uploads/thumbs/

# nginx serve a rota?
curl -sI http://192.168.15.50/uploads/originals/<nome>.jpg
```

**Causas possíveis**:

### 4.1. `location /uploads/` falta no nginx

Confira `/etc/nginx/sites-available/qrtrack`. O bloco abaixo é obrigatório:

```nginx
location /uploads/ {
    alias /var/www/qrtrack/uploads/;
    expires 1y;
    autoindex off;
    location ~ \.php$ { return 403; }
}
```

### 4.2. Permissões impedem nginx (www-data) de ler

```bash
# nginx (www-data) precisa estar no grupo qrtrack
groups www-data
# Esperado conter 'qrtrack'

# Se não, adiciona:
sudo usermod -aG qrtrack www-data
sudo systemctl reload php8.3-fpm nginx

# Permissões dos uploads
sudo -u qrtrack ls -la /var/www/qrtrack/uploads/originals/ | head -3
# Esperado: -rw-r--r-- qrtrack qrtrack (ou similar legível)
```

---

## 5. Modo manutenção não desliga via UI

**Sintoma**: Clica em "Desativar manutenção", aparece flash "Modo manutenção DESATIVADO" mas o arquivo continua lá.

**Diagnóstico**:

```bash
sudo -u qrtrack ls -la /var/www/qrtrack/data/maintenance.flag
```

**Causa provável**: PHP-FPM não tem permissão de escrita em `data/`.

**Fix**:

```bash
sudo chown qrtrack:qrtrack /var/www/qrtrack/data
sudo chmod 770 /var/www/qrtrack/data
sudo chown qrtrack:qrtrack /var/www/qrtrack/data/maintenance.flag 2>/dev/null
```

**Workaround imediato** (enquanto não corrige permissões):

```bash
sudo -u qrtrack rm /var/www/qrtrack/data/maintenance.flag
```

---

## 6. CSRF inválido em POST mesmo com sessão ativa

**Sintoma**: Submit em qualquer form retorna 400 "Token CSRF inválido". Recarregar a página e tentar de novo não resolve.

**Causa**: `Session::start()` precisa ser chamado **antes** de `validateCsrf` em qualquer POST handler. Se a ordem inverter, o token comparado é de uma sessão recém-iniciada (vazia).

**Diagnóstico**: Esse é um bug de código, não de operação. Se você acabou de adicionar um POST handler novo e está vendo esse erro, confira a ordem dos calls. Padrão correto:

```php
$router->post('/admin/xxx', function () use ($verifyCsrf) {
    if (!$verifyCsrf()) return;       // <-- já chama Session::start() internamente
    $user = Auth::requireAuth();
    // ... resto do handler
});
```

---

## 7. Cookie `Secure` impede login em HTTP local

**Sintoma**: Em dev local (`http://127.0.0.1:8080`), o login "funciona" mas o usuário é deslogado no próximo request.

**Causa**: A sessão é configurada com cookie `Secure=1` em produção (HTTPS), mas em HTTP isso impede o browser de enviar o cookie.

**Fix**: O código já tem a lógica condicional — só envia `Secure` se a conexão é HTTPS. Se mesmo assim falhar localmente:

```bash
# Adiciona no .env local
APP_DEBUG=true
# Reinicia se for php -S
```

E confere `src/Auth/Session.php` — a lógica `isHttps()` precisa estar funcionando.

---

## 8. Tela 503 (manutenção) aparece pra admin quando deveria ter bypass

**Sintoma**: Admin loga, ativa manutenção, navega — vê a tela 503 em vez do dashboard com banner de alerta.

**Diagnóstico**:

```bash
# Confere se a sessão do admin está sendo lida no middleware
sudo tail /var/log/php-fpm/qrtrack.error.log
```

**Causa provável**: O middleware tem `try/catch` defensivo que silenciosamente cai pro fallback (mostrar 503) se a sessão falhar. Causas comuns:

- Cookie de sessão expirou
- `SESSION_LIFETIME` no .env está baixo demais
- Permissão de escrita em `storage/` faltando (sessions PHP padrão usam tmp do sistema, mas se foi customizado...)

**Fix**:

```bash
# Logout + login de novo
# Se persistir, conferir storage/
sudo -u qrtrack ls -la /var/www/qrtrack/storage/
```

**Workaround imediato**:

```bash
sudo -u qrtrack rm /var/www/qrtrack/data/maintenance.flag
```

---

## 9. QR code scaneado dá 404 mesmo com o documento existindo

**Sintoma**: Imprime o QR, escaneia com celular, browser abre uma URL que dá 404.

**Diagnóstico**:

```bash
# Qual URL o QR codifica?
# Decodifica o QR usando um leitor online (envie só o PNG, sem dados sensíveis)
# Ou no terminal:
zbarimg /caminho/do/qr.png
```

**Causas**:

### 9.1. `APP_URL` no `.env` está errado

O QR é gerado usando `$_SERVER['HTTP_HOST']`. Se o servidor estiver vendo o host errado (proxy mal configurado), o QR codifica um host inválido.

**Fix**: Confere que `proxy_set_header Host $host` está no nginx do gateway.

### 9.2. O QR está usando o IP interno em vez do hostname

Se você baixou o QR de dentro da rede acessando `http://192.168.15.50/admin/...`, ele codifica `http://192.168.15.50/p/...` — que não funciona fora da LAN.

**Fix**: Sempre gera/baixa QRs acessando via `https://qrtrack.arkhamcloud.net/admin/...`.

### 9.3. QR foi gerado pra um documento que depois foi arquivado/expirado

**Diagnóstico**:

```bash
sudo -u qrtrack sqlite3 /var/www/qrtrack/data/qrtrack.sqlite \
  "SELECT public_id, type, title, is_deleted, is_disabled, expires_at
     FROM qrcodes WHERE public_id='xxxx-xx';"
```

Se `is_deleted=1` ou `expires_at` no passado → comportamento esperado.

---

## 10. Cloudflare Access bloqueia o admin pra todos

**Sintoma**: Você (admin) tenta acessar `/admin/*` e recebe tela do Cloudflare "Sign in with email" — mas mesmo com o email correto, fica em loop.

**Diagnóstico**: Cloudflare Application sem policy atrelada **bloqueia tudo por default**. Já aconteceu nesse projeto.

**Fix**:

1. Cloudflare Dashboard → Zero Trust → Access → Applications
2. Edita "Arkham Admin" (ou nome equivalente)
3. Aba "Policies" → deve ter pelo menos 1 policy com Action=Allow
4. Se não tem, cria: Name "Admin Arkham", Action Allow, Include → Emails → seu email
5. Save

---

## 11. Cores/fontes não carregam — admin aparece "cru"

**Sintoma**: O admin parece sem estilo nenhum, sidebar despida.

**Diagnóstico**:

```bash
curl -sI https://qrtrack.arkhamcloud.net/assets/css/arkham.css
# Esperado: HTTP/2 200, type: text/css
```

Se der 404 ou 403:

```bash
# Os arquivos existem?
sudo -u qrtrack ls -la /var/www/qrtrack/public/assets/css/

# Permissões corretas?
sudo -u qrtrack ls -la /var/www/qrtrack/public/assets/
```

**Causa frequente**: nginx `www-data` não consegue ler `public/`. Conferir grupo:

```bash
groups www-data
# Esperado conter 'qrtrack'
```

---

## 12. `composer install` quebra com erro de versão

**Sintoma**:

```
Your requirements could not be resolved to an installable set of packages.
Problem 1: endroid/qr-code 5.1.0 requires bacon/bacon-qr-code ^3.0
```

**Causa**: `composer.lock` está obsoleto vs `composer.json`.

**Fix**:

```bash
sudo -u qrtrack -H bash -c '
    cd /var/www/qrtrack && \
    rm composer.lock && \
    composer install --no-dev --optimize-autoloader
'
```

Depois commita o novo `composer.lock` no git.

---

## 13. Audit log lotando o banco

**Sintoma**: SQLite ficando GB-grande, queries lentas.

**Diagnóstico**:

```bash
sudo -u qrtrack sqlite3 /var/www/qrtrack/data/qrtrack.sqlite \
  "SELECT COUNT(*), MIN(created_at), MAX(created_at) FROM audit_log;"
```

**Causa**: `AUDIT_LOG_RETENTION_DAYS` no `.env` está muito alto, ou a rotina de purge não está rodando.

**Fix**:

```bash
# Ajusta retenção no .env
AUDIT_LOG_RETENTION_DAYS=30

# Purge manual
sudo -u qrtrack sqlite3 /var/www/qrtrack/data/qrtrack.sqlite \
  "DELETE FROM audit_log WHERE created_at < datetime('now', '-30 days');"

# VACUUM pra recuperar espaço no arquivo
sudo -u qrtrack sqlite3 /var/www/qrtrack/data/qrtrack.sqlite "VACUUM;"
```

---

## 14. Site fica fora do ar após atualização

**Sintoma**: `git pull` rolou, mas o site não responde mais.

**Diagnóstico em ordem**:

```bash
# 1. PHP-FPM rodando?
systemctl status php8.3-fpm

# 2. nginx OK?
sudo nginx -t

# 3. Algum erro fatal recente?
sudo tail -20 /var/log/nginx/qrtrack.error.log
sudo tail -20 /var/log/php-fpm/qrtrack.error.log

# 4. Schema compatível?
curl -s http://192.168.15.50/healthz | grep -E "✗"
```

**Rollback de emergência** (se sua atualização incluiu migration):

```bash
sudo -u qrtrack -H bash -c '
    cd /var/www/qrtrack && \
    # Restaura backup feito ANTES do pull
    cp data/qrtrack.sqlite.bak.YYYYMMDD-HHMMSS data/qrtrack.sqlite && \
    # Volta o código pra tag anterior
    git checkout v1.x.x
'
```

(Por isso o backup antes de cada `git pull` é mandatório — ver [`01-deploy.md`](01-deploy.md#9-atualizações-posteriores).)

---

## 15. Onde pedir ajuda

- Issues no GitHub: <https://github.com/guilherme1985/qr-track/issues>
- Para debugging avançado, anexe na issue:
  - `php -v` e `php -m`
  - `composer show | head -20`
  - Trecho relevante dos logs (`tail -50 /var/log/nginx/qrtrack.error.log`)
  - Saída de `/healthz`
