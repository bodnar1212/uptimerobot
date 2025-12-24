<?php

require_once __DIR__ . '/../vendor/autoload.php';

use UptimeRobot\Database\Connection;
use UptimeRobot\Repository\UserRepository;
use UptimeRobot\Repository\MonitorRepository;
use UptimeRobot\Repository\MonitorStatusRepository;
use UptimeRobot\Repository\QueueRepository;
use UptimeRobot\Repository\SettingsRepository;
use UptimeRobot\Service\MonitorService;

// Initialize database connection
Connection::setConfig(require __DIR__ . '/../config/database.php');

// Initialize repositories and services
$userRepository = new UserRepository();
$monitorRepository = new MonitorRepository();
$statusRepository = new MonitorStatusRepository();
$queueRepository = new QueueRepository();
$settingsRepository = new SettingsRepository();
$monitorService = new MonitorService($monitorRepository, $queueRepository);

// Handle form submissions
$message = null;
$messageType = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'create_user') {
        try {
            $email = trim($_POST['email'] ?? '');
            
            if (empty($email)) {
                throw new \Exception('Email is required');
            }
            
            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                throw new \Exception('Invalid email format');
            }
            
            // Check if email already exists
            $existingUser = $userRepository->findByEmail($email);
            if ($existingUser) {
                throw new \Exception('User with this email already exists');
            }
            
            // Generate API key
            $apiKey = bin2hex(random_bytes(32));
            
            $user = new \UptimeRobot\Entity\User($email, $apiKey);
            $user = $userRepository->create($user);
            
            $message = "User created successfully! API Key: {$apiKey}";
            $messageType = "success";
        } catch (\Exception $e) {
            $message = "Error: " . $e->getMessage();
            $messageType = "error";
        }
    } elseif ($action === 'update_user') {
        try {
            $userId = (int)$_POST['user_id'];
            $email = trim($_POST['email'] ?? '');
            $regenerateApiKey = isset($_POST['regenerate_api_key']) && $_POST['regenerate_api_key'] === '1';
            
            if (empty($email)) {
                throw new \Exception('Email is required');
            }
            
            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                throw new \Exception('Invalid email format');
            }
            
            $user = $userRepository->findById($userId);
            if (!$user) {
                throw new \Exception('User not found');
            }
            
            // Check if email is being changed and if it already exists
            if ($email !== $user->getEmail()) {
                $existingUser = $userRepository->findByEmail($email);
                if ($existingUser) {
                    throw new \Exception('User with this email already exists');
                }
            }
            
            $user->setEmail($email);
            
            if ($regenerateApiKey) {
                $apiKey = bin2hex(random_bytes(32));
                $user->setApiKey($apiKey);
                $message = "User updated successfully! New API Key: {$apiKey}";
            } else {
                $message = "User updated successfully!";
            }
            
            $userRepository->update($user);
            $messageType = "success";
        } catch (\Exception $e) {
            $message = "Error: " . $e->getMessage();
            $messageType = "error";
        }
    } elseif ($action === 'delete_user') {
        try {
            $userId = (int)$_POST['user_id'];
            
            $user = $userRepository->findById($userId);
            if (!$user) {
                throw new \Exception('User not found');
            }
            
            // Check if user has monitors
            $monitors = $monitorRepository->findByUserId($userId);
            if (!empty($monitors)) {
                throw new \Exception('Cannot delete user with existing monitors. Please delete monitors first.');
            }
            
            $userRepository->delete($userId);
            
            $message = "User deleted successfully!";
            $messageType = "success";
        } catch (\Exception $e) {
            $message = "Error: " . $e->getMessage();
            $messageType = "error";
        }
    } elseif ($action === 'create') {
        try {
            $userId = (int)$_POST['user_id'];
            $url = trim($_POST['url'] ?? '');
            $intervalSeconds = isset($_POST['interval_seconds']) && $_POST['interval_seconds'] !== '' 
                ? (int)$_POST['interval_seconds'] 
                : (int)($settingsMap['default_monitor_interval'] ?? 60);
            $timeoutSeconds = (int)($_POST['timeout_seconds'] ?? 30);
            $enabled = isset($_POST['enabled']) && $_POST['enabled'] === '1';
            $discordWebhookUrl = trim($_POST['discord_webhook_url'] ?? '') ?: null;
            $telegramBotToken = trim($_POST['telegram_bot_token'] ?? '') ?: null;
            $telegramChatId = trim($_POST['telegram_chat_id'] ?? '') ?: null;
            
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
            
            $message = "Monitor created successfully!";
            $messageType = "success";
        } catch (\Exception $e) {
            $message = "Error: " . $e->getMessage();
            $messageType = "error";
        }
    } elseif ($action === 'update') {
        try {
            $monitorId = (int)$_POST['monitor_id'];
            
            // Get the monitor first to get its user_id
            $monitor = $monitorRepository->findById($monitorId);
            if (!$monitor) {
                throw new \Exception('Monitor not found');
            }
            
            $userId = $monitor->getUserId();
            $url = trim($_POST['url'] ?? '');
            $intervalSeconds = isset($_POST['interval_seconds']) ? (int)$_POST['interval_seconds'] : null;
            $timeoutSeconds = isset($_POST['timeout_seconds']) ? (int)$_POST['timeout_seconds'] : null;
            $enabled = isset($_POST['enabled']) ? ($_POST['enabled'] === '1') : null;
            $discordWebhookUrl = isset($_POST['discord_webhook_url']) ? (trim($_POST['discord_webhook_url']) ?: null) : null;
            $telegramBotToken = isset($_POST['telegram_bot_token']) ? (trim($_POST['telegram_bot_token']) ?: null) : null;
            $telegramChatId = isset($_POST['telegram_chat_id']) ? (trim($_POST['telegram_chat_id']) ?: null) : null;
            
            $monitor = $monitorService->update(
                $monitorId,
                $userId,
                $url ?: null,
                $intervalSeconds,
                $timeoutSeconds,
                $enabled,
                $discordWebhookUrl,
                $telegramBotToken,
                $telegramChatId
            );
            
            $message = "Monitor updated successfully!";
            $messageType = "success";
        } catch (\Exception $e) {
            $message = "Error: " . $e->getMessage();
            $messageType = "error";
        }
    } elseif ($action === 'delete') {
        try {
            $monitorId = (int)$_POST['monitor_id'];
            
            // Get the monitor first to get its user_id
            $monitor = $monitorRepository->findById($monitorId);
            if (!$monitor) {
                throw new \Exception('Monitor not found');
            }
            
            $userId = $monitor->getUserId();
            
            $monitorService->delete($monitorId, $userId);
            
            $message = "Monitor deleted successfully!";
            $messageType = "success";
        } catch (\Exception $e) {
            $message = "Error: " . $e->getMessage();
            $messageType = "error";
        }
    } elseif ($action === 'reset_history') {
        try {
            $monitorId = (int)$_POST['monitor_id'];
            
            // Get the monitor to verify it exists
            $monitor = $monitorRepository->findById($monitorId);
            if (!$monitor) {
                throw new \Exception('Monitor not found');
            }
            
            // Delete all status history for this monitor
            $pdo = Connection::getInstance();
            $stmt = $pdo->prepare('DELETE FROM monitor_statuses WHERE monitor_id = ?');
            $stmt->execute([$monitorId]);
            $deletedCount = $stmt->rowCount();
            
            $message = "Monitor history reset successfully! Deleted {$deletedCount} status record(s).";
            $messageType = "success";
        } catch (\Exception $e) {
            $message = "Error: " . $e->getMessage();
            $messageType = "error";
        }
    }
    
    // Redirect to prevent form resubmission
    if ($message) {
        header("Location: ?msg=" . urlencode($message) . "&type=" . urlencode($messageType));
        exit;
    }
}

// Get message from URL
if (isset($_GET['msg'])) {
    $message = $_GET['msg'];
    $messageType = $_GET['type'] ?? 'success';
}

// Pagination settings
$itemsPerPage = 20;
$statusPage = max(1, (int)($_GET['status_page'] ?? 1));
$queuePage = max(1, (int)($_GET['queue_page'] ?? 1));
$statusOffset = ($statusPage - 1) * $itemsPerPage;
$queueOffset = ($queuePage - 1) * $itemsPerPage;

// Get all data
$pdo = Connection::getInstance();
$usersStmt = $pdo->query('SELECT * FROM users ORDER BY created_at DESC');
$allUsersData = $usersStmt->fetchAll();

$monitorsStmt = $pdo->query('SELECT m.*, u.email as user_email FROM monitors m LEFT JOIN users u ON m.user_id = u.id ORDER BY m.created_at DESC');
$allMonitorsData = $monitorsStmt->fetchAll();

// Get total counts for pagination
$statusCountStmt = $pdo->query('SELECT COUNT(*) as total FROM monitor_statuses');
$statusTotal = $statusCountStmt->fetch()['total'];
$statusTotalPages = ceil($statusTotal / $itemsPerPage);

$queueCountStmt = $pdo->query('SELECT COUNT(*) as total FROM queue_jobs');
$queueTotal = $queueCountStmt->fetch()['total'];
$queueTotalPages = ceil($queueTotal / $itemsPerPage);

// Get paginated statuses
$statusesStmt = $pdo->prepare('SELECT ms.*, m.url FROM monitor_statuses ms LEFT JOIN monitors m ON ms.monitor_id = m.id ORDER BY ms.checked_at DESC LIMIT ? OFFSET ?');
$statusesStmt->execute([$itemsPerPage, $statusOffset]);
$recentStatuses = $statusesStmt->fetchAll();

// Get paginated queue jobs (all statuses: pending, processing, completed, failed)
$queueStmt = $pdo->prepare('SELECT qj.*, m.url FROM queue_jobs qj LEFT JOIN monitors m ON qj.monitor_id = m.id ORDER BY qj.created_at DESC LIMIT ? OFFSET ?');
$queueStmt->execute([$itemsPerPage, $queueOffset]);
$queueJobs = $queueStmt->fetchAll();

// Get settings
$allSettings = $settingsRepository->getAll();
$settingsMap = [];
foreach ($allSettings as $setting) {
    $settingsMap[$setting['key']] = $setting['value'];
}
$schedulerInterval = (int)($settingsMap['scheduler_interval'] ?? 30);
$defaultMonitorInterval = (int)($settingsMap['default_monitor_interval'] ?? 60);

$statsStmt = $pdo->query('SELECT 
    (SELECT COUNT(*) FROM users) as total_users,
    (SELECT COUNT(*) FROM monitors) as total_monitors,
    (SELECT COUNT(*) FROM monitors WHERE enabled = 1) as enabled_monitors,
    (SELECT COUNT(*) FROM queue_jobs WHERE status = "pending") as pending_jobs,
    (SELECT COUNT(*) FROM queue_jobs WHERE status = "processing") as processing_jobs,
    (SELECT COUNT(*) FROM monitor_statuses WHERE status = "up") as up_statuses,
    (SELECT COUNT(*) FROM monitor_statuses WHERE status = "down") as down_statuses
');
$stats = $statsStmt->fetch();

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>UptimeRobot Admin Panel</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, Cantarell, sans-serif;
            background: #f5f5f5;
            color: #333;
            line-height: 1.6;
        }
        
        .header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 2rem;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        
        .header h1 {
            font-size: 2rem;
            margin-bottom: 0.5rem;
        }
        
        .header p {
            opacity: 0.9;
        }
        
        .container {
            max-width: 1400px;
            margin: 0 auto;
            padding: 2rem;
        }
        
        .message {
            padding: 1rem;
            border-radius: 4px;
            margin-bottom: 1.5rem;
            font-weight: 500;
        }
        
        .message-success {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        
        .message-error {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
        
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2rem;
        }
        
        .stat-card {
            background: white;
            padding: 1.5rem;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            border-left: 4px solid #667eea;
        }
        
        .stat-card h3 {
            font-size: 0.875rem;
            text-transform: uppercase;
            color: #666;
            margin-bottom: 0.5rem;
            letter-spacing: 0.5px;
        }
        
        .stat-card .value {
            font-size: 2rem;
            font-weight: bold;
            color: #333;
        }
        
        .section {
            background: white;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            margin-bottom: 2rem;
            overflow: hidden;
        }
        
        .section-header {
            padding: 1.5rem;
            background: #f8f9fa;
            border-bottom: 1px solid #e9ecef;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .section-header h2 {
            font-size: 1.25rem;
            color: #333;
        }
        
        .btn {
            padding: 0.5rem 1rem;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 0.875rem;
            font-weight: 500;
            text-decoration: none;
            display: inline-block;
            transition: background 0.2s;
        }
        
        .btn-primary {
            background: #667eea;
            color: white;
        }
        
        .btn-primary:hover {
            background: #5568d3;
        }
        
        .btn-sm {
            padding: 0.25rem 0.75rem;
            font-size: 0.75rem;
        }
        
        .btn-danger {
            background: #dc3545;
            color: white;
        }
        
        .btn-danger:hover {
            background: #c82333;
        }
        
        .btn-warning {
            background: #ffc107;
            color: #000;
        }
        
        .btn-warning:hover {
            background: #e0a800;
        }
        
        .btn-success {
            background: #28a745;
            color: white;
        }
        
        .btn-success:hover {
            background: #218838;
        }
        
        .table-container {
            overflow-x: auto;
        }
        
        table {
            width: 100%;
            border-collapse: collapse;
        }
        
        thead {
            background: #f8f9fa;
        }
        
        th {
            padding: 1rem;
            text-align: left;
            font-weight: 600;
            color: #666;
            font-size: 0.875rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        td {
            padding: 1rem;
            border-top: 1px solid #e9ecef;
        }
        
        tbody tr:hover {
            background: #f8f9fa;
        }
        
        .badge {
            display: inline-block;
            padding: 0.25rem 0.75rem;
            border-radius: 12px;
            font-size: 0.75rem;
            font-weight: 600;
            text-transform: uppercase;
        }
        
        .badge-success {
            background: #d4edda;
            color: #155724;
        }
        
        .badge-danger {
            background: #f8d7da;
            color: #721c24;
        }
        
        .badge-warning {
            background: #fff3cd;
            color: #856404;
        }
        
        .badge-info {
            background: #d1ecf1;
            color: #0c5460;
        }
        
        .badge-secondary {
            background: #e2e3e5;
            color: #383d41;
        }
        
        .url-cell {
            max-width: 300px;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }
        
        .text-muted {
            color: #6c757d;
            font-size: 0.875rem;
        }
        
        .modal {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.5);
            overflow: auto;
        }
        
        .modal-content {
            background: white;
            margin: 5% auto;
            padding: 2rem;
            border-radius: 8px;
            max-width: 600px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.3);
        }
        
        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1.5rem;
        }
        
        .modal-header h3 {
            font-size: 1.5rem;
            color: #333;
        }
        
        .close {
            color: #aaa;
            font-size: 28px;
            font-weight: bold;
            cursor: pointer;
            border: none;
            background: none;
        }
        
        .close:hover {
            color: #000;
        }
        
        .form-group {
            margin-bottom: 1.5rem;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 0.5rem;
            font-weight: 500;
            color: #333;
        }
        
        .form-group input,
        .form-group select {
            width: 100%;
            padding: 0.75rem;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 0.875rem;
        }
        
        .form-group input:focus,
        .form-group select:focus {
            outline: none;
            border-color: #667eea;
        }
        
        .form-group small {
            display: block;
            margin-top: 0.25rem;
            color: #6c757d;
            font-size: 0.75rem;
        }
        
        .form-checkbox {
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .form-checkbox input {
            width: auto;
        }
        
        .form-actions {
            display: flex;
            gap: 1rem;
            justify-content: flex-end;
            margin-top: 2rem;
        }
        
        .action-buttons {
            display: flex;
            gap: 0.5rem;
        }
        
        .pagination {
            display: flex;
            justify-content: center;
            align-items: center;
            gap: 0.5rem;
            margin-top: 1.5rem;
            padding: 1rem;
            border-top: 1px solid #e9ecef;
            flex-wrap: wrap;
        }
        
        .pagination-info {
            color: #6b7280;
            font-size: 0.875rem;
            margin: 0 1rem;
            white-space: nowrap;
        }
        
        .pagination-btn {
            padding: 0.5rem 1rem;
            border: 1px solid #ddd;
            border-radius: 4px;
            background: white;
            color: #333;
            cursor: pointer;
            font-size: 0.875rem;
            text-decoration: none;
            display: inline-block;
            transition: all 0.2s;
            min-width: 40px;
            text-align: center;
        }
        
        .pagination-btn:hover:not(.disabled) {
            background: #f8f9fa;
            border-color: #667eea;
            color: #667eea;
        }
        
        .pagination-btn.disabled {
            opacity: 0.5;
            cursor: not-allowed;
            pointer-events: none;
        }
        
        .pagination-btn.active {
            background: #667eea;
            color: white;
            border-color: #667eea;
            font-weight: 600;
        }
        
        .pagination-numbers {
            display: flex;
            gap: 0.25rem;
            align-items: center;
        }
        
        @media (max-width: 768px) {
            .pagination {
                gap: 0.25rem;
            }
            
            .pagination-btn {
                padding: 0.4rem 0.8rem;
                font-size: 0.8rem;
                min-width: 35px;
            }
            
            .pagination-info {
                margin: 0.5rem 0;
                width: 100%;
                text-align: center;
            }
        }
        
        .btn-copy-api {
            display: inline-flex;
            align-items: center;
            gap: 0.25rem;
            padding: 0.25rem 0.5rem;
            background: #667eea;
            color: white;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 0.75rem;
            transition: all 0.2s;
        }
        
        .btn-copy-api:hover {
            background: #5568d3;
        }
        
        .btn-copy-api:active {
            transform: scale(0.95);
        }
        
        .btn-copy-api.copied {
            background: #10b981;
        }
        
        .btn-copy-api .copy-icon {
            font-size: 0.875rem;
        }
        
        .btn-copy-api .copy-text {
            font-size: 0.75rem;
        }
        
        code {
            user-select: all;
            -webkit-user-select: all;
            -moz-user-select: all;
            -ms-user-select: all;
        }
    </style>
</head>
<body>
    <div class="header">
        <h1>🚀 UptimeRobot Admin Panel</h1>
        <p>Monitor and manage your uptime monitoring system</p>
    </div>
    
    <div class="container">
        <?php if ($message): ?>
        <div class="message message-<?= $messageType ?>">
            <?= htmlspecialchars($message) ?>
        </div>
        <?php endif; ?>
        
        <!-- Statistics -->
        <div class="stats-grid">
            <div class="stat-card">
                <h3>Total Users</h3>
                <div class="value"><?= htmlspecialchars($stats['total_users']) ?></div>
            </div>
            <div class="stat-card">
                <h3>Total Monitors</h3>
                <div class="value"><?= htmlspecialchars($stats['total_monitors']) ?></div>
            </div>
            <div class="stat-card">
                <h3>Enabled Monitors</h3>
                <div class="value"><?= htmlspecialchars($stats['enabled_monitors']) ?></div>
            </div>
            <div class="stat-card">
                <h3>Pending Jobs</h3>
                <div class="value"><?= htmlspecialchars($stats['pending_jobs']) ?></div>
            </div>
            <div class="stat-card">
                <h3>Processing Jobs</h3>
                <div class="value"><?= htmlspecialchars($stats['processing_jobs']) ?></div>
            </div>
            <div class="stat-card">
                <h3>Up Statuses</h3>
                <div class="value"><?= htmlspecialchars($stats['up_statuses']) ?></div>
            </div>
            <div class="stat-card">
                <h3>Down Statuses</h3>
                <div class="value"><?= htmlspecialchars($stats['down_statuses']) ?></div>
            </div>
        </div>
        
        <!-- Users Section -->
        <div class="section">
            <div class="section-header">
                <h2>Users</h2>
                <button class="btn btn-primary" onclick="openCreateUserModal()">+ Create User</button>
            </div>
            <div class="table-container">
                <table>
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Email</th>
                            <th>API Key</th>
                            <th>Created At</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($allUsersData)): ?>
                        <tr>
                            <td colspan="5" style="text-align: center; padding: 2rem; color: #6b7280;">No users found</td>
                        </tr>
                        <?php else: ?>
                        <?php foreach ($allUsersData as $user): ?>
                        <tr>
                            <td><?= htmlspecialchars($user['id']) ?></td>
                            <td><?= htmlspecialchars($user['email']) ?></td>
                            <td>
                                <div style="display: flex; align-items: center; gap: 0.5rem;">
                                    <code id="api-key-<?= $user['id'] ?>" style="font-size: 0.75rem; font-family: monospace; background: #f5f5f5; padding: 0.25rem 0.5rem; border-radius: 4px; user-select: all;"><?= htmlspecialchars($user['api_key']) ?></code>
                                    <button class="btn-copy-api" onclick="copyApiKey(<?= $user['id'] ?>, '<?= htmlspecialchars(addslashes($user['api_key'])) ?>', event)" title="Copy API Key">
                                        <span class="copy-icon">📋</span>
                                        <span class="copy-text">Copy</span>
                                    </button>
                                </div>
                            </td>
                            <td><?= htmlspecialchars($user['created_at']) ?></td>
                            <td>
                                <div class="action-buttons">
                                    <button class="btn btn-sm btn-primary" onclick="openEditUserModal(<?= htmlspecialchars(json_encode($user)) ?>)">Edit</button>
                                    <button class="btn btn-sm btn-danger" onclick="deleteUser(<?= htmlspecialchars($user['id']) ?>)">Delete</button>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
        
        <!-- Monitors Section -->
        <div class="section">
            <div class="section-header">
                <h2>Monitors</h2>
                <button class="btn btn-primary" onclick="openCreateModal()">+ Create Monitor</button>
            </div>
            <div class="table-container">
                <table>
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>User</th>
                            <th>URL</th>
                            <th>Interval</th>
                            <th>Timeout</th>
                            <th>Status</th>
                            <th>Discord</th>
                            <th>Telegram</th>
                            <th>Created At</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($allMonitorsData as $monitor): ?>
                        <tr>
                            <td><?= htmlspecialchars($monitor['id']) ?></td>
                            <td><?= htmlspecialchars($monitor['user_email'] ?? 'N/A') ?></td>
                            <td class="url-cell" title="<?= htmlspecialchars($monitor['url']) ?>">
                                <a href="<?= htmlspecialchars($monitor['url']) ?>" target="_blank">
                                    <?= htmlspecialchars($monitor['url']) ?>
                                </a>
                            </td>
                            <td><?= htmlspecialchars($monitor['interval_seconds']) ?>s</td>
                            <td><?= htmlspecialchars($monitor['timeout_seconds']) ?>s</td>
                            <td>
                                <?php if ($monitor['enabled']): ?>
                                    <span class="badge badge-success">Enabled</span>
                                <?php else: ?>
                                    <span class="badge badge-secondary">Disabled</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if (!empty($monitor['discord_webhook_url'])): ?>
                                    <span class="badge badge-success" title="<?= htmlspecialchars($monitor['discord_webhook_url']) ?>">✅ Configured</span>
                                <?php else: ?>
                                    <span class="badge badge-secondary">Not configured</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if (!empty($monitor['telegram_bot_token']) && !empty($monitor['telegram_chat_id'])): ?>
                                    <span class="badge badge-success" title="Bot Token: <?= htmlspecialchars(substr($monitor['telegram_bot_token'], 0, 20)) ?>... | Chat ID: <?= htmlspecialchars($monitor['telegram_chat_id']) ?>">✅ Configured</span>
                                <?php else: ?>
                                    <span class="badge badge-secondary">Not configured</span>
                                <?php endif; ?>
                            </td>
                            <td><?= htmlspecialchars($monitor['created_at']) ?></td>
                            <td>
                                <div class="action-buttons">
                                    <button class="btn btn-sm btn-primary" onclick="openEditModal(<?= htmlspecialchars(json_encode($monitor)) ?>)">Edit</button>
                                    <button class="btn btn-sm btn-warning" onclick="resetMonitorHistory(<?= htmlspecialchars($monitor['id']) ?>)">Reset History</button>
                                    <button class="btn btn-sm btn-danger" onclick="deleteMonitor(<?= htmlspecialchars($monitor['id']) ?>)">Delete</button>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
        
        <!-- Recent Statuses Section -->
        <div class="section">
            <div class="section-header">
                <h2>Recent Monitor Statuses</h2>
            </div>
            <div class="table-container">
                <table>
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Monitor URL</th>
                            <th>Status</th>
                            <th>Response Time</th>
                            <th>HTTP Code</th>
                            <th>Checked At</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($recentStatuses)): ?>
                        <tr>
                            <td colspan="6" style="text-align: center; padding: 2rem; color: #6b7280;">No status records found</td>
                        </tr>
                        <?php else: ?>
                        <?php foreach ($recentStatuses as $status): ?>
                        <tr>
                            <td><?= htmlspecialchars($status['id']) ?></td>
                            <td class="url-cell" title="<?= htmlspecialchars($status['url'] ?? 'N/A') ?>">
                                <?= htmlspecialchars($status['url'] ?? 'N/A') ?>
                            </td>
                            <td>
                                <?php
                                $statusClass = match($status['status']) {
                                    'up' => 'badge-success',
                                    'down' => 'badge-danger',
                                    default => 'badge-secondary'
                                };
                                ?>
                                <span class="badge <?= $statusClass ?>"><?= strtoupper(htmlspecialchars($status['status'])) ?></span>
                            </td>
                            <td><?= htmlspecialchars($status['response_time_ms'] ?? 'N/A') ?> ms</td>
                            <td><?= htmlspecialchars($status['http_status_code'] ?? 'N/A') ?></td>
                            <td><?= htmlspecialchars($status['checked_at']) ?></td>
                        </tr>
                        <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
            <?php if ($statusTotalPages > 1): ?>
            <div class="pagination">
                <?php
                $statusQueryParams = $_GET;
                unset($statusQueryParams['status_page']);
                $statusBaseUrl = '?' . http_build_query(array_merge($statusQueryParams, ['status_page' => '']));
                ?>
                <a href="<?= htmlspecialchars($statusBaseUrl . '1') ?>" class="pagination-btn <?= $statusPage === 1 ? 'disabled' : '' ?>">First</a>
                <a href="<?= htmlspecialchars($statusBaseUrl . max(1, $statusPage - 1)) ?>" class="pagination-btn <?= $statusPage === 1 ? 'disabled' : '' ?>">Previous</a>
                
                <div class="pagination-numbers">
                    <?php
                    $startPage = max(1, $statusPage - 2);
                    $endPage = min($statusTotalPages, $statusPage + 2);
                    
                    if ($startPage > 1): ?>
                        <a href="<?= htmlspecialchars($statusBaseUrl . '1') ?>" class="pagination-btn">1</a>
                        <?php if ($startPage > 2): ?>
                            <span class="pagination-btn disabled">...</span>
                        <?php endif; ?>
                    <?php endif; ?>
                    
                    <?php for ($i = $startPage; $i <= $endPage; $i++): ?>
                        <a href="<?= htmlspecialchars($statusBaseUrl . $i) ?>" class="pagination-btn <?= $i === $statusPage ? 'active' : '' ?>"><?= $i ?></a>
                    <?php endfor; ?>
                    
                    <?php if ($endPage < $statusTotalPages): ?>
                        <?php if ($endPage < $statusTotalPages - 1): ?>
                            <span class="pagination-btn disabled">...</span>
                        <?php endif; ?>
                        <a href="<?= htmlspecialchars($statusBaseUrl . $statusTotalPages) ?>" class="pagination-btn"><?= $statusTotalPages ?></a>
                    <?php endif; ?>
                </div>
                
                <a href="<?= htmlspecialchars($statusBaseUrl . min($statusTotalPages, $statusPage + 1)) ?>" class="pagination-btn <?= $statusPage >= $statusTotalPages ? 'disabled' : '' ?>">Next</a>
                <a href="<?= htmlspecialchars($statusBaseUrl . $statusTotalPages) ?>" class="pagination-btn <?= $statusPage >= $statusTotalPages ? 'disabled' : '' ?>">Last</a>
                
                <span class="pagination-info">
                    Showing <?= $statusOffset + 1 ?>-<?= min($statusOffset + $itemsPerPage, $statusTotal) ?> of <?= $statusTotal ?>
                </span>
            </div>
            <?php endif; ?>
        </div>
        
        <!-- Queue Jobs Section -->
        <div class="section">
            <div class="section-header">
                <h2>Queue Jobs</h2>
            </div>
            <div class="table-container">
                <table>
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Monitor URL</th>
                            <th>Status</th>
                            <th>Scheduled At</th>
                            <th>Attempts</th>
                            <th>Processed At</th>
                            <th>Created At</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($queueJobs)): ?>
                        <tr>
                            <td colspan="7" style="text-align: center; padding: 2rem; color: #6b7280;">No queue jobs found</td>
                        </tr>
                        <?php else: ?>
                        <?php foreach ($queueJobs as $job): ?>
                        <tr>
                            <td><?= htmlspecialchars($job['id']) ?></td>
                            <td class="url-cell" title="<?= htmlspecialchars($job['url'] ?? 'N/A') ?>">
                                <?= htmlspecialchars($job['url'] ?? 'N/A') ?>
                            </td>
                            <td>
                                <?php
                                $statusClass = match($job['status']) {
                                    'pending' => 'badge-warning',
                                    'processing' => 'badge-info',
                                    'completed' => 'badge-success',
                                    'failed' => 'badge-danger',
                                    default => 'badge-secondary'
                                };
                                ?>
                                <span class="badge <?= $statusClass ?>"><?= htmlspecialchars(ucfirst($job['status'])) ?></span>
                            </td>
                            <td><?= htmlspecialchars($job['scheduled_at']) ?></td>
                            <td><?= htmlspecialchars($job['attempts']) ?></td>
                            <td><?= htmlspecialchars($job['processed_at'] ?? 'N/A') ?></td>
                            <td><?= htmlspecialchars($job['created_at']) ?></td>
                        </tr>
                        <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
            <?php if ($queueTotalPages > 1): ?>
            <div class="pagination">
                <?php
                $queueQueryParams = $_GET;
                unset($queueQueryParams['queue_page']);
                $queueBaseUrl = '?' . http_build_query(array_merge($queueQueryParams, ['queue_page' => '']));
                ?>
                <a href="<?= htmlspecialchars($queueBaseUrl . '1') ?>" class="pagination-btn <?= $queuePage === 1 ? 'disabled' : '' ?>">First</a>
                <a href="<?= htmlspecialchars($queueBaseUrl . max(1, $queuePage - 1)) ?>" class="pagination-btn <?= $queuePage === 1 ? 'disabled' : '' ?>">Previous</a>
                
                <div class="pagination-numbers">
                    <?php
                    $startPage = max(1, $queuePage - 2);
                    $endPage = min($queueTotalPages, $queuePage + 2);
                    
                    if ($startPage > 1): ?>
                        <a href="<?= htmlspecialchars($queueBaseUrl . '1') ?>" class="pagination-btn">1</a>
                        <?php if ($startPage > 2): ?>
                            <span class="pagination-btn disabled">...</span>
                        <?php endif; ?>
                    <?php endif; ?>
                    
                    <?php for ($i = $startPage; $i <= $endPage; $i++): ?>
                        <a href="<?= htmlspecialchars($queueBaseUrl . $i) ?>" class="pagination-btn <?= $i === $queuePage ? 'active' : '' ?>"><?= $i ?></a>
                    <?php endfor; ?>
                    
                    <?php if ($endPage < $queueTotalPages): ?>
                        <?php if ($endPage < $queueTotalPages - 1): ?>
                            <span class="pagination-btn disabled">...</span>
                        <?php endif; ?>
                        <a href="<?= htmlspecialchars($queueBaseUrl . $queueTotalPages) ?>" class="pagination-btn"><?= $queueTotalPages ?></a>
                    <?php endif; ?>
                </div>
                
                <a href="<?= htmlspecialchars($queueBaseUrl . min($queueTotalPages, $queuePage + 1)) ?>" class="pagination-btn <?= $queuePage >= $queueTotalPages ? 'disabled' : '' ?>">Next</a>
                <a href="<?= htmlspecialchars($queueBaseUrl . $queueTotalPages) ?>" class="pagination-btn <?= $queuePage >= $queueTotalPages ? 'disabled' : '' ?>">Last</a>
                
                <span class="pagination-info">
                    Showing <?= $queueOffset + 1 ?>-<?= min($queueOffset + $itemsPerPage, $queueTotal) ?> of <?= $queueTotal ?>
                </span>
            </div>
            <?php elseif ($queueTotal > 0): ?>
            <div style="margin-top: 1rem; padding: 1rem; background: #f8f9fa; border-radius: 4px; border-top: 1px solid #e9ecef;">
                <span style="color: #6b7280; font-size: 0.875rem;">
                    Showing all <?= $queueTotal ?> queue job<?= $queueTotal !== 1 ? 's' : '' ?>
                </span>
            </div>
            <?php endif; ?>
        </div>
        
        <!-- Settings Section -->
        <div class="section">
            <div class="section-header">
                <h2>Settings</h2>
            </div>
            <div style="padding: 1.5rem 0;">
                <form method="POST" action="" style="max-width: 600px;">
                    <input type="hidden" name="action" value="update_settings">
                    <div class="form-group">
                        <label for="scheduler_interval">Scheduler Interval (seconds)</label>
                        <input type="number" name="scheduler_interval" id="scheduler_interval" value="<?= htmlspecialchars($schedulerInterval) ?>" min="10" max="3600" required>
                        <small>How often the scheduler runs to check for due monitors (10-3600 seconds)</small>
                    </div>
                    <div class="form-group">
                        <label for="default_monitor_interval">Default Monitor Interval (seconds)</label>
                        <input type="number" name="default_monitor_interval" id="default_monitor_interval" value="<?= htmlspecialchars($defaultMonitorInterval) ?>" min="60" max="86400" required>
                        <small>Default check interval for new monitors (60-86400 seconds = 1 minute to 24 hours)</small>
                    </div>
                    <div class="form-group">
                        <button type="submit" class="btn btn-primary">Save Settings</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <!-- Create User Modal -->
    <div id="createUserModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3>Create User</h3>
                <button class="close" onclick="closeModal('createUserModal')">&times;</button>
            </div>
            <form method="POST" action="">
                <input type="hidden" name="action" value="create_user">
                <div class="form-group">
                    <label for="create_user_email">Email *</label>
                    <input type="email" name="email" id="create_user_email" required placeholder="user@example.com">
                    <small>User email address. API key will be generated automatically.</small>
                </div>
                <div class="form-actions">
                    <button type="button" class="btn" onclick="closeModal('createUserModal')">Cancel</button>
                    <button type="submit" class="btn btn-primary">Create</button>
                </div>
            </form>
        </div>
    </div>
    
    <!-- Edit User Modal -->
    <div id="editUserModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3>Edit User</h3>
                <button class="close" onclick="closeModal('editUserModal')">&times;</button>
            </div>
            <form method="POST" action="" id="editUserForm">
                <input type="hidden" name="action" value="update_user">
                <input type="hidden" name="user_id" id="edit_user_user_id">
                <div class="form-group">
                    <label for="edit_user_email">Email *</label>
                    <input type="email" name="email" id="edit_user_email" required>
                    <small>User email address</small>
                </div>
                <div class="form-group">
                    <div class="form-checkbox">
                        <input type="checkbox" name="regenerate_api_key" id="edit_regenerate_api_key" value="1">
                        <label for="edit_regenerate_api_key">Regenerate API Key</label>
                    </div>
                    <small>Check this to generate a new API key. The old key will no longer work.</small>
                </div>
                <div class="form-actions">
                    <button type="button" class="btn" onclick="closeModal('editUserModal')">Cancel</button>
                    <button type="submit" class="btn btn-primary">Update</button>
                </div>
            </form>
        </div>
    </div>
    
    <!-- Delete User Confirmation Modal -->
    <div id="deleteUserModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3>Delete User</h3>
                <button class="close" onclick="closeModal('deleteUserModal')">&times;</button>
            </div>
            <p>Are you sure you want to delete this user? This action cannot be undone. All monitors belonging to this user must be deleted first.</p>
            <form method="POST" action="" id="deleteUserForm">
                <input type="hidden" name="action" value="delete_user">
                <input type="hidden" name="user_id" id="delete_user_id">
                <div class="form-actions">
                    <button type="button" class="btn" onclick="closeModal('deleteUserModal')">Cancel</button>
                    <button type="submit" class="btn btn-danger">Delete</button>
                </div>
            </form>
        </div>
    </div>
    
    <!-- Create Monitor Modal -->
    <div id="createModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3>Create Monitor</h3>
                <button class="close" onclick="closeModal('createModal')">&times;</button>
            </div>
            <form method="POST" action="">
                <input type="hidden" name="action" value="create">
                <div class="form-group">
                    <label for="create_user_id">User *</label>
                    <select name="user_id" id="create_user_id" required>
                        <?php foreach ($allUsersData as $user): ?>
                        <option value="<?= htmlspecialchars($user['id']) ?>"><?= htmlspecialchars($user['email']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label for="create_url">URL *</label>
                    <input type="url" name="url" id="create_url" required placeholder="https://example.com">
                    <small>Must start with http:// or https://</small>
                </div>
                <div class="form-group">
                    <label for="create_interval">Interval (seconds) *</label>
                    <input type="number" name="interval_seconds" id="create_interval" value="<?= htmlspecialchars($defaultMonitorInterval) ?>" min="60" max="86400" required>
                    <small>Minimum 60 seconds, maximum 86400 seconds (24 hours)</small>
                </div>
                <div class="form-group">
                    <label for="create_timeout">Timeout (seconds) *</label>
                    <input type="number" name="timeout_seconds" id="create_timeout" value="30" min="1" max="300" required>
                    <small>Minimum 1 second, maximum 300 seconds</small>
                </div>
                <div class="form-group">
                    <label for="create_webhook">Discord Webhook URL</label>
                    <input type="url" name="discord_webhook_url" id="create_webhook" placeholder="https://discord.com/api/webhooks/...">
                    <small>Optional. Get webhook URL from Discord: Server Settings → Integrations → Webhooks → New Webhook</small>
                </div>
                <div class="form-group">
                    <label for="create_telegram_token">Telegram Bot Token</label>
                    <input type="text" name="telegram_bot_token" id="create_telegram_token" placeholder="123456789:ABCdefGHIjklMNOpqrsTUVwxyz">
                    <small>Optional. Get bot token from @BotFather on Telegram</small>
                </div>
                <div class="form-group">
                    <label for="create_telegram_chat">Telegram Chat ID</label>
                    <input type="text" name="telegram_chat_id" id="create_telegram_chat" placeholder="123456789 or @username">
                    <small>
                        Optional. Leave empty to remove. <strong>For text & voice messages:</strong> Use numeric ID (e.g., 123456789). Get it by messaging @userinfobot. <strong>For voice calls:</strong> Use username format (e.g., @yourusername). Requires CallMeBot authorization (@CallMeBot_txtbot). <strong>Important:</strong> Start a conversation with your bot first!
                    </small>
                </div>
                <div class="form-group">
                    <div class="form-checkbox">
                        <input type="checkbox" name="enabled" id="create_enabled" value="1" checked>
                        <label for="create_enabled">Enabled</label>
                    </div>
                </div>
                <div class="form-actions">
                    <button type="button" class="btn" onclick="closeModal('createModal')">Cancel</button>
                    <button type="submit" class="btn btn-primary">Create</button>
                </div>
            </form>
        </div>
    </div>
    
    <!-- Edit Monitor Modal -->
    <div id="editModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3>Edit Monitor</h3>
                <button class="close" onclick="closeModal('editModal')">&times;</button>
            </div>
            <form method="POST" action="">
                <input type="hidden" name="action" value="update">
                <input type="hidden" name="monitor_id" id="edit_monitor_id">
                <div class="form-group">
                    <label for="edit_url">URL *</label>
                    <input type="url" name="url" id="edit_url" required placeholder="https://example.com">
                    <small>Must start with http:// or https://</small>
                </div>
                <div class="form-group">
                    <label for="edit_interval">Interval (seconds)</label>
                    <input type="number" name="interval_seconds" id="edit_interval" min="60" max="86400">
                    <small>Leave empty to keep current value. Minimum 60 seconds, maximum 86400 seconds</small>
                </div>
                <div class="form-group">
                    <label for="edit_timeout">Timeout (seconds)</label>
                    <input type="number" name="timeout_seconds" id="edit_timeout" min="1" max="300">
                    <small>Leave empty to keep current value. Minimum 1 second, maximum 300 seconds</small>
                </div>
                <div class="form-group">
                    <label for="edit_webhook">Discord Webhook URL</label>
                    <input type="url" name="discord_webhook_url" id="edit_webhook" placeholder="https://discord.com/api/webhooks/...">
                    <small>Leave empty to remove webhook</small>
                </div>
                <div class="form-group">
                    <label for="edit_telegram_token">Telegram Bot Token</label>
                    <input type="text" name="telegram_bot_token" id="edit_telegram_token" placeholder="123456789:ABCdefGHIjklMNOpqrsTUVwxyz">
                    <small>Leave empty to remove. Get bot token from @BotFather on Telegram</small>
                </div>
                <div class="form-group">
                    <label for="edit_telegram_chat">Telegram Chat ID</label>
                    <input type="text" name="telegram_chat_id" id="edit_telegram_chat" placeholder="123456789 or @username">
                    <small>
                        Leave empty to remove. <strong>For text & voice messages:</strong> Use numeric ID (e.g., 123456789). Get it by messaging @userinfobot. <strong>For voice calls:</strong> Use username format (e.g., @yourusername). Requires CallMeBot authorization (@CallMeBot_txtbot). <strong>Important:</strong> Start a conversation with your bot first!
                    </small>
                </div>
                <div class="form-group">
                    <div class="form-checkbox">
                        <input type="checkbox" name="enabled" id="edit_enabled" value="1">
                        <label for="edit_enabled">Enabled</label>
                    </div>
                    <small>Uncheck to disable monitoring</small>
                </div>
                <div class="form-actions">
                    <button type="button" class="btn" onclick="closeModal('editModal')">Cancel</button>
                    <button type="submit" class="btn btn-primary">Update</button>
                </div>
            </form>
        </div>
    </div>
    
    <!-- Delete Confirmation Modal -->
    <div id="deleteModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3>Delete Monitor</h3>
                <button class="close" onclick="closeModal('deleteModal')">&times;</button>
            </div>
            <p>Are you sure you want to delete this monitor? This action cannot be undone.</p>
            <form method="POST" action="" id="deleteForm">
                <input type="hidden" name="action" value="delete">
                <input type="hidden" name="monitor_id" id="delete_monitor_id">
                <div class="form-actions">
                    <button type="button" class="btn" onclick="closeModal('deleteModal')">Cancel</button>
                    <button type="submit" class="btn btn-danger">Delete</button>
                </div>
            </form>
        </div>
    </div>
    
    <!-- Reset History Confirmation Modal -->
    <div id="resetHistoryModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3>Reset Monitor History</h3>
                <button class="close" onclick="closeModal('resetHistoryModal')">&times;</button>
            </div>
            <p>Are you sure you want to reset all history for this monitor? This will delete all status records and cannot be undone.</p>
            <p style="color: #991b1b; font-weight: 500; margin-top: 1rem;">⚠️ This action will permanently delete all status history, uptime statistics, and historical data for this monitor.</p>
            <form method="POST" action="" id="resetHistoryForm">
                <input type="hidden" name="action" value="reset_history">
                <input type="hidden" name="monitor_id" id="reset_history_monitor_id">
                <div class="form-actions">
                    <button type="button" class="btn" onclick="closeModal('resetHistoryModal')">Cancel</button>
                    <button type="submit" class="btn btn-warning">Reset History</button>
                </div>
            </form>
        </div>
    </div>
    
    <script>
        function copyApiKey(userId, apiKey, event) {
            // Get the button element from the event
            const button = event && event.target ? event.target.closest('.btn-copy-api') : null;
            
            if (!button) {
                console.error('Button not found');
                return;
            }
            
            // Try using the Clipboard API first
            if (navigator.clipboard && window.isSecureContext) {
                navigator.clipboard.writeText(apiKey).then(function() {
                    showCopyFeedback(button);
                }).catch(function(err) {
                    console.error('Clipboard API failed:', err);
                    fallbackCopy(apiKey, button);
                });
            } else {
                // Fallback for older browsers
                fallbackCopy(apiKey, button);
            }
        }
        
        function fallbackCopy(text, button) {
            const textArea = document.createElement('textarea');
            textArea.value = text;
            textArea.style.position = 'fixed';
            textArea.style.left = '-999999px';
            textArea.style.top = '-999999px';
            textArea.style.opacity = '0';
            document.body.appendChild(textArea);
            textArea.focus();
            textArea.select();
            
            try {
                const successful = document.execCommand('copy');
                if (successful) {
                    showCopyFeedback(button);
                } else {
                    throw new Error('execCommand copy failed');
                }
            } catch (err) {
                console.error('Copy failed:', err);
                // Try to select the code element for manual copy
                const codeElement = button.closest('td').querySelector('code');
                if (codeElement) {
                    const range = document.createRange();
                    range.selectNodeContents(codeElement);
                    const selection = window.getSelection();
                    selection.removeAllRanges();
                    selection.addRange(range);
                    alert('Please press Ctrl+C (or Cmd+C on Mac) to copy the API key.');
                } else {
                    alert('Failed to copy API key. Please select and copy manually.');
                }
            }
            
            document.body.removeChild(textArea);
        }
        
        function showCopyFeedback(button) {
            if (!button) {
                console.error('Button not found for feedback');
                return;
            }
            
            const copyText = button.querySelector('.copy-text');
            const copyIcon = button.querySelector('.copy-icon');
            
            if (!copyText || !copyIcon) {
                console.error('Copy text or icon not found');
                return;
            }
            
            const originalText = copyText.textContent;
            const originalIcon = copyIcon.textContent;
            
            button.classList.add('copied');
            copyText.textContent = 'Copied!';
            copyIcon.textContent = '✓';
            
            setTimeout(function() {
                button.classList.remove('copied');
                copyText.textContent = originalText;
                copyIcon.textContent = originalIcon;
            }, 2000);
        }
        
        function openCreateUserModal() {
            document.getElementById('createUserModal').style.display = 'block';
        }
        
        function openEditUserModal(user) {
            document.getElementById('edit_user_user_id').value = user.id;
            document.getElementById('edit_user_email').value = user.email;
            document.getElementById('edit_regenerate_api_key').checked = false;
            document.getElementById('editUserModal').style.display = 'block';
        }
        
        function deleteUser(userId) {
            document.getElementById('delete_user_id').value = userId;
            document.getElementById('deleteUserModal').style.display = 'block';
        }
        
        function openCreateModal() {
            document.getElementById('createModal').style.display = 'block';
        }
        
        function openEditModal(monitor) {
            document.getElementById('edit_monitor_id').value = monitor.id;
            document.getElementById('edit_url').value = monitor.url;
            document.getElementById('edit_interval').value = monitor.interval_seconds;
            document.getElementById('edit_timeout').value = monitor.timeout_seconds;
            document.getElementById('edit_webhook').value = monitor.discord_webhook_url || '';
            document.getElementById('edit_telegram_token').value = monitor.telegram_bot_token || '';
            document.getElementById('edit_telegram_chat').value = monitor.telegram_chat_id || '';
            document.getElementById('edit_enabled').checked = monitor.enabled == 1;
            document.getElementById('editModal').style.display = 'block';
        }
        
        function deleteMonitor(monitorId) {
            document.getElementById('delete_monitor_id').value = monitorId;
            document.getElementById('deleteModal').style.display = 'block';
        }
        
        function resetMonitorHistory(monitorId) {
            document.getElementById('reset_history_monitor_id').value = monitorId;
            document.getElementById('resetHistoryModal').style.display = 'block';
        }
        
        function closeModal(modalId) {
            document.getElementById(modalId).style.display = 'none';
        }
        
        // Close modal when clicking outside
        window.onclick = function(event) {
            if (event.target.classList.contains('modal')) {
                event.target.style.display = 'none';
            }
        }
        
        // Auto-refresh every 30 seconds (only if no modals are open)
        setTimeout(() => {
            const modals = ['createUserModal', 'editUserModal', 'deleteUserModal', 'createModal', 'editModal', 'deleteModal', 'resetHistoryModal'];
            const anyOpen = modals.some(modalId => {
                const modal = document.getElementById(modalId);
                return modal && modal.style.display !== 'none';
            });
            if (!anyOpen) {
                location.reload();
            }
        }, 30000);
    </script>
</body>
</html>
