#!/usr/bin/env php
<?php

require_once __DIR__ . '/../vendor/autoload.php';

use UptimeRobot\Database\Connection;
use UptimeRobot\Repository\UserRepository;
use UptimeRobot\Repository\MonitorRepository;
use UptimeRobot\Repository\QueueRepository;
use UptimeRobot\Service\MonitorService;

// Initialize database connection
Connection::setConfig(require __DIR__ . '/../config/database.php');

// Parse command line arguments
$options = getopt('u:i:t:e:w:h', ['url:', 'interval:', 'timeout:', 'enabled:', 'webhook:', 'telegram-token:', 'telegram-chat:', 'user:', 'help']);

if (isset($options['h']) || isset($options['help']) || !isset($options['u']) && !isset($options['url'])) {
    echo "Usage: php create-monitor.php [options]\n";
    echo "\n";
    echo "Options:\n";
    echo "  -u, --url URL              Monitor URL (required)\n";
    echo "  -i, --interval SECONDS     Check interval in seconds (default: 60)\n";
    echo "  -t, --timeout SECONDS      Request timeout in seconds (default: 30)\n";
    echo "  -e, --enabled 1|0          Enable/disable monitor (default: 1)\n";
    echo "  -w, --webhook URL          Discord webhook URL (optional)\n";
    echo "  --telegram-token TOKEN     Telegram bot token (optional)\n";
    echo "  --telegram-chat ID         Telegram chat ID or username (optional)\n";
    echo "  --user EMAIL               User email (required)\n";
    echo "  -h, --help                 Show this help\n";
    echo "\n";
    echo "Example:\n";
    echo "  php create-monitor.php -u https://example.com -i 60 --user user@example.com\n";
    exit(1);
}

$url = $options['u'] ?? $options['url'] ?? null;
$intervalSeconds = (int)($options['i'] ?? $options['interval'] ?? 60);
$timeoutSeconds = (int)($options['t'] ?? $options['timeout'] ?? 30);
$enabled = isset($options['e']) || isset($options['enabled']) 
    ? (bool)(int)($options['e'] ?? $options['enabled']) 
    : true;
$webhookUrl = $options['w'] ?? $options['webhook'] ?? null;
$telegramBotToken = $options['telegram-token'] ?? null;
$telegramChatId = $options['telegram-chat'] ?? null;
$userEmail = $options['user'] ?? null;

if (!$url) {
    echo "Error: URL is required\n";
    exit(1);
}

if (!$userEmail) {
    echo "Error: User email is required\n";
    exit(1);
}

// Initialize repositories and services
$userRepository = new UserRepository();
$monitorRepository = new MonitorRepository();
$queueRepository = new QueueRepository();

$monitorService = new MonitorService($monitorRepository, $queueRepository);

// Find or create user
$user = $userRepository->findByEmail($userEmail);

if (!$user) {
    // Create user with API key
    $apiKey = bin2hex(random_bytes(32));
    $user = new \UptimeRobot\Entity\User($userEmail, $apiKey);
    $user = $userRepository->create($user);
    echo "Created new user: {$userEmail}\n";
    echo "API Key: {$apiKey}\n\n";
} else {
    echo "Using existing user: {$userEmail}\n\n";
}

try {
    $monitor = $monitorService->create(
        $user->getId(),
        $url,
        $intervalSeconds,
        $timeoutSeconds,
        $enabled,
        $webhookUrl,
        $telegramBotToken,
        $telegramChatId
    );

    echo "Monitor created successfully!\n";
    echo "ID: {$monitor->getId()}\n";
    echo "URL: {$monitor->getUrl()}\n";
    echo "Interval: {$monitor->getIntervalSeconds()} seconds\n";
    echo "Timeout: {$monitor->getTimeoutSeconds()} seconds\n";
    echo "Enabled: " . ($monitor->isEnabled() ? 'Yes' : 'No') . "\n";
    if ($monitor->getDiscordWebhookUrl()) {
        echo "Discord Webhook: {$monitor->getDiscordWebhookUrl()}\n";
    }
    if ($monitor->getTelegramBotToken() && $monitor->getTelegramChatId()) {
        echo "Telegram: Configured (Chat ID: {$monitor->getTelegramChatId()})\n";
    }
} catch (\Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
    exit(1);
}

