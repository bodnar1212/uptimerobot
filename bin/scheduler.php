#!/usr/bin/env php
<?php

require_once __DIR__ . '/../vendor/autoload.php';

use UptimeRobot\Database\Connection;
use UptimeRobot\Repository\MonitorRepository;
use UptimeRobot\Repository\MonitorStatusRepository;
use UptimeRobot\Repository\QueueRepository;
use UptimeRobot\Repository\SettingsRepository;
use UptimeRobot\Service\SchedulerService;

// Initialize database connection
Connection::setConfig(require __DIR__ . '/../config/database.php');

// Initialize repositories and services
$monitorRepository = new MonitorRepository();
$statusRepository = new MonitorStatusRepository();
$queueRepository = new QueueRepository();

$schedulerService = new SchedulerService(
    $monitorRepository,
    $statusRepository,
    $queueRepository
);

// Initialize settings repository
$settingsRepository = new SettingsRepository();

// Get interval from database settings, environment variable, or use default (30 seconds)
$interval = (int)($settingsRepository->get('scheduler_interval') ?: getenv('SCHEDULER_INTERVAL') ?: 30);

echo "Scheduler started (interval: {$interval} seconds)\n";
echo "Press Ctrl+C to stop\n\n";

while (true) {
    try {
        $scheduled = $schedulerService->scheduleDueChecks();

        if ($scheduled > 0) {
            echo "[" . date('Y-m-d H:i:s') . "] Scheduled {$scheduled} check(s)\n";
        }
    } catch (\Exception $e) {
        echo "[" . date('Y-m-d H:i:s') . "] Error: " . $e->getMessage() . "\n";
    }

    sleep($interval);
}

