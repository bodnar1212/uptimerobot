# Uptime Monitoring SaaS

A production-ready PHP uptime monitoring application built with PHP 8+, MySQL, and Docker.

## Quick Start

```bash
# Start services
docker-compose up -d

# Access the application
# - Admin Panel: http://localhost:8000/admin
# - API: http://localhost:8000/api/monitors
# - Status Page: http://localhost:8000/status
# - API Explorer: http://localhost:8000/api/docs

# Start workers (in separate terminals)
docker-compose exec php php bin/scheduler.php
docker-compose exec php php bin/worker.php
```

## Documentation

📚 **Complete documentation is available in the [`docs/`](docs/) directory:**

- **[Architecture Guide](docs/ARCHITECTURE.md)** - System design and data flow
- **[Module Documentation](docs/MODULES.md)** - Detailed module documentation
- **[API Reference](docs/API.md)** - Complete REST API documentation
- **[Deployment Guide](docs/DEPLOYMENT.md)** - Production deployment instructions

## Features

- Multiple URL monitors per user
- Concurrent HTTP checks using cURL multi-handle
- Queue-based worker architecture
- Status tracking (up/down) with response times
- Discord notifications via webhooks
- REST API for monitor management
- Admin panel and public status page

## License

MIT
