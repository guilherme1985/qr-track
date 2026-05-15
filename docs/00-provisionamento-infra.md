# 00 · Provisionamento da Infraestrutura

Como criar do zero a infraestrutura necessária para rodar o Arkham Files. Este documento cobre **2 LXCs no Proxmox** + **Cloudflare Tunnel** + **Cloudflare Access**.

> Topologia usada como referência:
> - **Proxmox host** em `192.168.15.42`
> - **Gateway LXC** em `192.168.15.10` (cloudflared + nginx reverse proxy)
> - **App LXC** em `192.168.15.50` (Debian 12, nginx + PHP-FPM + SQLite)
> - **Hostname público**: `qrtrack.arkhamcloud.net`

Se sua topologia é diferente, ajuste IPs e nomes em todos os comandos.

---

## 1. Criação do LXC do app no Proxmox

### 1.1. Template

Baixe o template Debian 12 standard pelo painel do Proxmox (Datacenter → Storage → CT Templates) ou via shell:

```bash
pveam update
pveam download local debian-12-standard_12.7-1_amd64.tar.zst
```

### 1.2. Criar o container

Pelo painel do Proxmox:

| Campo | Valor |
|---|---|
| CT ID | 150 (ou disponível) |
| Hostname | `qrtrack` |
| Template | Debian 12 standard |
| Root disk | 8 GB (cresce conforme necessidade) |
| CPU cores | 2 |
| Memory | 1024 MB |
| Swap | 512 MB |
| Network | bridge `vmbr0`, IPv4 estático `192.168.15.50/24`, gateway `192.168.15.1` |
| Features | nesting=1 (se for usar Cloudflare Tunnel client dentro) |
| Unprivileged | sim |

Após criar, inicie o container e entre com `pct enter 150`.

### 1.3. Atualização e pacotes base

```bash
apt update && apt upgrade -y

apt install -y \
    nginx \
    php8.3-fpm php8.3-cli php8.3-sqlite3 php8.3-mbstring php8.3-gd php8.3-xml php8.3-curl \
    sqlite3 \
    git \
    composer \
    librsvg2-bin \
    sudo \
    curl \
    ca-certificates
```

> **Note:** Se `php8.3-*` não estiver disponível, adicione o repositório do Sury:
> ```bash
> apt install -y apt-transport-https lsb-release gnupg2
> curl -sSL https://packages.sury.org/php/README.txt | bash -x
> ```

### 1.4. Usuário do app

```bash
adduser --system --group --home /var/www/qrtrack --shell /bin/bash qrtrack
usermod -aG www-data qrtrack
```

---

## 2. PHP-FPM — pool dedicado

Pra evitar que outros sites no LXC compartilhem o pool e pra rodar como o usuário `qrtrack`:

```bash
cp /etc/php/8.3/fpm/pool.d/www.conf /etc/php/8.3/fpm/pool.d/qrtrack.conf
```

Edite `/etc/php/8.3/fpm/pool.d/qrtrack.conf`:

```ini
[qrtrack]
user = qrtrack
group = qrtrack
listen = /run/php/php8.3-fpm-qrtrack.sock
listen.owner = www-data
listen.group = www-data

pm = dynamic
pm.max_children = 8
pm.start_servers = 2
pm.min_spare_servers = 1
pm.max_spare_servers = 3

php_admin_value[error_log] = /var/log/php-fpm/qrtrack.error.log
php_admin_flag[log_errors] = on

php_admin_value[upload_max_filesize] = 5M
php_admin_value[post_max_size] = 6M
php_admin_value[memory_limit] = 128M
```

Remove o pool default (opcional, mas recomendado):

```bash
rm /etc/php/8.3/fpm/pool.d/www.conf
mkdir -p /var/log/php-fpm
chown www-data:www-data /var/log/php-fpm
systemctl restart php8.3-fpm
systemctl status php8.3-fpm
```

---

## 3. nginx — vhost do qrtrack

Crie `/etc/nginx/sites-available/qrtrack`:

```nginx
server {
    listen 80 default_server;
    server_name qrtrack.local 192.168.15.50;
    root /var/www/qrtrack/public;
    index index.php;

    client_max_body_size 6m;

    # IP real do visitante: vem do gateway via X-Forwarded-For
    set_real_ip_from 192.168.15.10;
    real_ip_header X-Forwarded-For;
    real_ip_recursive on;

    # Headers de segurança
    add_header X-Frame-Options "SAMEORIGIN" always;
    add_header X-Content-Type-Options "nosniff" always;
    add_header Referrer-Policy "strict-origin-when-cross-origin" always;

    # Bloqueia paths ocultos
    location ~ /\.(?!well-known) { deny all; }

    # Serve uploads de imagem diretamente — sem passar pelo PHP
    location /uploads/ {
        alias /var/www/qrtrack/uploads/;
        expires 1y;
        add_header Cache-Control "public, max-age=31536000, immutable" always;
        autoindex off;
        # Defesa em profundidade: nunca executa PHP em /uploads/
        location ~ \.php$ { return 403; }
    }

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location ~ \.php$ {
        fastcgi_pass unix:/run/php/php8.3-fpm-qrtrack.sock;
        fastcgi_index index.php;
        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
        include fastcgi_params;
    }

    access_log /var/log/nginx/qrtrack.access.log;
    error_log /var/log/nginx/qrtrack.error.log;
}
```

Habilite e teste:

```bash
ln -s /etc/nginx/sites-available/qrtrack /etc/nginx/sites-enabled/qrtrack
rm /etc/nginx/sites-enabled/default
nginx -t
systemctl reload nginx
```

---

## 4. Estrutura de diretórios do app

```bash
sudo -u qrtrack -H bash -c '
    mkdir -p /var/www/qrtrack/{data,storage,uploads/originals,uploads/thumbs}
    chmod 750 /var/www/qrtrack
    chmod 770 /var/www/qrtrack/data /var/www/qrtrack/storage
    chmod 755 /var/www/qrtrack/uploads /var/www/qrtrack/uploads/originals /var/www/qrtrack/uploads/thumbs
'
```

---

## 5. Gateway LXC — Cloudflare Tunnel + reverse proxy

A função do gateway LXC é:
- Terminar o Cloudflare Tunnel (cloudflared)
- Encaminhar requests pra `192.168.15.50` (qrtrack LXC) via nginx interno
- Anexar `X-Forwarded-For` correto

### 5.1. Instalar cloudflared

No LXC `192.168.15.10`:

```bash
curl -L --output cloudflared.deb \
    https://github.com/cloudflare/cloudflared/releases/latest/download/cloudflared-linux-amd64.deb
dpkg -i cloudflared.deb

# Autentica e cria tunnel
cloudflared tunnel login
cloudflared tunnel create arkham-qrtrack
```

### 5.2. Configurar tunnel

Crie `/etc/cloudflared/config.yml`:

```yaml
tunnel: arkham-qrtrack
credentials-file: /root/.cloudflared/<TUNNEL-UUID>.json

ingress:
  - hostname: qrtrack.arkhamcloud.net
    service: http://localhost:8080
  - service: http_status:404
```

Roteie o hostname pro tunnel:

```bash
cloudflared tunnel route dns arkham-qrtrack qrtrack.arkhamcloud.net
```

Suba como serviço:

```bash
cloudflared service install
systemctl enable --now cloudflared
```

### 5.3. nginx no gateway (reverse proxy)

Crie `/etc/nginx/sites-available/qrtrack-proxy`:

```nginx
server {
    listen 8080;
    server_name qrtrack.arkhamcloud.net;

    client_max_body_size 6m;

    location / {
        proxy_pass http://192.168.15.50;
        proxy_set_header Host              $host;
        proxy_set_header X-Real-IP         $remote_addr;
        proxy_set_header X-Forwarded-For   $proxy_add_x_forwarded_for;
        proxy_set_header X-Forwarded-Proto $http_x_forwarded_proto;
        proxy_set_header X-Forwarded-Host  $host;
    }
}
```

Habilite e reload:

```bash
ln -s /etc/nginx/sites-available/qrtrack-proxy /etc/nginx/sites-enabled/qrtrack-proxy
nginx -t && systemctl reload nginx
```

---

## 6. Cloudflare Access — proteção de `/admin/*`

Esta camada bloqueia acesso ao admin antes de chegar no app (defense in depth).

No dashboard da Cloudflare:

1. **Zero Trust** → **Access** → **Applications** → **Add an application** → **Self-hosted**
2. Configure:
   - **Application name**: `Arkham Admin`
   - **Session duration**: `24 hours`
   - **Application domain**: `qrtrack.arkhamcloud.net`
   - **Path**: `/admin/*`
3. **Policies** → **Add a policy**:
   - **Policy name**: `Admin Arkham`
   - **Action**: `Allow`
   - **Configure rules** → **Include** → `Emails`: lista de emails autorizados
4. Save

> **Importante:** Uma Application sem policies bloqueia todos os requests — confira que a policy está criada e linkada.

---

## 7. Deploy da aplicação

Com toda a infra pronta, prossiga para [`01-deploy.md`](01-deploy.md).

---

## 8. Verificação final do provisionamento

```bash
# No LXC qrtrack
systemctl status nginx php8.3-fpm
ls -la /var/www/qrtrack/
php -m | grep -iE "pdo_sqlite|gd|mbstring|json|curl"
which rsvg-convert composer git

# No gateway
systemctl status cloudflared nginx

# Externamente (no seu computador)
curl -I https://qrtrack.arkhamcloud.net
# Esperado: HTTP/2 200 (após deploy do app)
```
