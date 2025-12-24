<?php

namespace UptimeRobot\Service;

use UptimeRobot\Entity\Monitor;
use UptimeRobot\Entity\MonitorStatus;
use UptimeRobot\Notification\NotificationFactory;
use UptimeRobot\Repository\MonitorStatusRepository;

class NotificationService
{
    private MonitorStatusRepository $statusRepository;

    public function __construct(MonitorStatusRepository $statusRepository)
    {
        $this->statusRepository = $statusRepository;
    }

    /**
     * Process status change and send notifications if needed
     * 
     * @param Monitor $monitor The monitor
     * @param MonitorStatus $newStatus The new status that was just saved
     * @param string|null $previousStatus The previous status (if known, otherwise will be fetched)
     */
    public function processStatusChange(Monitor $monitor, MonitorStatus $newStatus, ?string $previousStatus = null): void
    {
        // Get previous status if not provided
        if ($previousStatus === null) {
            // Get the two most recent statuses to exclude the one we just saved
            $recentStatuses = $this->statusRepository->findByMonitorId($monitor->getId(), 2);
            
            // Find the status that's not the current one
            foreach ($recentStatuses as $status) {
                if ($status->getId() !== $newStatus->getId()) {
                    $previousStatus = $status->getStatus();
                    break;
                }
            }
        }

        // Check if notification should be sent
        if ($this->shouldNotify($newStatus->getStatus(), $previousStatus)) {
            $this->sendNotification($monitor, $newStatus, $previousStatus);
        }
    }

    /**
     * Determine if a notification should be sent
     */
    private function shouldNotify(string $newStatus, ?string $previousStatus): bool
    {
        // Always notify on status changes
        if ($previousStatus === null) {
            return true; // First check
        }

        if ($newStatus !== $previousStatus) {
            return true; // Status changed
        }

        return false; // No change, no notification
    }

    /**
     * Send notification using appropriate notifier
     */
    private function sendNotification(Monitor $monitor, MonitorStatus $status, ?string $previousStatus): void
    {
        // Get webhook URL from monitor
        $webhookUrl = $monitor->getDiscordWebhookUrl();

        if (empty($webhookUrl)) {
            // No webhook configured, skip notification
            return;
        }

        try {
            $notifier = NotificationFactory::create('discord', [
                'webhook_url' => $webhookUrl,
            ]);

            $notifier->send($monitor, $status, $previousStatus);
        } catch (\Exception $e) {
            error_log("Failed to send notification: " . $e->getMessage());
        }
    }
}

