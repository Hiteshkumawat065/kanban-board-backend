# Deployment Guide — Free Tier

Three battle-tested ways to deploy this Kanban app for **₹0/month**.

| Option | Best for | Caveat |
|---|---|---|
| **A. Render.com** | Easiest, zero DevOps | Free service sleeps after 15 min idle |
| **B. Oracle Cloud Free Tier** | Best performance, learning | Manual server setup required |
| **C. Railway.app** | Mid-ground | $5 free credit/mo (enough for portfolio) |

---

## Option A — Render.com (recommended for first deploy)

### Prerequisites
- GitHub account
- Render account (free, no card)
- Neon.tech account (free Postgres) — or use Render's free Postgres (90 days)
- Upstash account (free Redis)

### Step 1 — Push code to GitHub
```bash
git init
git add .
git commit -m "feat: initial commit"
gh repo create kanban --public --source=. --push
```

### Step 2 — Create database
1. Go to [neon.tech](https://neon.tech) → New Project → name `kanban`.
2. Copy the connection string.

### Step 3 — Create Redis
1. Go to [upstash.com](https://upstash.com) → Create Database (free tier).
2. Copy the `redis://` URL.

### Step 4 — Create Web Service on Render
1. Render → **New +** → **Web Service** → connect your GitHub repo.
2. Settings:
   - **Environment**: Docker
   - **Dockerfile path**: `docker/Dockerfile`
   - **Plan**: Free
3. Add environment variables (paste your DB + Redis URLs).
4. Add a **Build Hook** so Vite assets get compiled:
   ```
   composer install --no-dev --optimize-autoloader \
     && npm ci && npm run build \
     && php artisan migrate --force \
     && php artisan config:cache \
     && php artisan route:cache
   ```
5. **Start Command**:
   ```
   php artisan serve --host=0.0.0.0 --port=$PORT
   ```
6. Click **Create Web Service**.

### Step 5 — Add Reverb as a separate service
Reverb needs its own process. Repeat step 4 with:
- **Start Command**: `php artisan reverb:start --host=0.0.0.0 --port=$PORT`
- **Plan**: Free

Add the Reverb service URL to your main app's env:
```
REVERB_HOST=<reverb-service>.onrender.com
REVERB_PORT=443
REVERB_SCHEME=https
```

### Step 6 — Add a Background Worker for queues
- **New +** → **Background Worker** → same repo
- **Start Command**: `php artisan queue:work --tries=3 --sleep=2`

### Step 7 — Visit your live app
`https://kanban.onrender.com` — first request takes ~30 seconds (cold start).

---

## Option B — Oracle Cloud Free Tier (best performance, forever free)

You get a **4 vCPU + 24 GB RAM ARM VM** absolutely free. Perfect for a portfolio app that doesn't sleep.

### Step 1 — Create Oracle Cloud account
[oracle.com/cloud/free](https://www.oracle.com/cloud/free) — needs a card (no charge).

### Step 2 — Launch a free VM
1. Compute → Instances → Create.
2. Image: **Ubuntu 22.04 (Always Free)**.
3. Shape: **VM.Standard.A1.Flex** — 4 OCPU, 24 GB RAM (free).
4. SSH keys: paste your public key.
5. Networking: assign public IPv4.
6. Create.

### Step 3 — Open ports in Security List
In your VCN's Security List, add ingress rules for:
- TCP 22 (SSH) — already open
- TCP 80 (HTTP)
- TCP 443 (HTTPS)
- TCP 8080 (Reverb, or proxy via Nginx)

### Step 4 — Bootstrap the server
SSH into the VM and run:

```bash
ssh ubuntu@<your-public-ip>

# Install Docker
sudo apt update
sudo apt install -y docker.io docker-compose-v2 git
sudo usermod -aG docker $USER
newgrp docker

# Allow ports through Ubuntu's iptables
sudo iptables -I INPUT -p tcp --dport 80 -j ACCEPT
sudo iptables -I INPUT -p tcp --dport 443 -j ACCEPT
sudo iptables -I INPUT -p tcp --dport 8080 -j ACCEPT
sudo netfilter-persistent save

# Clone and run
git clone https://github.com/<you>/kanban.git
cd kanban
cp .env.example .env

# Edit .env with production values
nano .env
# Set APP_ENV=production, APP_DEBUG=false, your domain in APP_URL,
# DB_HOST=mysql, REDIS_HOST=redis, REVERB_HOST=yourdomain.com

docker compose up -d --build
docker compose exec app php artisan key:generate
docker compose exec app php artisan migrate --force
docker compose exec app php artisan storage:link
docker compose exec app php artisan config:cache
docker compose exec app php artisan route:cache
```

### Step 5 — Free SSL via Cloudflare Tunnel
1. Buy a cheap domain (`.xyz` ₹100/yr) or use a free `.tk`.
2. Add domain to Cloudflare.
3. Install `cloudflared` on the VM:
   ```bash
   curl -L --output cloudflared.deb https://github.com/cloudflare/cloudflared/releases/latest/download/cloudflared-linux-arm64.deb
   sudo dpkg -i cloudflared.deb
   cloudflared tunnel login
   cloudflared tunnel create kanban
   cloudflared tunnel route dns kanban kanban.yourdomain.com
   ```
4. Create `~/.cloudflared/config.yml`:
   ```yaml
   tunnel: kanban
   credentials-file: /home/ubuntu/.cloudflared/<UUID>.json
   ingress:
     - hostname: kanban.yourdomain.com
       service: http://localhost:8000
     - hostname: ws.yourdomain.com
       service: http://localhost:8080
     - service: http_status:404
   ```
5. Run as a service:
   ```bash
   sudo cloudflared service install
   sudo systemctl start cloudflared
   ```

Done — `https://kanban.yourdomain.com` live with free SSL.

---

## Option C — Railway.app

### Step 1 — Install CLI
```bash
npm i -g @railway/cli
railway login
```

### Step 2 — Init & link
```bash
cd kanban
railway init
railway link
```

### Step 3 — Add plugins
```bash
railway add --plugin postgresql
railway add --plugin redis
```

### Step 4 — Set env vars
```bash
railway variables set APP_KEY=$(php artisan key:generate --show)
railway variables set APP_ENV=production
railway variables set APP_DEBUG=false
# Railway auto-injects DATABASE_URL and REDIS_URL
```

### Step 5 — Add a `nixpacks.toml`
Create `nixpacks.toml`:
```toml
[phases.setup]
nixPkgs = ["php83", "php83Packages.composer", "nodejs_20"]

[phases.install]
cmds = ["composer install --no-dev --optimize-autoloader", "npm ci"]

[phases.build]
cmds = ["npm run build", "php artisan config:cache"]

[start]
cmd = "php artisan migrate --force && php artisan serve --host=0.0.0.0 --port=$PORT"
```

### Step 6 — Deploy
```bash
railway up
```

Railway gives you a `*.up.railway.app` URL automatically.

---

## Post-Deployment Checklist

- [ ] `APP_DEBUG=false` in production
- [ ] `APP_ENV=production`
- [ ] `APP_URL` matches your real domain (HTTPS)
- [ ] `php artisan config:cache && route:cache && view:cache` ran
- [ ] Migrations applied (`php artisan migrate --force`)
- [ ] Storage symlinked
- [ ] Queue worker running (process or supervisor)
- [ ] Reverb running and reachable via WSS
- [ ] Backups configured (Neon/Render have auto-backups; for Oracle, schedule `mysqldump` to S3/R2)
- [ ] Error tracking (Sentry / Bugsnag free tier)
- [ ] Uptime monitor (UptimeRobot free)

---

## Useful Production Commands

```bash
# Maintenance mode during deploy
php artisan down --refresh=15

# After deploy
php artisan migrate --force
php artisan optimize
php artisan queue:restart    # tells workers to reload code
php artisan up

# View logs
docker compose logs -f app
docker compose logs -f reverb
```
