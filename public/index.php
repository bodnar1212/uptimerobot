<?php

require_once __DIR__ . '/../vendor/autoload.php';

use UptimeRobot\Database\Connection;
use UptimeRobot\Repository\UserRepository;
use UptimeRobot\Repository\MonitorRepository;
use UptimeRobot\Repository\MonitorStatusRepository;
use UptimeRobot\Repository\QueueRepository;
use UptimeRobot\Repository\SettingsRepository;
use UptimeRobot\Service\MonitorService;
use UptimeRobot\Notification\NotificationFactory;
use UptimeRobot\Entity\MonitorStatus;

// Initialize database connection
Connection::setConfig(require __DIR__ . '/../config/database.php');

// Get request method and path
$method = $_SERVER['REQUEST_METHOD'];
$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$path = rtrim($path, '/');

// Route handling for admin panel, status page, and API explorer (before setting JSON headers)
if ($path === '/admin' || $path === '/admin.php') {
    // Admin panel
    require_once __DIR__ . '/admin.php';
    exit;
} elseif ($path === '/status' || $path === '/status.php') {
    // Status page
    require_once __DIR__ . '/status.php';
    exit;
} elseif ($path === '/api/docs') {
    // API Explorer (Swagger UI)
    require_once __DIR__ . '/api-explorer.php';
    exit;
} elseif ($path === '/api-docs.json') {
    // OpenAPI specification
    header('Content-Type: application/json');
    readfile(__DIR__ . '/api-docs.json');
    exit;
}

// CORS headers (for API access)
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-API-Key');
header('Content-Type: application/json');

// Handle preflight requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// Get request body
$input = file_get_contents('php://input');
$data = json_decode($input, true) ?? [];

// Get API key from header only
$apiKey = $_SERVER['HTTP_X_API_KEY'] ?? null;

// Initialize repositories and services
$userRepository = new UserRepository();
$monitorRepository = new MonitorRepository();
$statusRepository = new MonitorStatusRepository();
$queueRepository = new QueueRepository();

$monitorService = new MonitorService($monitorRepository, $queueRepository);

// Authenticate user
$user = null;
if ($apiKey) {
    $user = $userRepository->findByApiKey($apiKey);
}

function jsonResponse(array $data, int $statusCode = 200): void
{
    http_response_code($statusCode);
    echo json_encode($data, JSON_PRETTY_PRINT);
    exit;
}

function errorResponse(string $message, int $statusCode = 400): void
{
    jsonResponse(['error' => $message], $statusCode);
}

// Route handling
if ($path === '/api/monitors' || strpos($path, '/api/monitors/') === 0) {
    if (!$user) {
        errorResponse('Unauthorized: Invalid or missing API key', 401);
    }

    $userId = $user->getId();

    if ($method === 'POST' && $path === '/api/monitors') {
        // Create monitor
        $url = $data['url'] ?? null;
        // Get default interval from settings if not provided
        $settingsRepository = new SettingsRepository();
        $defaultInterval = (int)($settingsRepository->get('default_monitor_interval') ?: 60);
        $intervalSeconds = $data['interval_seconds'] ?? $defaultInterval;
        $timeoutSeconds = $data['timeout_seconds'] ?? 30;
        $enabled = $data['enabled'] ?? true;
        $discordWebhookUrl = $data['discord_webhook_url'] ?? null;
        $telegramBotToken = $data['telegram_bot_token'] ?? null;
        $telegramChatId = $data['telegram_chat_id'] ?? null;

        if (!$url) {
            errorResponse('URL is required');
        }

        try {
            $monitor = $monitorService->create(
                $userId,
                $url,
                $intervalSeconds,
                $timeoutSeconds,
                $enabled,
                $discordWebhookUrl,
                $telegramBotToken,
                $telegramChatId
            );
            jsonResponse(['monitor' => $monitor->toArray()], 201);
        } catch (\Exception $e) {
            errorResponse($e->getMessage(), 400);
        }
    } elseif ($method === 'GET' && $path === '/api/monitors') {
        // List monitors
        $monitors = $monitorService->getByUserId($userId);
        jsonResponse(['monitors' => array_map(fn($m) => $m->toArray(), $monitors)]);
    } elseif ($method === 'GET' && preg_match('#^/api/monitors/(\d+)$#', $path, $matches)) {
        // Get monitor by ID
        $monitorId = (int)$matches[1];
        $monitor = $monitorService->getById($monitorId, $userId);

        if (!$monitor) {
            errorResponse('Monitor not found', 404);
        }

        // Get latest statuses
        $statuses = $statusRepository->findByMonitorId($monitorId, 10);
        $monitorData = $monitor->toArray();
        $monitorData['recent_statuses'] = array_map(fn($s) => $s->toArray(), $statuses);

        jsonResponse(['monitor' => $monitorData]);
    } elseif ($method === 'PUT' && preg_match('#^/api/monitors/(\d+)$#', $path, $matches)) {
        // Update monitor
        $monitorId = (int)$matches[1];

        try {
            $monitor = $monitorService->update(
                $monitorId,
                $userId,
                $data['url'] ?? null,
                $data['interval_seconds'] ?? null,
                $data['timeout_seconds'] ?? null,
                $data['enabled'] ?? null,
                $data['discord_webhook_url'] ?? null,
                $data['telegram_bot_token'] ?? null,
                $data['telegram_chat_id'] ?? null
            );
            jsonResponse(['monitor' => $monitor->toArray()]);
        } catch (\Exception $e) {
            errorResponse($e->getMessage(), 400);
        }
    } elseif ($method === 'DELETE' && preg_match('#^/api/monitors/(\d+)$#', $path, $matches)) {
        // Delete monitor
        $monitorId = (int)$matches[1];

        try {
            $monitorService->delete($monitorId, $userId);
            jsonResponse(['message' => 'Monitor deleted successfully']);
        } catch (\Exception $e) {
            errorResponse($e->getMessage(), 400);
        }
    } elseif ($method === 'POST' && preg_match('#^/api/monitors/(\d+)/test-notification$#', $path, $matches)) {
        // Test notification (Discord or Telegram)
        $monitorId = (int)$matches[1];
        $monitor = $monitorService->getById($monitorId, $userId);

        if (!$monitor) {
            errorResponse('Monitor not found', 404);
        }

        // Determine notification type from request or auto-detect
        $notificationType = $data['type'] ?? null;
        if (!$notificationType) {
            // Auto-detect: prefer Telegram if configured, otherwise Discord
            if (!empty($monitor->getTelegramBotToken()) && !empty($monitor->getTelegramChatId())) {
                $notificationType = 'telegram';
            } elseif (!empty($monitor->getDiscordWebhookUrl())) {
                $notificationType = 'discord';
            } else {
                errorResponse('No notification method configured for this monitor', 400);
            }
        }

        try {
            // Validate status
            $testStatus = $data['status'] ?? 'up';
            if (!in_array($testStatus, ['up', 'down'])) {
                errorResponse('Invalid status. Must be: up or down', 400);
            }

            // Create a test monitor status
            $testMonitorStatus = new MonitorStatus(
                $monitorId,
                $testStatus,
                null,
                new \DateTime(),
                $data['response_time_ms'] ?? 150,
                $data['http_status_code'] ?? 200,
                $data['error_message'] ?? null
            );

            // Create and send notification
            $notifier = null;
            $notificationConfig = [];

            if ($notificationType === 'discord') {
                $webhookUrl = $monitor->getDiscordWebhookUrl();
                if (empty($webhookUrl)) {
                    errorResponse('Discord webhook URL is not configured for this monitor', 400);
                }
                $notificationConfig = ['webhook_url' => $webhookUrl];
            } elseif ($notificationType === 'telegram') {
                $botToken = $monitor->getTelegramBotToken();
                $chatId = $monitor->getTelegramChatId();
                if (empty($botToken) || empty($chatId)) {
                    errorResponse('Telegram bot token and chat ID are not configured for this monitor', 400);
                }
                $notificationConfig = [
                    'bot_token' => $botToken,
                    'chat_id' => $chatId,
                ];
            } else {
                errorResponse('Invalid notification type. Must be: discord or telegram', 400);
            }

            $notifier = NotificationFactory::create($notificationType, $notificationConfig);
            $success = $notifier->send($monitor, $testMonitorStatus, null);

            if ($success) {
                jsonResponse([
                    'message' => 'Test notification sent successfully',
                    'monitor_id' => $monitorId,
                    'type' => $notificationType,
                    'status' => $testStatus,
                ]);
            } else {
                errorResponse("Failed to send {$notificationType} notification", 500);
            }
        } catch (\Exception $e) {
            errorResponse('Error sending notification: ' . $e->getMessage(), 500);
        }
    } else {
        errorResponse('Method not allowed', 405);
    }
} else {
    errorResponse('Not found', 404);
}
