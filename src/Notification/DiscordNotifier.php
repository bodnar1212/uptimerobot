<?php

namespace UptimeRobot\Notification;

use UptimeRobot\Entity\Monitor;
use UptimeRobot\Entity\MonitorStatus;

class DiscordNotifier implements NotificationInterface
{
    private string $webhookUrl;

    public function __construct(string $webhookUrl)
    {
        $this->webhookUrl = $webhookUrl;
    }

    public function send(Monitor $monitor, MonitorStatus $status, ?string $previousStatus = null): bool
    {
        $message = $this->buildMessage($monitor, $status, $previousStatus);
        
        $payload = [
            'embeds' => [
                [
                    'title' => $this->getTitle($status->getStatus()),
                    'description' => $message,
                    'color' => $this->getColor($status->getStatus()),
                    'fields' => [
                        [
                            'name' => 'Monitor URL',
                            'value' => $monitor->getUrl(),
                            'inline' => false,
                        ],
                        [
                            'name' => 'Status',
                            'value' => strtoupper($status->getStatus()),
                            'inline' => true,
                        ],
                        [
                            'name' => 'Response Time',
                            'value' => $status->getResponseTimeMs() . ' ms',
                            'inline' => true,
                        ],
                    ],
                    'timestamp' => $status->getCheckedAt()->format('c'),
                ],
            ],
        ];

        if ($status->getHttpStatusCode()) {
            $payload['embeds'][0]['fields'][] = [
                'name' => 'HTTP Status',
                'value' => (string)$status->getHttpStatusCode(),
                'inline' => true,
            ];
        }

        if ($status->getErrorMessage()) {
            $payload['embeds'][0]['fields'][] = [
                'name' => 'Error',
                'value' => substr($status->getErrorMessage(), 0, 1000),
                'inline' => false,
            ];
        }

        return $this->sendWebhook($payload);
    }

    private function buildMessage(Monitor $monitor, MonitorStatus $status, ?string $previousStatus): string
    {
        $statusText = strtoupper($status->getStatus());
        
        if ($previousStatus === null) {
            return "Monitor **{$monitor->getUrl()}** is now **{$statusText}**";
        }

        if ($status->getStatus() === 'down') {
            return "Monitor **{$monitor->getUrl()}** is **DOWN**";
        }

        return "Monitor **{$monitor->getUrl()}** status: **{$statusText}**";
    }

    private function getTitle(string $status): string
    {
        return match ($status) {
            'up' => '✅ Monitor is UP',
            'down' => '❌ Monitor is DOWN',
            default => 'Monitor Status Update',
        };
    }

    private function getColor(string $status): int
    {
        return match ($status) {
            'up' => 0x00ff00,      // Green
            'down' => 0xff0000,    // Red
            default => 0x808080,    // Gray
        };
    }

    private function sendWebhook(array $payload): bool
    {
        $ch = curl_init($this->webhookUrl);
        
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
            return true;
        }

        error_log("Discord webhook failed: HTTP {$httpCode} - {$error}");
        return false;
    }

    public function getType(): string
    {
        return 'discord';
    }
}

