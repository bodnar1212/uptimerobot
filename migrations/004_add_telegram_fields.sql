-- Add Telegram notification fields to monitors table
ALTER TABLE monitors 
ADD COLUMN telegram_bot_token VARCHAR(255) NULL AFTER discord_webhook_url,
ADD COLUMN telegram_chat_id VARCHAR(100) NULL AFTER telegram_bot_token;

