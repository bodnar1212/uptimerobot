<?php

namespace UptimeRobot\Notification;

class NotificationFactory
{
    /**
     * Create a notifier instance based on type
     * 
     * @param string $type Notification type (e.g., 'discord')
     * @param array $config Configuration specific to the notifier type
     * @return NotificationInterface
     * @throws \InvalidArgumentException If notifier type is not supported
     */
    public static function create(string $type, array $config): NotificationInterface
    {
        return match ($type) {
            'discord' => new DiscordNotifier($config['webhook_url'] ?? ''),
            default => throw new \InvalidArgumentException("Unsupported notification type: {$type}"),
        };
    }

    /**
     * Get available notification types
     * 
     * @return array
     */
    public static function getAvailableTypes(): array
    {
        return ['discord'];
    }
}

