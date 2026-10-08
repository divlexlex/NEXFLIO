# Deployment Analysis — Perfect Nails Wellness & Aesthetics (NexFlio)

## Deployment Options

### Option 1: Traditional VPS/Dedicated Server

| Component | Technology |
|-----------|-----------|
| Web Server | Nginx or Apache |
| PHP Runtime | PHP-FPM 8.2+ |
| Database | MySQL 8.x |
| Cache/Queue | Redis |
| Process Manager | Supervisor (queue workers) |
| SSL | Let's Encrypt |
| OS | Ubuntu 22.04 LTS |

**Pros:** Full control, familiar stack, easy Laravel deployment
**Cons:** Requires server management, manual scaling

### Option 2: Platform-as-a-Service (PaaS)

| Platform | Support |
|----------|---------|
| Laravel Forge | Native Laravel support, Nginx + PHP-FPM |
| Laravel Vapor | Serverless Laravel on AWS Lambda |
| Railway | Container-based, easy setup |
| Render | Static + Web Services |
| DigitalOcean App Platform | Container-based |

**Pros:** Managed infrastructure, easy scaling, less ops work
**Cons:** Higher cost, less control, vendor lock-in

### Option 3: Containerized (Docker)

| Component | Technology |
|-----------|-----------|
| Containers | Docker |
| Orchestration | Docker Compose (dev) / Kubernetes (prod) |
| Registry | Docker Hub / AWS ECR |
| Load Balancer | Nginx / AWS ALB |

**Pros:** Reproducible environments, easy scaling, portable
**Cons:** Complexity, learning curve, infrastructure overhead

## Recommended Architecture (Production)

### Single-Server Deployment (Cost-Effective)

```
┌─────────────────────────────────────────────────┐
│              Production Server                    │
│  Ubuntu 22.04 LTS / 4GB RAM / 2 vCPU           │
│                                                  │
│  ┌──────────┐  ┌──────────┐  ┌──────────┐      │
│  │  Nginx   │  │ PHP-FPM  │  │  Redis   │      │
│  │  (SSL)   │  │  (8.2+)  │  │ (Cache)  │      │
│  └────┬─────┘  └────┬─────┘  └──────────┘      │
│       │              │                           │
│  ┌────▼──────────────▼──────────────────────┐   │
│  │         Laravel Application               │   │
│  │  - Web (Blade)                           │   │
│  │  - API (Sanctum)                         │   │
│  │  - Queue Worker (Supervisor)              │   │
│  │  - Scheduler (Cron)                       │   │
│  └──────────────────┬───────────────────────┘   │
│                     │                            │
│  ┌──────────────────▼───────────────────────┐   │
│  │              MySQL 8.x                    │   │
│  │         (Local socket connection)         │   │
│  └──────────────────────────────────────────┘   │
└─────────────────────────────────────────────────┘
```

### Multi-Server Deployment (Scalable)

```
┌──────────────┐     ┌──────────────┐     ┌──────────────┐
│   Web Server  │     │   API Server  │     │   Queue       │
│   (Nginx)     │     │   (Nginx)     │     │   Worker      │
│   PHP-FPM     │     │   PHP-FPM     │     │   (Supervisor)│
└──────┬───────┘     └──────┬───────┘     └──────┬───────┘
       │                     │                     │
       └─────────────────────┼─────────────────────┘
                             │
                    ┌────────▼────────┐
                    │   Load Balancer  │
                    │   (Nginx/AWS ALB)│
                    └────────┬────────┘
                             │
              ┌──────────────┼──────────────┐
              │              │              │
     ┌────────▼───────┐ ┌───▼────────┐ ┌──▼──────────┐
     │   MySQL (RDS)   │ │   Redis     │ │   Storage   │
     │   Primary +      │ │   (ElastiCache)│ │   (S3)     │
     │   Replica        │ │              │ │              │
     └─────────────────┘ └────────────┘ └─────────────┘
```

## Deployment Steps (Single Server)

### 1. Server Setup

```bash
# Update system
sudo apt update && sudo apt upgrade -y

# Install Nginx
sudo apt install nginx -y

# Install PHP 8.2 + extensions
sudo apt install php8.2-fpm php8.2-mysql php8.2-mbstring \
  php8.2-xml php8.2-curl php8.2-gd php8.2-redis -y

# Install MySQL 8.x
sudo apt install mysql-server -y

# Install Redis
sudo apt install redis-server -y

# Install Composer
curl -sS https://getcomposer.org/installer | php
sudo mv composer.phar /usr/local/bin/composer
```

### 2. Application Deployment

```bash
# Clone repository
cd /var/www
sudo git clone https://github.com/your-org/nexflio.git
cd nexflio

# Checkout production branch
git checkout frontend-redesign

# Install dependencies
composer install --no-dev --optimize-autoloader

# Configure environment
cp .env.example .env
php artisan key:generate

# Configure .env with production values
# DB_HOST=127.0.0.1
# DB_DATABASE=nexflio_prod
# DB_USERNAME=nexflio_user
# DB_PASSWORD=<strong-password>
# APP_DEBUG=false
# APP_URL=https://your-domain.com
# CACHE_STORE=redis
# QUEUE_CONNECTION=redis
# SESSION_DRIVER=redis

# Run migrations
php artisan migrate --force

# Seed database (if needed)
php artisan db:seed

# Link storage
php artisan storage:link

# Optimize
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

### 3. Nginx Configuration

```nginx
server {
    listen 80;
    server_name your-domain.com;
    return 301 https://$server_name$request_uri;
}

server {
    listen 443 ssl http2;
    server_name your-domain.com;
    root /var/www/nexflio/public;
    index index.php;

    ssl_certificate /etc/letsencrypt/live/your-domain.com/fullchain.pem;
    ssl_certificate_key /etc/letsencrypt/live/your-domain.com/privkey.pem;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location ~ \.php$ {
        fastcgi_pass unix:/var/run/php/php8.2-fpm.sock;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
        include fastcgi_params;
    }

    location ~ /\.(?!well-known).* {
        deny all;
    }
}
```

### 4. Queue Worker (Supervisor)

```ini
[program:nexflio-worker]
process_name=%(program_name)s_%(process_num)02d
command=php /var/www/nexflio/artisan queue:work redis --sleep=3 --tries=3 --max-time=3600
autostart=true
autorestart=true
stopasgroup=true
killasgroup=true
user=www-data
numprocs=2
redirect_stderr=true
stdout_logfile=/var/www/nexflio/storage/logs/worker.log
stopwaitsecs=3600
```

### 5. Scheduler (Cron)

```bash
# Add to crontab
* * * * * cd /var/www/nexflio && php artisan schedule:run >> /dev/null 2>&1
```

### 6. SSL Certificate

```bash
# Install Certbot
sudo apt install certbot python3-certbot-nginx -y

# Obtain certificate
sudo certbot --nginx -d your-domain.com

# Auto-renewal
sudo certbot renew --dry-run
```

## Environment Configuration

### Production .env

```env
APP_NAME="Perfect Nails"
APP_ENV=production
APP_KEY=base64:<generated-key>
APP_DEBUG=false
APP_URL=https://your-domain.com

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=nexflio_prod
DB_USERNAME=nexflio_user
DB_PASSWORD=<strong-password>

CACHE_STORE=redis
QUEUE_CONNECTION=redis
SESSION_DRIVER=redis

MAIL_MAILER=smtp
MAIL_HOST=smtp.gmail.com
MAIL_PORT=465
MAIL_USERNAME=NexflioServices@gmail.com
MAIL_PASSWORD=<app-password>
MAIL_ENCRYPTION=ssl
MAIL_FROM_ADDRESS="NexflioServices@gmail.com"
MAIL_FROM_NAME="${APP_NAME}"

GEMINI_API_KEY=<your-key>
FIREBASE_CREDENTIALS=/var/www/nexflio/storage/app/firebase-service-account.json
DIALOGFLOW_WEBHOOK_TOKEN=<your-token>

LOG_CHANNEL=daily
LOG_DAILY_DAYS=14
```

## Monitoring & Maintenance

### Health Checks

```bash
# Check queue worker
php artisan queue:work --once

# Check scheduler
php artisan schedule:list

# Check database
php artisan migrate:status

# Check logs
tail -f storage/logs/laravel.log
```

### Backup Strategy

| Component | Frequency | Method |
|-----------|-----------|--------|
| Database | Daily | `mysqldump` + cron |
| Storage files | Daily | S3 sync / rsync |
| Application code | On deploy | Git |
| Logs | Weekly | Archive + rotate |

### Performance Optimization

| Optimization | Command | Purpose |
|-------------|---------|---------|
| Config cache | `php artisan config:cache` | Faster config loading |
| Route cache | `php artisan route:cache` | Faster route matching |
| View cache | `php artisan view:cache` | Faster view compilation |
| Autoloader | `composer dump-autoload --optimize` | Faster class loading |
| OPcache | PHP OPcache enabled | Bytecode caching |

## Scaling Considerations

| Metric | Current | Production Target |
|--------|---------|-------------------|
| Concurrent users | 1-5 | 50-100 |
| Daily appointments | 10-20 | 50-100 |
| Database size | <100MB | 1-10GB |
| Queue jobs/day | 0-50 | 200-500 |

### Scaling Triggers

- **Database:** Add read replica when query latency > 100ms
- **Queue:** Add worker when job wait time > 30s
- **Web:** Add server when response time > 500ms
- **Storage:** Migrate to S3 when local disk > 50GB

## Cost Estimation (Monthly)

### Single Server (Recommended Start)

| Item | Cost |
|------|------|
| VPS (4GB RAM, 2 vCPU) | $20-40 |
| Domain + SSL | $10-15 |
| Gmail SMTP | Free |
| Firebase FCM | Free |
| Gemini API | Free tier |
| **Total** | **$30-55/month** |

### Scaled (Multi-Server)

| Item | Cost |
|------|------|
| Web Server (2GB RAM) | $15-25 |
| API Server (2GB RAM) | $15-25 |
| Database (RDS) | $25-50 |
| Redis (ElastiCache) | $15-25 |
| Load Balancer | $20 |
| Domain + SSL | $10-15 |
| **Total** | **$100-160/month** |
