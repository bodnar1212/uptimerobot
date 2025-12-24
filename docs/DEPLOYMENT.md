# Deployment Guide

Complete guide for deploying UptimeRobot to production.

## Prerequisites

- Docker and Docker Compose installed
- MySQL 8.0+ (or use Docker)
- PHP 8.2+ (if not using Docker)
- Composer (for dependency management)

## Docker Deployment (Recommended)

### Quick Start

```bash
# Clone repository
git clone <repository-url>
cd uptimerobot

# Start services
docker-compose up -d

# Install dependencies
docker-compose exec php composer install

# Services are now running:
# - API: http://localhost:8000
# - Admin: http://localhost:8000/admin
# - Status: http://localhost:8000/status
```

### Production Configuration

1. **Update Environment Variables**

Edit `docker-compose.yml`:

```yaml
environment:
  DB_HOST: mysql
  DB_PORT: 3306
  DB_NAME: uptimerobot_prod
  DB_USER: uptimerobot_prod
  DB_PASSWORD: your-secure-password-here
  WORKER_CONCURRENCY: 100
  SCHEDULER_INTERVAL: 30
```

2. **Use Production Web Server**

Replace PHP built-in server with Nginx/Apache:

```yaml
php:
  # ... existing config ...
  command: php-fpm  # Use PHP-FPM instead
```

Add Nginx service:

```yaml
nginx:
  image: nginx:alpine
  ports:
    - "80:80"
    - "443:443"
  volumes:
    - ./docker/nginx/default.conf:/etc/nginx/conf.d/default.conf
    - .:/var/www/html
  depends_on:
    - php
```

3. **Enable SSL**

Use Let's Encrypt or your SSL certificate:

```yaml
nginx:
  volumes:
    - ./ssl:/etc/nginx/ssl
```

4. **Set Resource Limits**

```yaml
php:
  deploy:
    resources:
      limits:
        cpus: '2'
        memory: 2G
      reservations:
        cpus: '1'
        memory: 1G
```

## Manual Deployment

### 1. Server Setup

```bash
# Install PHP 8.2+
sudo apt-get update
sudo apt-get install php8.2 php8.2-fpm php8.2-mysql php8.2-curl php8.2-json

# Install Composer
curl -sS https://getcomposer.org/installer | php
sudo mv composer.phar /usr/local/bin/composer

# Install MySQL
sudo apt-get install mysql-server
```

### 2. Application Setup

```bash
# Clone repository
git clone <repository-url>
cd uptimerobot

# Install dependencies
composer install --no-dev --optimize-autoloader

# Set permissions
chown -R www-data:www-data /var/www/uptimerobot
chmod -R 755 /var/www/uptimerobot
```

### 3. Database Setup

```bash
# Create database
mysql -u root -p
CREATE DATABASE uptimerobot;
CREATE USER 'uptimerobot'@'localhost' IDENTIFIED BY 'secure-password';
GRANT ALL PRIVILEGES ON uptimerobot.* TO 'uptimerobot'@'localhost';
FLUSH PRIVILEGES;
EXIT;

# Run migrations
mysql -u uptimerobot -p uptimerobot < migrations/001_create_tables.sql
```

### 4. Configuration

Edit `config/database.php`:

```php
return [
    'host' => 'localhost',
    'port' => '3306',
    'database' => 'uptimerobot',
    'username' => 'uptimerobot',
    'password' => 'secure-password',
    'charset' => 'utf8mb4',
    'options' => [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ],
];
```

### 5. Web Server Configuration

#### Nginx

Create `/etc/nginx/sites-available/uptimerobot`:

```nginx
server {
    listen 80;
    server_name your-domain.com;
    root /var/www/uptimerobot/public;
    index index.php;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location ~ \.php$ {
        fastcgi_pass unix:/var/run/php/php8.2-fpm.sock;
        fastcgi_index index.php;
        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
        include fastcgi_params;
    }

    location ~ /\. {
        deny all;
    }
}
```

Enable site:

```bash
sudo ln -s /etc/nginx/sites-available/uptimerobot /etc/nginx/sites-enabled/
sudo nginx -t
sudo systemctl reload nginx
```

#### Apache

Create `/etc/apache2/sites-available/uptimerobot.conf`:

```apache
<VirtualHost *:80>
    ServerName your-domain.com
    DocumentRoot /var/www/uptimerobot/public

    <Directory /var/www/uptimerobot/public>
        Options -Indexes +FollowSymLinks
        AllowOverride All
        Require all granted
    </Directory>

    ErrorLog ${APACHE_LOG_DIR}/uptimerobot_error.log
    CustomLog ${APACHE_LOG_DIR}/uptimerobot_access.log combined
</VirtualHost>
```

Enable site:

```bash
sudo a2ensite uptimerobot
sudo a2enmod rewrite
sudo systemctl reload apache2
```

### 6. Process Management

#### Using systemd

Create `/etc/systemd/system/uptimerobot-scheduler.service`:

```ini
[Unit]
Description=UptimeRobot Scheduler
After=network.target mysql.service

[Service]
Type=simple
User=www-data
WorkingDirectory=/var/www/uptimerobot
ExecStart=/usr/bin/php /var/www/uptimerobot/bin/scheduler.php
Restart=always
RestartSec=10
Environment="DB_HOST=localhost"
Environment="DB_NAME=uptimerobot"
Environment="DB_USER=uptimerobot"
Environment="DB_PASSWORD=secure-password"

[Install]
WantedBy=multi-user.target
```

Create `/etc/systemd/system/uptimerobot-worker.service`:

```ini
[Unit]
Description=UptimeRobot Worker
After=network.target mysql.service

[Service]
Type=simple
User=www-data
WorkingDirectory=/var/www/uptimerobot
ExecStart=/usr/bin/php /var/www/uptimerobot/bin/worker.php
Restart=always
RestartSec=10
Environment="DB_HOST=localhost"
Environment="DB_NAME=uptimerobot"
Environment="DB_USER=uptimerobot"
Environment="DB_PASSWORD=secure-password"
Environment="WORKER_CONCURRENCY=50"

[Install]
WantedBy=multi-user.target
```

Enable and start services:

```bash
sudo systemctl enable uptimerobot-scheduler
sudo systemctl enable uptimerobot-worker
sudo systemctl start uptimerobot-scheduler
sudo systemctl start uptimerobot-worker
```

#### Using Supervisor

Install Supervisor:

```bash
sudo apt-get install supervisor
```

Create `/etc/supervisor/conf.d/uptimerobot-scheduler.conf`:

```ini
[program:uptimerobot-scheduler]
command=/usr/bin/php /var/www/uptimerobot/bin/scheduler.php
directory=/var/www/uptimerobot
user=www-data
autostart=true
autorestart=true
stderr_logfile=/var/log/uptimerobot-scheduler.err.log
stdout_logfile=/var/log/uptimerobot-scheduler.out.log
environment=DB_HOST="localhost",DB_NAME="uptimerobot",DB_USER="uptimerobot",DB_PASSWORD="secure-password"
```

Create `/etc/supervisor/conf.d/uptimerobot-worker.conf`:

```ini
[program:uptimerobot-worker]
command=/usr/bin/php /var/www/uptimerobot/bin/worker.php
directory=/var/www/uptimerobot
user=www-data
autostart=true
autorestart=true
stderr_logfile=/var/log/uptimerobot-worker.err.log
stdout_logfile=/var/log/uptimerobot-worker.out.log
environment=DB_HOST="localhost",DB_NAME="uptimerobot",DB_USER="uptimerobot",DB_PASSWORD="secure-password",WORKER_CONCURRENCY="50"
```

Reload Supervisor:

```bash
sudo supervisorctl reread
sudo supervisorctl update
sudo supervisorctl start uptimerobot-scheduler
sudo supervisorctl start uptimerobot-worker
```

## Monitoring

### Health Checks

Create health check endpoint (`public/health.php`):

```php
<?php
try {
    $pdo = new PDO('mysql:host=localhost;dbname=uptimerobot', 'user', 'pass');
    $pdo->query('SELECT 1');
    echo json_encode(['status' => 'ok']);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}
```

### Logging

Monitor logs:

```bash
# Docker
docker-compose logs -f worker scheduler

# systemd
journalctl -u uptimerobot-worker -f
journalctl -u uptimerobot-scheduler -f

# Supervisor
supervisorctl tail -f uptimerobot-worker
supervisorctl tail -f uptimerobot-scheduler
```

### Metrics

Monitor these metrics:
- Queue job processing rate
- Failed job count
- Average response time
- Database connection pool usage
- Worker memory usage

## Security Checklist

- [ ] Use strong database passwords
- [ ] Enable HTTPS/SSL
- [ ] Restrict database access (firewall)
- [ ] Use environment variables for secrets
- [ ] Enable rate limiting on API
- [ ] Restrict CORS to specific domains
- [ ] Regular security updates
- [ ] Monitor for suspicious activity
- [ ] Backup database regularly
- [ ] Use non-root user for processes

## Backup Strategy

### Database Backup

```bash
# Daily backup script
#!/bin/bash
DATE=$(date +%Y%m%d_%H%M%S)
mysqldump -u uptimerobot -p uptimerobot > /backups/uptimerobot_$DATE.sql
gzip /backups/uptimerobot_$DATE.sql

# Keep last 30 days
find /backups -name "uptimerobot_*.sql.gz" -mtime +30 -delete
```

### Automated Backups

Add to crontab:

```bash
0 2 * * * /path/to/backup-script.sh
```

## Scaling

### Horizontal Scaling

Run multiple worker instances:

```bash
# Worker 1
WORKER_CONCURRENCY=50 php bin/worker.php

# Worker 2
WORKER_CONCURRENCY=50 php bin/worker.php

# Worker 3
WORKER_CONCURRENCY=50 php bin/worker.php
```

### Load Balancing

Use a load balancer (Nginx, HAProxy) to distribute API requests:

```nginx
upstream uptimerobot {
    server 127.0.0.1:8000;
    server 127.0.0.1:8001;
    server 127.0.0.1:8002;
}

server {
    location / {
        proxy_pass http://uptimerobot;
    }
}
```

### Database Optimization

- Add indexes for frequently queried columns
- Use read replicas for status queries
- Partition large tables by date
- Archive old status data

## Troubleshooting

### Workers Not Processing Jobs

1. Check worker logs
2. Verify database connection
3. Check queue for pending jobs
4. Verify worker is running

### High Memory Usage

1. Reduce `WORKER_CONCURRENCY`
2. Increase PHP memory limit
3. Check for memory leaks
4. Restart workers periodically

### Slow Performance

1. Check database indexes
2. Optimize queries
3. Increase worker concurrency
4. Use connection pooling
5. Monitor database performance

## Maintenance

### Regular Tasks

- Monitor disk space (status history grows)
- Clean old queue jobs (automatic after 7 days)
- Review error logs weekly
- Update dependencies monthly
- Backup verification monthly

### Updates

```bash
# Pull latest code
git pull

# Update dependencies
composer install --no-dev --optimize-autoloader

# Run migrations if any
mysql -u uptimerobot -p uptimerobot < migrations/new_migration.sql

# Restart services
sudo systemctl restart uptimerobot-worker
sudo systemctl restart uptimerobot-scheduler
```

