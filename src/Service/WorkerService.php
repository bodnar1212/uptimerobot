<?php

namespace UptimeRobot\Service;

use UptimeRobot\Entity\MonitorStatus;
use UptimeRobot\Repository\MonitorRepository;
use UptimeRobot\Repository\MonitorStatusRepository;
use UptimeRobot\Repository\QueueRepository;

class WorkerService
{
    private QueueRepository $queueRepository;
    private MonitorRepository $monitorRepository;
    private MonitorStatusRepository $statusRepository;
    private HttpCheckService $httpCheckService;
    private NotificationService $notificationService;
    private int $concurrencyLimit;

    public function __construct(
        QueueRepository $queueRepository,
        MonitorRepository $monitorRepository,
        MonitorStatusRepository $statusRepository,
        HttpCheckService $httpCheckService,
        NotificationService $notificationService,
        int $concurrencyLimit = 50
    ) {
        $this->queueRepository = $queueRepository;
        $this->monitorRepository = $monitorRepository;
        $this->statusRepository = $statusRepository;
        $this->httpCheckService = $httpCheckService;
        $this->notificationService = $notificationService;
        $this->concurrencyLimit = $concurrencyLimit;
    }

    /**
     * Process pending jobs from the queue
     */
    public function processJobs(): int
    {
        $jobs = $this->queueRepository->getPendingJobs($this->concurrencyLimit);

        if (empty($jobs)) {
            return 0;
        }

        $processed = 0;

        // Group jobs by monitor_id for batch processing
        $monitorIds = array_unique(array_column($jobs, 'monitor_id'));

        // Mark all jobs as processing
        foreach ($jobs as $job) {
            $this->queueRepository->markAsProcessing($job['id']);
        }

        // Perform concurrent HTTP checks
        $checkResults = $this->httpCheckService->checkMonitorsConcurrent($monitorIds);

        // Create a map of monitor_id => result
        $resultsMap = [];
        foreach ($checkResults as $result) {
            $resultsMap[$result['monitor_id']] = $result;
        }

        // Process each job
        foreach ($jobs as $job) {
            try {
                $monitorId = $job['monitor_id'];
                $monitor = $this->monitorRepository->findById($monitorId);

                if (!$monitor) {
                    // Monitor was deleted, mark job as completed
                    $this->queueRepository->markAsCompleted($job['id']);
                    continue;
                }

                $result = $resultsMap[$monitorId] ?? null;

                if (!$result) {
                    // No result available, mark as failed
                    $this->queueRepository->markAsFailed($job['id']);
                    continue;
                }

                // Get previous status before creating new one
                $previousStatus = $this->statusRepository->getLatestStatus($monitorId);
                $previousStatusValue = $previousStatus ? $previousStatus->getStatus() : null;

                // Determine status from check result
                $finalStatus = $result['success'] ? 'up' : 'down';

                // Create monitor status record
                $monitorStatus = new MonitorStatus(
                    $monitorId,
                    $finalStatus,
                    null,
                    new \DateTime(),
                    $result['response_time_ms'],
                    $result['http_status_code'],
                    $result['error_message']
                );

                // Save the status
                $monitorStatus = $this->statusRepository->create($monitorStatus);

                // Process notification if status changed (pass previous status to avoid re-querying)
                $this->notificationService->processStatusChange($monitor, $monitorStatus, $previousStatusValue);

                // Mark job as completed
                $this->queueRepository->markAsCompleted($job['id']);
                $processed++;

            } catch (\Exception $e) {
                error_log("Error processing job {$job['id']}: " . $e->getMessage());
                $this->queueRepository->markAsFailed($job['id']);
            }
        }

        return $processed;
    }

    /**
     * Set concurrency limit
     */
    public function setConcurrencyLimit(int $limit): void
    {
        $this->concurrencyLimit = $limit;
    }
}

