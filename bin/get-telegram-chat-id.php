#!/usr/bin/env php
<?php
/**
 * Get Telegram Chat ID from bot's received messages
 * 
 * Usage: php bin/get-telegram-chat-id.php <BOT_TOKEN>
 * 
 * This script shows all chat IDs that have sent messages to your bot.
 * Use the numeric chat ID (not username) for best reliability.
 */

if ($argc < 2) {
    echo "Usage: php get-telegram-chat-id.php <BOT_TOKEN>\n";
    echo "\n";
    echo "Steps:\n";
    echo "1. Create a bot with @BotFather on Telegram\n";
    echo "2. Start a conversation with your bot (send /start)\n";
    echo "3. Run this script with your bot token\n";
    echo "\n";
    echo "Example:\n";
    echo "  php bin/get-telegram-chat-id.php 123456789:ABCdefGHIjklMNOpqrsTUVwxyz\n";
    exit(1);
}

$botToken = $argv[1];

echo "Fetching recent messages from your bot...\n\n";

// Get updates from Telegram
$url = "https://api.telegram.org/bot{$botToken}/getUpdates";

$ch = curl_init($url);
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_TIMEOUT => 10,
]);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($httpCode !== 200) {
    echo "❌ Error: Failed to connect to Telegram API (HTTP {$httpCode})\n";
    echo "Response: {$response}\n";
    exit(1);
}

$data = json_decode($response, true);

if (!isset($data['ok']) || !$data['ok']) {
    echo "❌ Error: " . ($data['description'] ?? 'Unknown error') . "\n";
    exit(1);
}

if (empty($data['result'])) {
    echo "⚠️  No messages found.\n\n";
    echo "To get your chat ID:\n";
    echo "1. Open Telegram\n";
    echo "2. Search for your bot\n";
    echo "3. Send a message to your bot (e.g., /start or Hello)\n";
    echo "4. Run this script again\n\n";
    echo "Alternative method:\n";
    echo "1. Send a message to @userinfobot on Telegram\n";
    echo "2. It will reply with your user ID (a number)\n";
    exit(0);
}

// Get unique chat IDs from updates
$chatIds = [];
foreach ($data['result'] as $update) {
    if (isset($update['message']['chat']['id'])) {
        $chatId = $update['message']['chat']['id'];
        $chatType = $update['message']['chat']['type'] ?? 'unknown';
        $firstName = $update['message']['from']['first_name'] ?? 'Unknown';
        $lastName = $update['message']['from']['last_name'] ?? '';
        $username = $update['message']['from']['username'] ?? null;
        $isBot = $update['message']['from']['is_bot'] ?? false;
        $lastMessage = $update['message']['text'] ?? '(no text)';
        
        // Only show non-bot users
        if (!$isBot) {
            if (!isset($chatIds[$chatId])) {
                $chatIds[$chatId] = [
                    'id' => $chatId,
                    'type' => $chatType,
                    'name' => trim($firstName . ' ' . $lastName),
                    'username' => $username,
                    'last_message' => $lastMessage,
                ];
            }
        }
    }
}

if (empty($chatIds)) {
    echo "⚠️  No user messages found (only bot messages).\n";
    echo "Please send a message to your bot first.\n";
    exit(0);
}

echo "Found " . count($chatIds) . " chat ID(s):\n\n";

foreach ($chatIds as $chat) {
    echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
    echo "Chat ID: {$chat['id']}\n";
    echo "Type: {$chat['type']}\n";
    echo "Name: {$chat['name']}\n";
    if ($chat['username']) {
        echo "Username: @{$chat['username']}\n";
    }
    echo "Last message: {$chat['last_message']}\n";
    echo "\n";
}

echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
echo "\n";
echo "✅ Use the numeric Chat ID (e.g., {$chatIds[array_key_first($chatIds)]['id']}) in your monitor configuration.\n";
echo "   Numeric IDs are more reliable than usernames.\n";
echo "\n";
echo "To update a monitor's chat ID:\n";
echo "  docker exec -i uptimerobot_mysql mysql -u uptimerobot -puptimerobot uptimerobot \\\n";
echo "    -e \"UPDATE monitors SET telegram_chat_id = 'CHAT_ID' WHERE id = MONITOR_ID;\"\n";

