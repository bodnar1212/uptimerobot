<?php

namespace UptimeRobot\Notification;

use UptimeRobot\Entity\Monitor;
use UptimeRobot\Entity\MonitorStatus;

interface NotificationInterface
{
    /**
     * Send a notification about a monitor status change
     * 
     * @param Monitor $monitor The monitor that changed status
     * @param MonitorStatus $status The new status
     * @param string $previousStatus The previous status ('up', 'down', or null if first check)
     * @return bool True if notification was sent successfully
     */
    public function send(Monitor $monitor, MonitorStatus $status, ?string $previousStatus = null): bool;

    /**
     * Get the notification type identifier
     */
    public function getType(): string;
}

