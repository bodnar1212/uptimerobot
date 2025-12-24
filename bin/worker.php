#!/usr/bin/env php
<?php

require_once __DIR__ . '/../vendor/autoload.php';

use UptimeRobot\Database\Connection;
use UptimeRobot\Repository\MonitorRepository;
use UptimeRobot\Repository\MonitorStatusRepository;
use UptimeRobot\Repository\QueueRepository;
use UptimeRobot\Service\HttpCheckService;
use UptimeRobot\Service\NotificationService;
use UptimeRobot\Service\WorkerService;
use UptimeRobot\Http\HttpClient;

// Initialize database connection
Connection::setConfig(require __DIR__ . '/../config/database.php');

// Initialize repositories and services
$monitorRepository = new MonitorRepository();
$statusRepository = new MonitorStatusRepository();
$queueRepository = new QueueRepository();
$httpClient = new HttpClient();
$httpCheckService = new HttpCheckService($httpClient, $monitorRepository);
$notificationService = new NotificationService($statusRepository);

// Get concurrency limit from environment or use default
$concurrencyLimit = (int)(getenv('WORKER_CONCURRENCY') ?: 50);

$workerService = new WorkerService(
    $queueRepository,
    $monitorRepository,
    $statusRepository,
    $httpCheckService,
    $notificationService,
    $concurrencyLimit
);

echo "Worker started (concurrency limit: {$concurrencyLimit})\n";
echo "Press Ctrl+C to stop\n\n";

$iteration = 0;
while (true) {
    $iteration++;
    $processed = $workerService->processJobs();

    if ($processed > 0) {
        echo "[" . date('Y-m-d H:i:s') . "] Processed {$processed} job(s)\n";
    }

    // Sleep for 1 second before next iteration
    sleep(1);

    // Cleanup old jobs every 100 iterations
    if ($iteration % 100 === 0) {
        $deleted = $queueRepository->deleteOldJobs(7);
        if ($deleted > 0) {
            echo "[" . date('Y-m-d H:i:s') . "] Cleaned up {$deleted} old job(s)\n";
        }
    }
}

