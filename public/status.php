<?php

require_once __DIR__ . '/../vendor/autoload.php';

use UptimeRobot\Database\Connection;
use UptimeRobot\Repository\MonitorRepository;
use UptimeRobot\Repository\MonitorStatusRepository;

// Initialize database connection
Connection::setConfig(require __DIR__ . '/../config/database.php');

// Initialize repositories
$monitorRepository = new MonitorRepository();
$statusRepository = new MonitorStatusRepository();

// Get all enabled monitors
$monitors = $monitorRepository->findEnabled();

// Get status data for each monitor
$pdo = Connection::getInstance();
$monitorData = [];

foreach ($monitors as $monitor) {
    $monitorId = $monitor->getId();
    
    // Get latest status
    $latestStatus = $statusRepository->getLatestStatus($monitorId);
    
    // Get status history for last 90 days
    $historyStmt = $pdo->prepare('
        SELECT DATE(checked_at) as date, status, COUNT(*) as count
        FROM monitor_statuses
        WHERE monitor_id = ? AND checked_at >= DATE_SUB(NOW(), INTERVAL 90 DAY)
        GROUP BY DATE(checked_at), status
        ORDER BY date DESC
    ');
    $historyStmt->execute([$monitorId]);
    $history = $historyStmt->fetchAll();
    
    // Calculate uptime percentage for last 90 days
    $totalChecks = 0;
    $upChecks = 0;
    foreach ($history as $day) {
        $totalChecks += $day['count'];
        if ($day['status'] === 'up') {
            $upChecks += $day['count'];
        }
    }
    $uptimePercent = $totalChecks > 0 ? round(($upChecks / $totalChecks) * 100, 2) : 100;
    
    // Get recent status changes (for status updates) - show all status changes, not just down
    // Get all statuses and filter to show only when status actually changed
    $recentChangesStmt = $pdo->prepare('
        SELECT ms.*, m.url
        FROM monitor_statuses ms
        INNER JOIN monitors m ON ms.monitor_id = m.id
        WHERE ms.monitor_id = ?
        AND ms.checked_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
        ORDER BY ms.checked_at DESC
        LIMIT 100
    ');
    $recentChangesStmt->execute([$monitorId]);
    $allStatuses = $recentChangesStmt->fetchAll();
    
    // Filter to only show status changes (where status differs from previous)
    // Always include the first (most recent) status, then show changes
    $recentChanges = [];
    $previousStatus = null;
    foreach ($allStatuses as $status) {
        // Always include first status, then only include when status changes
        if ($previousStatus === null || $status['status'] !== $previousStatus) {
            $recentChanges[] = $status;
            $previousStatus = $status['status'];
        }
        if (count($recentChanges) >= 20) {
            break;
        }
    }
    
    // Build calendar data (last 90 days)
    $calendarData = [];
    $today = new DateTime();
    for ($i = 0; $i < 90; $i++) {
        $date = clone $today;
        $date->modify("-{$i} days");
        $dateStr = $date->format('Y-m-d');
        
        $dayStatus = null;
        foreach ($history as $day) {
            if ($day['date'] === $dateStr) {
                $dayStatus = $day['status'];
                break;
            }
        }
        
        $calendarData[] = [
            'date' => $dateStr,
            'status' => $dayStatus,
            'day' => $date->format('j'),
            'weekday' => $date->format('D')
        ];
    }
    
    $monitorData[] = [
        'monitor' => $monitor,
        'latest_status' => $latestStatus,
        'uptime_percent' => $uptimePercent,
        'calendar' => array_reverse($calendarData),
        'recent_changes' => $recentChanges
    ];
}

// Determine overall status
$overallStatus = 'operational';
foreach ($monitorData as $data) {
    if ($data['latest_status'] && $data['latest_status']->getStatus() === 'down') {
        $overallStatus = 'degraded';
        break;
    }
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Service Status - UptimeRobot</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, Cantarell, sans-serif;
            background: #f7f8fa;
            color: #1a1a1a;
            line-height: 1.6;
        }
        
        .header {
            background: white;
            border-bottom: 1px solid #e5e7eb;
            padding: 1.5rem 2rem;
            position: sticky;
            top: 0;
            z-index: 100;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
        }
        
        .header-content {
            max-width: 1200px;
            margin: 0 auto;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .header h1 {
            font-size: 1.5rem;
            font-weight: 600;
            color: #1a1a1a;
        }
        
        .status-indicator {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.5rem 1rem;
            border-radius: 6px;
            font-size: 0.875rem;
            font-weight: 500;
        }
        
        .status-indicator.operational {
            background: #d1fae5;
            color: #065f46;
        }
        
        .status-indicator.degraded {
            background: #fee2e2;
            color: #991b1b;
        }
        
        .status-dot {
            width: 8px;
            height: 8px;
            border-radius: 50%;
            display: inline-block;
        }
        
        .status-dot.operational {
            background: #10b981;
        }
        
        .status-dot.degraded {
            background: #ef4444;
        }
        
        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 2rem;
        }
        
        .status-section {
            background: white;
            border-radius: 8px;
            padding: 2rem;
            margin-bottom: 2rem;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
        }
        
        .status-section h2 {
            font-size: 1.25rem;
            font-weight: 600;
            margin-bottom: 1.5rem;
            color: #1a1a1a;
        }
        
        .service-list {
            display: grid;
            gap: 1.5rem;
        }
        
        .service-item {
            border: 1px solid #e5e7eb;
            border-radius: 8px;
            padding: 1.5rem;
            background: white;
        }
        
        .service-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1rem;
        }
        
        .service-name {
            font-size: 1.125rem;
            font-weight: 600;
            color: #1a1a1a;
        }
        
        .service-url {
            font-size: 0.875rem;
            color: #6b7280;
            margin-top: 0.25rem;
        }
        
        .service-status {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            font-size: 0.875rem;
            font-weight: 500;
        }
        
        .service-status.up {
            color: #10b981;
        }
        
        .service-status.down {
            color: #ef4444;
        }
        
        .uptime-section {
            margin-top: 1rem;
            padding-top: 1rem;
            border-top: 1px solid #e5e7eb;
        }
        
        .uptime-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1rem;
        }
        
        .uptime-label {
            font-size: 0.875rem;
            color: #6b7280;
        }
        
        .uptime-percent {
            font-size: 1.5rem;
            font-weight: 600;
            color: #1a1a1a;
        }
        
        .calendar-container {
            margin-top: 1rem;
        }
        
        .calendar-grid {
            display: grid;
            grid-template-columns: repeat(13, 1fr);
            gap: 4px;
            margin-top: 0.5rem;
        }
        
        .calendar-day {
            width: 100%;
            aspect-ratio: 1;
            border-radius: 3px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 0.75rem;
            cursor: pointer;
            position: relative;
        }
        
        .calendar-day.up {
            background: #d1fae5;
        }
        
        .calendar-day.down {
            background: #fee2e2;
        }
        
            background: #dbeafe;
        }
        
        .calendar-day.none {
            background: #f3f4f6;
        }
        
        .calendar-day:hover {
            opacity: 0.8;
        }
        
        .calendar-legend {
            display: flex;
            gap: 1rem;
            margin-top: 1rem;
            font-size: 0.75rem;
            color: #6b7280;
        }
        
        .legend-item {
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .legend-color {
            width: 12px;
            height: 12px;
            border-radius: 3px;
        }
        
        .updates-section {
            margin-top: 2rem;
        }
        
        .update-item {
            padding: 1rem;
            border-left: 3px solid #e5e7eb;
            margin-bottom: 1rem;
            background: #f9fafb;
            border-radius: 4px;
        }
        
        .update-item.down {
            border-left-color: #ef4444;
        }
        
        .update-item.up {
            border-left-color: #10b981;
        }
        
        .update-header {
            display: flex;
            justify-content: space-between;
            align-items: start;
            margin-bottom: 0.5rem;
        }
        
        .update-title {
            font-weight: 600;
            color: #1a1a1a;
        }
        
        .update-time {
            font-size: 0.875rem;
            color: #6b7280;
        }
        
        .update-details {
            font-size: 0.875rem;
            color: #6b7280;
        }
        
        .no-updates {
            text-align: center;
            padding: 2rem;
            color: #6b7280;
            font-size: 0.875rem;
        }
        
        .last-updated {
            text-align: center;
            padding: 1rem;
            font-size: 0.875rem;
            color: #6b7280;
            background: white;
            border-radius: 8px;
            margin-bottom: 2rem;
        }
        
        @media (max-width: 768px) {
            .calendar-grid {
                grid-template-columns: repeat(7, 1fr);
            }
            
            .service-header {
                flex-direction: column;
                align-items: flex-start;
                gap: 0.5rem;
            }
        }
    </style>
</head>
<body>
    <div class="header">
        <div class="header-content">
            <h1>Service Status</h1>
            <div class="status-indicator <?= $overallStatus ?>">
                <span class="status-dot <?= $overallStatus ?>"></span>
                <?= ucfirst($overallStatus) ?>
            </div>
        </div>
    </div>
    
    <div class="container">
        <div class="last-updated">
            Last updated: <span id="lastUpdated"><?= date('Y-m-d H:i:s') ?></span> | 
            Next update in <span id="nextUpdate">60</span> sec.
        </div>
        
        <div class="status-section">
            <h2>Service status</h2>
            <div class="service-list">
                <?php if (empty($monitorData)): ?>
                    <div class="no-updates">No services are currently being monitored.</div>
                <?php else: ?>
                    <?php foreach ($monitorData as $data): 
                        $monitor = $data['monitor'];
                        $latestStatus = $data['latest_status'];
                        $status = $latestStatus ? $latestStatus->getStatus() : 'unknown';
                        $statusClass = $status === 'up' ? 'up' : 'down';
                    ?>
                    <div class="service-item">
                        <div class="service-header">
                            <div>
                                <div class="service-name"><?= htmlspecialchars(parse_url($monitor->getUrl(), PHP_URL_HOST) ?: $monitor->getUrl()) ?></div>
                                <div class="service-url"><?= htmlspecialchars($monitor->getUrl()) ?></div>
                            </div>
                            <div class="service-status <?= $statusClass ?>">
                                <span class="status-dot <?= $statusClass ?>"></span>
                                <?= strtoupper($status) ?>
                            </div>
                        </div>
                        
                        <div class="uptime-section">
                            <div class="uptime-header">
                                <span class="uptime-label">Uptime</span>
                                <span class="uptime-percent"><?= $data['uptime_percent'] ?>%</span>
                            </div>
                            <div class="calendar-container">
                                <div style="font-size: 0.75rem; color: #6b7280; margin-bottom: 0.5rem;">Last 90 days</div>
                                <div class="calendar-grid">
                                    <?php foreach ($data['calendar'] as $day): 
                                        $dayClass = $day['status'] ? $day['status'] : 'none';
                                    ?>
                                    <div class="calendar-day <?= $dayClass ?>" title="<?= htmlspecialchars($day['date'] . ' - ' . ($day['status'] ?: 'No data')) ?>">
                                        <?= $day['day'] ?>
                                    </div>
                                    <?php endforeach; ?>
                                </div>
                                <div class="calendar-legend">
                                    <div class="legend-item">
                                        <div class="legend-color" style="background: #d1fae5;"></div>
                                        <span>Operational</span>
                                    </div>
                                    <div class="legend-item">
                                        <div class="legend-color" style="background: #fee2e2;"></div>
                                        <span>Down</span>
                                    </div>
                                    <div class="legend-item">
                                        <div class="legend-color" style="background: #f3f4f6;"></div>
                                        <span>No data</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
        
        <div class="status-section updates-section">
            <h2>Status updates</h2>
            <div style="font-size: 0.875rem; color: #6b7280; margin-bottom: 1rem;">Last 7 days</div>
            
            <?php 
            $allUpdates = [];
            foreach ($monitorData as $data) {
                foreach ($data['recent_changes'] as $change) {
                    $allUpdates[] = $change;
                }
            }
            
            // Sort by date
            usort($allUpdates, function($a, $b) {
                return strtotime($b['checked_at']) - strtotime($a['checked_at']);
            });
            
            $allUpdates = array_slice($allUpdates, 0, 20);
            ?>
            
            <?php if (empty($allUpdates)): ?>
                <div class="no-updates">There are no updates in the last 7 days.</div>
            <?php else: ?>
                <?php foreach ($allUpdates as $update): 
                    $updateClass = $update['status'];
                    if ($update['status'] === 'down') {
                        $updateTitle = 'Service Degraded';
                        $updateMessage = 'Service is currently experiencing issues.';
                    } else {
                        $updateTitle = 'Service Operational';
                        $updateMessage = 'Service is now operational.';
                    }
                ?>
                <div class="update-item <?= $updateClass ?>">
                    <div class="update-header">
                        <div class="update-title"><?= htmlspecialchars($updateTitle) ?> - <?= htmlspecialchars(parse_url($update['url'], PHP_URL_HOST) ?: $update['url']) ?></div>
                        <div class="update-time"><?= date('M j, Y g:i A', strtotime($update['checked_at'])) ?></div>
                    </div>
                    <div class="update-details">
                        <?= $updateMessage ?>
                        <?php if ($update['response_time_ms']): ?>
                            Response time: <?= $update['response_time_ms'] ?>ms
                        <?php endif; ?>
                        <?php if ($update['http_status_code']): ?>
                            HTTP Status: <?= $update['http_status_code'] ?>
                        <?php endif; ?>
                        <?php if ($update['error_message']): ?>
                            Error: <?= htmlspecialchars($update['error_message']) ?>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>
    
    <script>
        // Update countdown timer
        let nextUpdate = 60;
        setInterval(() => {
            nextUpdate--;
            if (nextUpdate <= 0) {
                nextUpdate = 60;
                location.reload();
            }
            document.getElementById('nextUpdate').textContent = nextUpdate;
        }, 1000);
        
        // Update last updated time
        setInterval(() => {
            const now = new Date();
            document.getElementById('lastUpdated').textContent = now.toLocaleString();
        }, 1000);
    </script>
</body>
</html>

