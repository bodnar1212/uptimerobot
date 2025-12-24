<?php

namespace UptimeRobot\Service;

use UptimeRobot\Repository\MonitorRepository;
use UptimeRobot\Repository\MonitorStatusRepository;
use UptimeRobot\Repository\QueueRepository;

class SchedulerService
{
    private MonitorRepository $monitorRepository;
    private MonitorStatusRepository $statusRepository;
    private QueueRepository $queueRepository;

    public function __construct(
        MonitorRepository $monitorRepository,
        MonitorStatusRepository $statusRepository,
        QueueRepository $queueRepository
    ) {
        $this->monitorRepository = $monitorRepository;
        $this->statusRepository = $statusRepository;
        $this->queueRepository = $queueRepository;
    }

    /**
     * Schedule checks for all enabled monitors that are due
     */
    public function scheduleDueChecks(): int
    {
        $monitors = $this->monitorRepository->findEnabled();
        $scheduled = 0;

        foreach ($monitors as $monitor) {
            if ($this->shouldScheduleCheck($monitor)) {
                $this->scheduleCheck($monitor);
                $scheduled++;
            }
        }

        return $scheduled;
    }

    /**
     * Determine if a check should be scheduled for a monitor
     */
    private function shouldScheduleCheck($monitor): bool
    {
        $latestStatus = $this->statusRepository->getLatestStatus($monitor->getId());

        if (!$latestStatus) {
            // No previous check, schedule immediately
            return true;
        }

        $lastCheckTime = $latestStatus->getCheckedAt()->getTimestamp();
        $interval = $monitor->getIntervalSeconds();
        $nextCheckTime = $lastCheckTime + $interval;
        $now = time();

        // Schedule if the interval has passed
        return $now >= $nextCheckTime;
    }

    /**
     * Schedule a check for a monitor
     */
    private function scheduleCheck($monitor): void
    {
        $latestStatus = $this->statusRepository->getLatestStatus($monitor->getId());

        if ($latestStatus) {
            $lastCheckTime = $latestStatus->getCheckedAt()->getTimestamp();
            $interval = $monitor->getIntervalSeconds();
            $scheduledAt = new \DateTime('@' . ($lastCheckTime + $interval));
        } else {
            // First check, schedule immediately
            $scheduledAt = new \DateTime();
        }

        $this->queueRepository->createJob($monitor->getId(), $scheduledAt);
    }
}

