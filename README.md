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
- Telegram notifications with voice calls
- REST API for monitor management
- Admin panel and public status page

## Telegram Notifications Setup

To configure Telegram notifications:

1. **Create a bot:**
   - Message @BotFather on Telegram
   - Send `/newbot` and follow instructions
   - Save your bot token

2. **Get your Chat ID:**
   ```bash
   # Method 1: Use the helper script (recommended)
   docker exec uptimerobot_php php bin/get-telegram-chat-id.php YOUR_BOT_TOKEN
   
   # Method 2: Send a message to @userinfobot
   # It will reply with your user ID (a number)
   ```

3. **Start a conversation with your bot:**
   - Open Telegram and search for your bot
   - Send it a message (e.g., `/start` or `Hello`)

4. **Configure your monitor:**
   - Via Admin Panel: Add bot token and chat ID
   - Via API: Include `telegram_bot_token` and `telegram_chat_id` in monitor creation/update

**Note:** Use numeric chat IDs (not usernames) for best reliability.

## License

MIT
