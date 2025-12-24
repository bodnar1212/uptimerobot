<?php

require_once __DIR__ . '/../vendor/autoload.php';

use UptimeRobot\Database\Connection;
use UptimeRobot\Repository\UserRepository;
use UptimeRobot\Repository\MonitorRepository;
use UptimeRobot\Repository\MonitorStatusRepository;
use UptimeRobot\Repository\QueueRepository;
use UptimeRobot\Repository\SettingsRepository;
use UptimeRobot\Service\MonitorService;
use UptimeRobot\Http\HttpClient;
use UptimeRobot\Service\HttpCheckService;
use UptimeRobot\Service\NotificationService;
use UptimeRobot\Service\WorkerService;
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
$httpClient = new HttpClient();
$httpCheckService = new HttpCheckService($httpClient, $monitorRepository);
$notificationService = new NotificationService($statusRepository);

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
        // Test Discord notification
        $monitorId = (int)$matches[1];
        $monitor = $monitorService->getById($monitorId, $userId);

        if (!$monitor) {
            errorResponse('Monitor not found', 404);
        }

        $webhookUrl = $monitor->getDiscordWebhookUrl();
        if (empty($webhookUrl)) {
            errorResponse('Discord webhook URL is not configured for this monitor', 400);
        }

        try {
            // Get optional test parameters
            $testStatus = $data['status'] ?? 'up';
            $testMessage = $data['message'] ?? null;

            // Validate status
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

            // Create notifier and send test notification
            $notifier = NotificationFactory::create('discord', [
                'webhook_url' => $webhookUrl,
            ]);

            // If custom message provided, create a custom notification
            if ($testMessage !== null) {
                // Send custom message via Discord webhook
                $payload = [
                    'embeds' => [
                        [
                            'title' => '🧪 Test Notification',
                            'description' => $testMessage,
                            'color' => 0x3498db, // Blue for test
                            'fields' => [
                                [
                                    'name' => 'Monitor URL',
                                    'value' => $monitor->getUrl(),
                                    'inline' => false,
                                ],
                                [
                                    'name' => 'Test Status',
                                    'value' => strtoupper($testStatus),
                                    'inline' => true,
                                ],
                            ],
                            'timestamp' => (new \DateTime())->format('c'),
                            'footer' => [
                                'text' => 'This is a test notification'
                            ]
                        ],
                    ],
                ];

                $ch = curl_init($webhookUrl);
                curl_setopt_array($ch, [
                    CURLOPT_POST => true,
                    CURLOPT_POSTFIELDS => json_encode($payload),
                    CURLOPT_HTTPHEADER => [
                        'Content-Type: application/json',
                    ],
                    CURLOPT_RETURNTRANSFER => true,
                    CURLOPT_TIMEOUT => 10,
                ]);

                $response = curl_exec($ch);
                $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                $error = curl_error($ch);
                curl_close($ch);

                if ($httpCode >= 200 && $httpCode < 300) {
                    jsonResponse([
                        'message' => 'Test notification sent successfully',
                        'webhook_url' => $webhookUrl,
                        'status_code' => $httpCode
                    ]);
                } else {
                    errorResponse("Failed to send notification. Discord returned HTTP {$httpCode}: {$error}", 500);
                }
            } else {
                // Use standard notification format
                $success = $notifier->send($monitor, $testMonitorStatus, null);
                
                if ($success) {
                    jsonResponse([
                        'message' => 'Test notification sent successfully',
                        'monitor_id' => $monitorId,
                        'status' => $testStatus,
                        'webhook_url' => $webhookUrl
                    ]);
                } else {
                    errorResponse('Failed to send notification to Discord', 500);
                }
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
